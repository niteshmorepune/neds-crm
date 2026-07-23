<?php

use App\Enums\UserRole;
use App\Enums\VoiceTranscriptStatus;
use App\Jobs\TranscribeCallLogVoiceNote;
use App\Livewire\CallVoiceTranscript;
use App\Models\AiUsage;
use App\Models\Attachment;
use App\Models\CallLog;
use App\Models\Customer;
use App\Models\User;
use App\Services\AnthropicClient;
use App\Services\GoogleSpeechClient;
use Database\Seeders\MenuItemsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

function enableCallVoiceAi(): void
{
    config([
        'services.anthropic.enabled' => true,
        'services.anthropic.key' => 'sk-test',
        'services.google_speech.api_key' => 'g-test',
    ]);
}

function fakeGoogleSpeechAndClaude(?string $rawTranscript = 'client ko call kiya, follow up chahiye', ?string $englishText = 'Called the client, follow-up needed.'): void
{
    Http::fake([
        'speech.googleapis.com/*' => $rawTranscript === null
            ? Http::response('upstream error', 500)
            : Http::response(['results' => [['alternatives' => [['transcript' => $rawTranscript]]]]]),
        'api.anthropic.com/*' => $englishText === null
            ? Http::response('upstream error', 500)
            : Http::response([
                'content' => [['type' => 'text', 'text' => $englishText]],
                'usage' => ['input_tokens' => 50, 'output_tokens' => 20],
            ]),
    ]);
}

beforeEach(function () {
    $this->seed(MenuItemsSeeder::class);
    $this->sales = User::factory()->role(UserRole::Sales)->create();
});

it('uploads a voice note and dispatches transcription when AI is enabled', function () {
    enableCallVoiceAi();
    Storage::fake('local');
    Queue::fake();
    $customer = Customer::factory()->create();

    $this->actingAs($this->sales)->post(route('calls.store'), [
        'customer_id' => $customer->id,
        'direction' => 'outgoing',
        'outcome' => 'connected',
        'called_at' => now()->format('Y-m-d H:i:s'),
        'voice_note' => UploadedFile::fake()->create('note.webm', 200, 'audio/webm'),
    ])->assertRedirect(route('clients.show', $customer));

    $call = CallLog::firstOrFail();
    $attachment = Attachment::first();

    expect($attachment)->not->toBeNull()
        ->and($attachment->attachable_id)->toBe($call->id)
        ->and($attachment->attachable_type)->toBe(CallLog::class)
        ->and($call->voice_transcript_status)->toBe(VoiceTranscriptStatus::Pending);

    Storage::disk('local')->assertExists($attachment->path);
    Queue::assertPushed(TranscribeCallLogVoiceNote::class, fn ($job) => $job->callLogId === $call->id && $job->attachmentId === $attachment->id);
});

it('does not attach a voice note when AI is disabled', function () {
    config(['services.anthropic.enabled' => false]);
    Storage::fake('local');
    Queue::fake();
    $customer = Customer::factory()->create();

    $this->actingAs($this->sales)->post(route('calls.store'), [
        'customer_id' => $customer->id,
        'direction' => 'outgoing',
        'outcome' => 'connected',
        'called_at' => now()->format('Y-m-d H:i:s'),
        'voice_note' => UploadedFile::fake()->create('note.webm', 200, 'audio/webm'),
    ])->assertRedirect(route('clients.show', $customer));

    expect(Attachment::count())->toBe(0);
    expect(CallLog::firstOrFail()->voice_transcript_status)->toBeNull();
    Queue::assertNothingPushed();
});

it('transcribes and translates a voice note into English', function () {
    enableCallVoiceAi();
    Storage::fake('local');
    fakeGoogleSpeechAndClaude();
    $call = CallLog::factory()->create();
    $attachment = $call->attachments()->create([
        'uploaded_by' => $this->sales->id,
        'disk' => 'local',
        'path' => 'call-voice-notes/note.webm',
        'original_name' => 'note.webm',
        'mime_type' => 'audio/webm',
        'size' => 1000,
    ]);
    Storage::disk('local')->put($attachment->path, 'fake-audio-bytes');

    (new TranscribeCallLogVoiceNote($call->id, $attachment->id))->handle(app(GoogleSpeechClient::class), app(AnthropicClient::class));

    $call->refresh();
    expect($call->voice_transcript_status)->toBe(VoiceTranscriptStatus::Completed)
        ->and($call->voice_transcript)->toBe('Called the client, follow-up needed.')
        ->and($call->voice_transcribed_at)->not->toBeNull();

    expect(AiUsage::where('feature', 'call_voice_transcript_translate')->first())
        ->input_tokens->toBe(50)
        ->output_tokens->toBe(20);
});

it('marks the call failed when speech-to-text fails', function () {
    enableCallVoiceAi();
    Storage::fake('local');
    fakeGoogleSpeechAndClaude(rawTranscript: null);
    $call = CallLog::factory()->create();
    $attachment = $call->attachments()->create([
        'uploaded_by' => $this->sales->id,
        'disk' => 'local',
        'path' => 'call-voice-notes/note.webm',
        'original_name' => 'note.webm',
        'mime_type' => 'audio/webm',
        'size' => 1000,
    ]);
    Storage::disk('local')->put($attachment->path, 'fake-audio-bytes');

    (new TranscribeCallLogVoiceNote($call->id, $attachment->id))->handle(app(GoogleSpeechClient::class), app(AnthropicClient::class));

    expect($call->refresh()->voice_transcript_status)->toBe(VoiceTranscriptStatus::Failed);
});

it('marks the call failed when Claude translation fails', function () {
    enableCallVoiceAi();
    Storage::fake('local');
    fakeGoogleSpeechAndClaude(englishText: null);
    $call = CallLog::factory()->create();
    $attachment = $call->attachments()->create([
        'uploaded_by' => $this->sales->id,
        'disk' => 'local',
        'path' => 'call-voice-notes/note.webm',
        'original_name' => 'note.webm',
        'mime_type' => 'audio/webm',
        'size' => 1000,
    ]);
    Storage::disk('local')->put($attachment->path, 'fake-audio-bytes');

    (new TranscribeCallLogVoiceNote($call->id, $attachment->id))->handle(app(GoogleSpeechClient::class), app(AnthropicClient::class));

    expect($call->refresh()->voice_transcript_status)->toBe(VoiceTranscriptStatus::Failed);
});

it('is a no-op when the call log or attachment no longer exists', function () {
    enableCallVoiceAi();
    fakeGoogleSpeechAndClaude();

    (new TranscribeCallLogVoiceNote(99999, 99999))->handle(app(GoogleSpeechClient::class), app(AnthropicClient::class));

    Http::assertNothingSent();
});

it('shows the record-voice-note button on the create form when AI is enabled', function () {
    enableCallVoiceAi();

    $this->actingAs($this->sales)->get(route('calls.create'))
        ->assertOk()
        ->assertSee('Record voice note', false);
});

it('hides the record-voice-note button entirely when AI is disabled', function () {
    config(['services.anthropic.enabled' => false]);

    $this->actingAs($this->sales)->get(route('calls.create'))
        ->assertOk()
        ->assertDontSee('Record voice note', false);
});

it('hides the record-voice-note button when Claude is on but no Google Speech key is set', function () {
    config([
        'services.anthropic.enabled' => true,
        'services.anthropic.key' => 'sk-test',
        'services.google_speech.api_key' => null,
    ]);

    $this->actingAs($this->sales)->get(route('calls.create'))
        ->assertOk()
        ->assertDontSee('Record voice note', false);
});

it('offers Hindi and Marathi as dictation language options, not just English', function () {
    $this->actingAs($this->sales)->get(route('calls.create'))
        ->assertOk()
        ->assertSee('value="en-IN"', false)
        ->assertSee('value="hi-IN"', false)
        ->assertSee('value="mr-IN"', false);
});

it('exposes a playable link to the raw voice-note recording regardless of transcript status', function () {
    Storage::fake('local');
    $call = CallLog::factory()->create(['voice_transcript_status' => VoiceTranscriptStatus::Pending]);
    $attachment = $call->attachments()->create([
        'uploaded_by' => $this->sales->id,
        'disk' => 'local',
        'path' => 'call-voice-notes/note.webm',
        'original_name' => 'note.webm',
        'mime_type' => 'audio/webm',
        'size' => 1000,
    ]);

    Livewire::test(CallVoiceTranscript::class, ['callLogId' => $call->id])
        ->assertSet('audioUrl', route('attachments.download', $attachment))
        ->assertSee('audio', false);
});

it('has no playable link when the voice note was never attached', function () {
    $call = CallLog::factory()->create(['voice_transcript_status' => VoiceTranscriptStatus::Failed]);

    Livewire::test(CallVoiceTranscript::class, ['callLogId' => $call->id])
        ->assertSet('audioUrl', null);
});

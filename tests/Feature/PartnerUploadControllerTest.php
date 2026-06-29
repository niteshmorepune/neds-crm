<?php

use App\Enums\ContentStatus;
use App\Models\ContentPiece;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

it('renders the upload form for a valid token', function () {
    $piece = ContentPiece::factory()->agencyLed()->create([
        'project_id' => Project::factory()->create()->id,
        'created_by' => User::factory()->create()->id,
        'upload_token' => (string) Str::uuid(),
        'upload_token_expires_at' => now()->addDays(7),
    ]);

    $this->get(route('partner-upload.show', $piece->upload_token))
        ->assertOk()
        ->assertSee($piece->title);
});

it('returns 404 for an expired token', function () {
    $piece = ContentPiece::factory()->agencyLed()->create([
        'project_id' => Project::factory()->create()->id,
        'created_by' => User::factory()->create()->id,
        'upload_token' => (string) Str::uuid(),
        'upload_token_expires_at' => now()->subDay(),
    ]);

    $this->get(route('partner-upload.show', $piece->upload_token))
        ->assertNotFound();
});

it('returns 404 for an unknown token', function () {
    $this->get(route('partner-upload.show', 'not-a-real-token'))
        ->assertNotFound();
});

it('partner can upload files and status advances to received', function () {
    Storage::fake('local');

    $piece = ContentPiece::factory()->agencyLed()->create([
        'project_id' => Project::factory()->create()->id,
        'created_by' => User::factory()->create()->id,
        'upload_token' => (string) Str::uuid(),
        'upload_token_expires_at' => now()->addDays(7),
    ]);

    $file = UploadedFile::fake()->image('content.jpg');

    $this->post(route('partner-upload.store', $piece->upload_token), [
        'files' => [$file],
    ])->assertRedirect();

    expect($piece->fresh()->status)->toBe(ContentStatus::Received);
    expect($piece->attachments()->count())->toBe(1);
    expect($piece->attachments()->first()->uploaded_by)->toBeNull();
});

it('upload on expired token returns 404', function () {
    Storage::fake('local');

    $piece = ContentPiece::factory()->agencyLed()->create([
        'project_id' => Project::factory()->create()->id,
        'created_by' => User::factory()->create()->id,
        'upload_token' => (string) Str::uuid(),
        'upload_token_expires_at' => now()->subHour(),
    ]);

    $this->post(route('partner-upload.store', $piece->upload_token), [
        'files' => [UploadedFile::fake()->image('file.jpg')],
    ])->assertNotFound();
});

it('upload rejects files that are too large', function () {
    Storage::fake('local');

    $piece = ContentPiece::factory()->agencyLed()->create([
        'project_id' => Project::factory()->create()->id,
        'created_by' => User::factory()->create()->id,
        'upload_token' => (string) Str::uuid(),
        'upload_token_expires_at' => now()->addDays(7),
    ]);

    // 52 MB — exceeds the 50 MB limit
    $bigFile = UploadedFile::fake()->create('huge.mp4', 52 * 1024, 'video/mp4');

    $this->post(route('partner-upload.store', $piece->upload_token), [
        'files' => [$bigFile],
    ])->assertSessionHasErrors('files.0');
});

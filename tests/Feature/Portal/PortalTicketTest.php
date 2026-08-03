<?php

use App\Enums\TicketStatus;
use App\Models\Contact;
use App\Models\Customer;
use App\Models\Ticket;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->customerA = Customer::factory()->create();
    $this->contactA = Contact::factory()->portalUser()->create(['customer_id' => $this->customerA->id, 'name' => 'Asha']);
    $this->customerB = Customer::factory()->create();
});

it('lets a portal contact raise a ticket scoped to their company with an SLA', function () {
    $this->actingAs($this->contactA, 'portal')->post(route('portal.tickets.store'), [
        'subject' => 'Login not working',
        'description' => 'I cannot sign in',
        'priority' => 'high',
    ])->assertRedirect();

    $ticket = Ticket::firstWhere('subject', 'Login not working');
    expect($ticket->customer_id)->toBe($this->customerA->id)
        ->and($ticket->status)->toBe(TicketStatus::Open)
        ->and($ticket->sla_due_at)->not->toBeNull()
        ->and($ticket->created_by)->toBeNull();
});

it('lets a portal contact attach files when raising a ticket', function () {
    Storage::fake('local');

    $this->actingAs($this->contactA, 'portal')->post(route('portal.tickets.store'), [
        'subject' => 'Logo not showing',
        'description' => 'See attached screenshot',
        'priority' => 'normal',
        'attachments' => [
            UploadedFile::fake()->create('screenshot.png', 500, 'image/png'),
            UploadedFile::fake()->create('brief.pdf', 200, 'application/pdf'),
        ],
    ])->assertRedirect();

    $ticket = Ticket::firstWhere('subject', 'Logo not showing');
    expect($ticket->attachments)->toHaveCount(2);

    $attachment = $ticket->attachments()->firstWhere('original_name', 'screenshot.png');
    expect($attachment->contact_id)->toBe($this->contactA->id)
        ->and($attachment->uploaded_by)->toBeNull()
        ->and($attachment->uploaderName())->toBe('Asha');
    Storage::disk('local')->assertExists($attachment->path);
});

it('rejects a disallowed file type when raising a ticket', function () {
    Storage::fake('local');

    $this->actingAs($this->contactA, 'portal')->post(route('portal.tickets.store'), [
        'subject' => 'Bad file',
        'description' => 'Testing validation',
        'priority' => 'normal',
        'attachments' => [UploadedFile::fake()->create('malware.exe', 100, 'application/octet-stream')],
    ])->assertSessionHasErrors('attachments.0');

    expect(Ticket::firstWhere('subject', 'Bad file'))->toBeNull();
});

it('lets a portal contact download their own ticket attachment but not another customer\'s', function () {
    Storage::fake('local');

    $ownTicket = Ticket::factory()->create(['customer_id' => $this->customerA->id]);
    $ownAttachment = $ownTicket->attachments()->create([
        'contact_id' => $this->contactA->id,
        'disk' => 'local',
        'path' => UploadedFile::fake()->create('mine.pdf', 100)->store('attachments', 'local'),
        'original_name' => 'mine.pdf',
        'mime_type' => 'application/pdf',
        'size' => 100,
    ]);

    $foreignTicket = Ticket::factory()->create(['customer_id' => $this->customerB->id]);
    $foreignAttachment = $foreignTicket->attachments()->create([
        'disk' => 'local',
        'path' => UploadedFile::fake()->create('theirs.pdf', 100)->store('attachments', 'local'),
        'original_name' => 'theirs.pdf',
        'mime_type' => 'application/pdf',
        'size' => 100,
    ]);

    $this->actingAs($this->contactA, 'portal')
        ->get(route('portal.tickets.attachments.download', [$ownTicket->id, $ownAttachment->id]))
        ->assertOk();

    $this->actingAs($this->contactA, 'portal')
        ->get(route('portal.tickets.attachments.download', [$foreignTicket->id, $foreignAttachment->id]))
        ->assertNotFound();
});

it('records a portal reply as authored by the contact', function () {
    $ticket = Ticket::factory()->create(['customer_id' => $this->customerA->id]);

    $this->actingAs($this->contactA, 'portal')
        ->post(route('portal.tickets.reply', $ticket->id), ['body' => 'Any update?'])
        ->assertRedirect();

    $reply = $ticket->replies()->firstOrFail();
    expect($reply->contact_id)->toBe($this->contactA->id)
        ->and($reply->user_id)->toBeNull()
        ->and($reply->is_internal)->toBeFalse()
        ->and($reply->authorName())->toBe('Asha')
        ->and($reply->isFromCustomer())->toBeTrue();
});

it('lists only the contact\'s own tickets', function () {
    Ticket::factory()->create(['customer_id' => $this->customerA->id, 'subject' => 'Mine ticket']);
    Ticket::factory()->create(['customer_id' => $this->customerB->id, 'subject' => 'Their ticket']);

    $this->actingAs($this->contactA, 'portal')->get(route('portal.tickets.index'))->assertOk()
        ->assertSee('Mine ticket')->assertDontSee('Their ticket');
});

it('cannot view or reply to another customer\'s ticket', function () {
    $foreign = Ticket::factory()->create(['customer_id' => $this->customerB->id]);

    $this->actingAs($this->contactA, 'portal')->get(route('portal.tickets.show', $foreign->id))->assertNotFound();
    $this->actingAs($this->contactA, 'portal')
        ->post(route('portal.tickets.reply', $foreign->id), ['body' => 'sneaky'])->assertNotFound();

    expect($foreign->replies()->count())->toBe(0);
});

it('hides internal notes from the portal thread', function () {
    $ticket = Ticket::factory()->create(['customer_id' => $this->customerA->id]);
    $ticket->replies()->create(['user_id' => null, 'body' => 'Internal: escalate', 'is_internal' => true]);
    $ticket->replies()->create(['contact_id' => $this->contactA->id, 'body' => 'Customer visible note', 'is_internal' => false]);

    $this->actingAs($this->contactA, 'portal')->get(route('portal.tickets.show', $ticket->id))->assertOk()
        ->assertSee('Customer visible note')->assertDontSee('Internal: escalate');
});

it('renders the portal ticket create page', function () {
    $this->actingAs($this->contactA, 'portal')->get(route('portal.tickets.create'))->assertOk()->assertSee('Raise a Ticket');
});

it('lets a portal contact rate a resolved ticket', function () {
    $ticket = Ticket::factory()->create(['customer_id' => $this->customerA->id, 'status' => TicketStatus::Resolved]);

    $this->actingAs($this->contactA, 'portal')
        ->post(route('portal.tickets.rate', $ticket->id), ['rating' => 4, 'comment' => 'Quick turnaround'])
        ->assertRedirect();

    $rating = $ticket->satisfactionRating()->firstOrFail();
    expect($rating->rating)->toBe(4)
        ->and($rating->comment)->toBe('Quick turnaround')
        ->and($rating->contact_id)->toBe($this->contactA->id);
});

it('forbids rating a still-open ticket', function () {
    $ticket = Ticket::factory()->create(['customer_id' => $this->customerA->id, 'status' => TicketStatus::Open]);

    $this->actingAs($this->contactA, 'portal')
        ->post(route('portal.tickets.rate', $ticket->id), ['rating' => 4])
        ->assertForbidden();
});

it('forbids rating a ticket that already has a rating', function () {
    $ticket = Ticket::factory()->create(['customer_id' => $this->customerA->id, 'status' => TicketStatus::Resolved]);
    $ticket->satisfactionRating()->create(['contact_id' => $this->contactA->id, 'rating' => 5]);

    $this->actingAs($this->contactA, 'portal')
        ->post(route('portal.tickets.rate', $ticket->id), ['rating' => 1])
        ->assertForbidden();

    expect($ticket->satisfactionRating()->count())->toBe(1);
});

it('cannot rate another customers ticket', function () {
    $foreign = Ticket::factory()->create(['customer_id' => $this->customerB->id, 'status' => TicketStatus::Resolved]);

    $this->actingAs($this->contactA, 'portal')
        ->post(route('portal.tickets.rate', $foreign->id), ['rating' => 1])
        ->assertNotFound();

    expect($foreign->satisfactionRating()->count())->toBe(0);
});

it('rejects a rating outside the 1-5 range', function () {
    $ticket = Ticket::factory()->create(['customer_id' => $this->customerA->id, 'status' => TicketStatus::Resolved]);

    $this->actingAs($this->contactA, 'portal')
        ->post(route('portal.tickets.rate', $ticket->id), ['rating' => 6])
        ->assertSessionHasErrors('rating');
});

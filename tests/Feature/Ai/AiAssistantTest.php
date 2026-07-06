<?php

use App\Models\AiUsage;
use App\Models\Customer;
use App\Models\Festival;
use App\Models\Lead;
use App\Models\Project;
use App\Models\Ticket;
use App\Models\User;
use App\Services\AiAssistant;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

function fakeAiText(string $text): void
{
    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'text', 'text' => $text]],
            'usage' => ['input_tokens' => 50, 'output_tokens' => 30],
        ]),
    ]);
}

function aiOn(): void
{
    config(['services.anthropic.enabled' => true, 'services.anthropic.key' => 'sk-test']);
}

it('returns null and makes no call when AI is disabled', function () {
    config(['services.anthropic.enabled' => false]);
    Http::fake();
    $ticket = Ticket::factory()->create();

    expect(app(AiAssistant::class)->draftTicketReply($ticket))->toBeNull();
    Http::assertNothingSent();
});

it('drafts a ticket reply and records usage under its feature', function () {
    aiOn();
    fakeAiText('Hi, we have reset your access. Please try again.');
    $ticket = Ticket::factory()->create();
    $ticket->replies()->create(['user_id' => null, 'contact_id' => null, 'body' => 'I cannot log in', 'is_internal' => false]);

    $draft = app(AiAssistant::class)->draftTicketReply($ticket->load('replies'));

    expect($draft)->toBe('Hi, we have reset your access. Please try again.');
    expect(AiUsage::where('feature', 'draft_ticket_reply')->exists())->toBeTrue();
});

it('drafts a lead follow-up message', function () {
    aiOn();
    fakeAiText('Hi Ravi, just following up on the SEO plan — shall we hop on a quick call?');
    $lead = Lead::factory()->create(['name' => 'Ravi']);

    $draft = app(AiAssistant::class)->draftLeadFollowUp($lead);

    expect($draft)->toContain('Ravi');
    expect(AiUsage::where('feature', 'draft_lead_followup')->exists())->toBeTrue();
});

it('summarizes a customer timeline', function () {
    aiOn();
    fakeAiText('- Long-time client. - One open ticket about billing. - Next: follow up on renewal.');
    $customer = Customer::factory()->create();

    $summary = app(AiAssistant::class)->summarizeCustomer($customer);

    expect($summary)->toContain('open ticket');
    expect(AiUsage::where('feature', 'summarize_customer')->exists())->toBeTrue();
});

it('drafts a festival greeting caption', function () {
    aiOn();
    fakeAiText('🎉 Wishing Acme Corp a joyful Diwali filled with light and prosperity! ✨');
    $project = Project::factory()->create();
    $festival = Festival::factory()->create(['name' => 'Diwali']);

    $draft = app(AiAssistant::class)->draftFestivalGreeting($festival, $project);

    expect($draft)->toContain('Diwali');
    expect(AiUsage::where('feature', 'draft_festival_greeting')->exists())->toBeTrue();
});

it('summarizes daily priorities for a user', function () {
    aiOn();
    fakeAiText('You have 2 overdue tasks and a call follow-up due — start with the oldest task.');
    $user = User::factory()->create();
    $empty = new Collection;

    $summary = app(AiAssistant::class)->summarizeDailyPriorities($user, $empty, $empty, $empty, $empty, $empty, $empty);

    expect($summary)->toContain('overdue');
    expect(AiUsage::where('feature', 'daily_priorities_summary')->exists())->toBeTrue();
});

it('summarizes team performance from report rows', function () {
    aiOn();
    fakeAiText('- Mohit completed the most tasks this month. - Attendance is strong across the team.');
    $rows = new Collection([
        ['user' => 'Mohit', 'role' => 'Sales', 'tasks_completed' => 12, 'on_time_pct' => 90, 'calls_made' => 30, 'leads_converted' => 3, 'attendance_pct' => 95, 'daily_reports' => 20],
    ]);

    $summary = app(AiAssistant::class)->summarizeTeamPerformance($rows, now()->startOfMonth(), now()->endOfMonth());

    expect($summary)->toContain('Mohit');
    expect(AiUsage::where('feature', 'team_performance_summary')->exists())->toBeTrue();
});

it('suggests a next action for a flagged client', function () {
    aiOn();
    fakeAiText('Give them a quick check-in call this week and mention the SEO add-on.');
    $customer = Customer::factory()->create(['company_name' => 'Acme Corp']);

    $suggestion = app(AiAssistant::class)->suggestClientAction($customer, [
        'no_contact' => ['label' => 'No Contact', 'detail' => 'Last touch 20 days ago'],
    ]);

    expect($suggestion)->toContain('check-in');
    expect(AiUsage::where('feature', 'client_radar_suggestion')->exists())->toBeTrue();
});

it('drafts a monthly wins note for a client', function () {
    aiOn();
    fakeAiText('Acme Corp, this month we wrapped up 5 tasks and kept everything running smoothly!');
    $customer = Customer::factory()->create(['company_name' => 'Acme Corp']);

    $draft = app(AiAssistant::class)->draftMonthlyWinsNote($customer, [
        'tasks_completed' => 5,
        'tickets_resolved' => 1,
        'amount_paid' => '₹50,000.00',
    ]);

    expect($draft)->toContain('Acme Corp');
    expect(AiUsage::where('feature', 'monthly_wins_note')->exists())->toBeTrue();
});

it('drafts a monthly wins note including Drishti marketing-delivery numbers', function () {
    aiOn();
    fakeAiText('Acme Corp, we published 8 posts and completed an audit for you this month!');
    $customer = Customer::factory()->create(['company_name' => 'Acme Corp']);

    $draft = app(AiAssistant::class)->draftMonthlyWinsNote($customer, [
        'tasks_completed' => 0,
        'tickets_resolved' => 0,
        'amount_paid' => '—',
        'posts_published' => 8,
        'audits_completed' => 1,
        'action_items_done' => 3,
    ]);

    expect($draft)->toContain('posts');
});

it('returns null (not an exception) when the API fails', function () {
    aiOn();
    Http::fake(['api.anthropic.com/*' => Http::response('boom', 500)]);
    $ticket = Ticket::factory()->create();

    expect(app(AiAssistant::class)->summarizeTicket($ticket))->toBeNull();
    expect(AiUsage::count())->toBe(0);
});

it('treats a blank model response as no result', function () {
    aiOn();
    fakeAiText('   ');
    $ticket = Ticket::factory()->create();

    expect(app(AiAssistant::class)->draftTicketReply($ticket))->toBeNull();
});

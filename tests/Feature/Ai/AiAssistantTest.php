<?php

use App\Enums\CrmQueryType;
use App\Enums\TicketPriority;
use App\Models\AiUsage;
use App\Models\Customer;
use App\Models\Festival;
use App\Models\Lead;
use App\Models\Project;
use App\Models\Service;
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

it('answers a portal question and records usage under its own feature', function () {
    aiOn();
    fakeAiText('You have no overdue invoices right now.');
    $customer = Customer::factory()->create(['company_name' => 'Acme Corp']);

    $answer = app(AiAssistant::class)->answerPortalQuestion($customer, 'Do I owe anything?');

    expect($answer)->toBe('You have no overdue invoices right now.');
    expect(AiUsage::where('feature', 'portal_assistant_answer')->exists())->toBeTrue();
});

it('drafts a CSAT recovery message grounded in the specific ticket', function () {
    aiOn();
    fakeAiText('I\'m sorry the SEO delay frustrated you — let\'s hop on a call this week to make it right.');
    $customer = Customer::factory()->create(['company_name' => 'Acme Corp']);
    $ticket = Ticket::factory()->for($customer)->create(['subject' => 'SEO report late']);
    $ticket->satisfactionRating()->create(['rating' => 1, 'comment' => 'Report was a week late']);

    $draft = app(AiAssistant::class)->draftCsatRecoveryMessage($ticket->load('satisfactionRating'));

    expect($draft)->toContain('call');
    expect(AiUsage::where('feature', 'csat_recovery_message')->exists())->toBeTrue();
});

it('suggests ticket triage matched to one of the client\'s real active services', function () {
    aiOn();
    $seo = Service::factory()->create(['name' => 'SEO']);
    $customer = Customer::factory()->create();
    Project::factory()->create(['customer_id' => $customer->id, 'service_id' => $seo->id]);
    fakeAiText('{"priority": "high", "service": "SEO", "reason": "Rankings dropped, time-sensitive."}');

    $suggestion = app(AiAssistant::class)->suggestTicketTriage($customer, 'Rankings dropped', 'Our keywords fell off page 1 overnight.');

    expect($suggestion)->not->toBeNull()
        ->and($suggestion['priority'])->toBe(TicketPriority::High)
        ->and($suggestion['service_id'])->toBe($seo->id)
        ->and($suggestion['service_name'])->toBe('SEO')
        ->and($suggestion['reason'])->toContain('Rankings');
});

it('ignores a hallucinated service name that is not in the client\'s actual active services', function () {
    aiOn();
    $seo = Service::factory()->create(['name' => 'SEO']);
    $customer = Customer::factory()->create();
    Project::factory()->create(['customer_id' => $customer->id, 'service_id' => $seo->id]);
    fakeAiText('{"priority": "normal", "service": "Software Development", "reason": "Unclear."}');

    $suggestion = app(AiAssistant::class)->suggestTicketTriage($customer, 'A ticket', 'Something unrelated.');

    expect($suggestion['service_id'])->toBeNull()
        ->and($suggestion['service_name'])->toBeNull();
});

it('returns null for ticket triage when the client has no active projects to route to', function () {
    aiOn();
    $customer = Customer::factory()->create();

    expect(app(AiAssistant::class)->suggestTicketTriage($customer, 'A ticket', 'Description'))->toBeNull();
    Http::assertNothingSent();
});

it('returns null for ticket triage when the model reply has no usable priority', function () {
    aiOn();
    $seo = Service::factory()->create(['name' => 'SEO']);
    $customer = Customer::factory()->create();
    Project::factory()->create(['customer_id' => $customer->id, 'service_id' => $seo->id]);
    fakeAiText('{"priority": "critical", "service": "SEO", "reason": "Bad enum value."}');

    expect(app(AiAssistant::class)->suggestTicketTriage($customer, 'A ticket', 'Description'))->toBeNull();
});

it('classifies a CRM question into one of the known query types', function () {
    aiOn();
    fakeAiText('{"query_type": "client_radar"}');

    expect(app(AiAssistant::class)->classifyCrmQuestion('Which clients are at risk this month?'))
        ->toBe(CrmQueryType::ClientRadar);
});

it('returns null when the CRM question is unsupported or unclassifiable', function () {
    aiOn();
    fakeAiText('{"query_type": "unsupported"}');

    expect(app(AiAssistant::class)->classifyCrmQuestion('What is the meaning of life?'))->toBeNull();
});

it('returns null when the CRM classifier replies with a made-up query type', function () {
    aiOn();
    fakeAiText('{"query_type": "employee_salaries"}');

    expect(app(AiAssistant::class)->classifyCrmQuestion('How much do we pay staff?'))->toBeNull();
});

it('narrates a CRM answer grounded only in the given figures', function () {
    aiOn();
    fakeAiText('Two clients need a check-in: Acme (overdue invoice) and Beta (no contact).');

    $answer = app(AiAssistant::class)->narrateCrmAnswer(
        'Which clients are at risk?',
        CrmQueryType::ClientRadar,
        [
            ['label' => 'Clients flagged', 'value' => '2'],
            ['label' => 'Acme', 'value' => 'Overdue Invoice'],
            ['label' => 'Beta', 'value' => 'No Contact'],
        ]
    );

    expect($answer)->toContain('Acme')->toContain('Beta');
    expect(AiUsage::where('feature', 'crm_query_answer')->exists())->toBeTrue();
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

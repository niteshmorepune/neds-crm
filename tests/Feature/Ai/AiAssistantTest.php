<?php

use App\Enums\CrmQueryType;
use App\Enums\TicketPriority;
use App\Models\AiUsage;
use App\Models\Customer;
use App\Models\Deal;
use App\Models\Festival;
use App\Models\Lead;
use App\Models\Project;
use App\Models\Quotation;
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

it('exposes the ai_usages row id of the call it just made, for later feedback', function () {
    aiOn();
    fakeAiText('Hi, we have reset your access. Please try again.');
    $ticket = Ticket::factory()->create();

    $assistant = app(AiAssistant::class);
    expect($assistant->lastUsageId)->toBeNull();

    $assistant->draftTicketReply($ticket->load('replies'));

    $usage = AiUsage::where('feature', 'draft_ticket_reply')->firstOrFail();
    expect($assistant->lastUsageId)->toBe($usage->id);
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
        ['user_id' => 1, 'user' => 'Mohit', 'role' => 'Sales', 'tasks_completed' => 12, 'on_time_pct' => 90, 'calls_made' => 30, 'leads_converted' => 3, 'attendance_pct' => 95, 'daily_reports' => 20],
    ]);

    $summary = app(AiAssistant::class)->summarizeTeamPerformance($rows, now()->startOfMonth(), now()->endOfMonth());

    expect($summary)->toContain('Mohit');
    expect(AiUsage::where('feature', 'team_performance_summary')->exists())->toBeTrue();
});

it('enriches a rep\'s line with stage-dwell figures when given, keyed by user_id', function () {
    aiOn();
    fakeAiText('- Priya stalls longest in Negotiation, averaging 18 days against the team\'s 9.');
    $rows = new Collection([
        ['user_id' => 42, 'user' => 'Priya', 'role' => 'Sales', 'tasks_completed' => 5, 'on_time_pct' => 80, 'calls_made' => 10, 'leads_converted' => 1, 'attendance_pct' => 90, 'daily_reports' => 15],
        ['user_id' => 43, 'user' => 'Rahul', 'role' => 'Sales', 'tasks_completed' => 8, 'on_time_pct' => 85, 'calls_made' => 12, 'leads_converted' => 2, 'attendance_pct' => 92, 'daily_reports' => 18],
    ]);
    $dwellTimes = [
        42 => ['negotiation' => ['rep_avg_days' => 18.0, 'rep_sample' => 4, 'team_avg_days' => 9.0, 'team_sample' => 10]],
    ];

    app(AiAssistant::class)->summarizeTeamPerformance($rows, now()->startOfMonth(), now()->endOfMonth(), $dwellTimes);

    Http::assertSent(function ($request) {
        $body = $request->body();

        // Priya's line carries the dwell figures; Rahul's (no entry in $dwellTimes) does not.
        return str_contains($body, 'Negotiation stage')
            && str_contains($body, '18')
            && str_contains($body, 'team average 9');
    });
});

it('summarizes a weekly owner digest from pre-formatted figure lines', function () {
    aiOn();
    fakeAiText('Pipeline is healthy this week, but 2 clients are flagged for low satisfaction and deserve a check-in.');
    $lines = [
        'Open pipeline: 5 deals worth ₹5,00,000.00',
        'Clients flagged by Client Radar: 2',
        'Clients with a low-satisfaction flag: 2',
    ];

    $summary = app(AiAssistant::class)->summarizeWeeklyOwnerDigest($lines);

    expect($summary)->toContain('low satisfaction');
    expect(AiUsage::where('feature', 'weekly_owner_digest')->exists())->toBeTrue();
});

it('suggests onboarding tasks grounded in deal notes and quotation line items', function () {
    aiOn();
    fakeAiText('[{"title": "Set up Hindi translation workflow", "description": "Client requested a Hindi version of the site.", "due_in_days": 10}]');
    $deal = Deal::factory()->create();
    $deal->notes()->create(['user_id' => User::factory()->create()->id, 'body' => 'Client wants a Hindi translation of the whole site.']);
    $project = Project::factory()->create(['deal_id' => $deal->id]);

    $result = app(AiAssistant::class)->suggestOnboardingTasks($project);

    expect($result)->toHaveCount(1)
        ->and($result[0]['title'])->toBe('Set up Hindi translation workflow')
        ->and($result[0]['due_in_days'])->toBe(10);
    expect(AiUsage::where('feature', 'onboarding_task_suggestion')->exists())->toBeTrue();
});

it('skips the AI call entirely and returns an empty array when there is nothing deal-specific to work from', function () {
    aiOn();
    Http::fake();
    $project = Project::factory()->create(['deal_id' => null]);

    $result = app(AiAssistant::class)->suggestOnboardingTasks($project);

    expect($result)->toBe([]);
    Http::assertNothingSent();
});

it('clamps an out-of-range due_in_days and drops a suggestion with no title', function () {
    aiOn();
    fakeAiText('[{"title": "Valid task", "description": "x", "due_in_days": 999}, {"description": "no title here"}]');
    $deal = Deal::factory()->create();
    $deal->notes()->create(['user_id' => User::factory()->create()->id, 'body' => 'Some specific requirement.']);
    $project = Project::factory()->create(['deal_id' => $deal->id]);

    $result = app(AiAssistant::class)->suggestOnboardingTasks($project);

    expect($result)->toHaveCount(1)
        ->and($result[0]['title'])->toBe('Valid task')
        ->and($result[0]['due_in_days'])->toBe(60);
});

it('returns null for onboarding task suggestions when AI is disabled', function () {
    config(['services.anthropic.enabled' => false]);
    Http::fake();
    $project = Project::factory()->create();

    expect(app(AiAssistant::class)->suggestOnboardingTasks($project))->toBeNull();
    Http::assertNothingSent();
});

it('suggests quotation line items grounded in deal notes, matching a known SAC code exactly', function () {
    aiOn();
    Quotation::factory()->create()->items()->create([
        'description' => 'Existing item', 'sac_code' => '998361', 'quantity' => 1, 'rate' => 100000, 'gst_rate' => 18, 'amount' => 100000,
    ]);
    fakeAiText('[{"description": "Hindi translation setup", "quantity": 1, "sac_code": "998361"}]');
    $deal = Deal::factory()->create();
    $deal->notes()->create(['user_id' => User::factory()->create()->id, 'body' => 'Client wants a Hindi translation of the whole site.']);

    $result = app(AiAssistant::class)->suggestQuotationLineItems($deal);

    expect($result)->toHaveCount(1)
        ->and($result[0]['description'])->toBe('Hindi translation setup')
        ->and($result[0]['sac_code'])->toBe('998361')
        ->and($result[0])->not->toHaveKey('rate')
        ->and($result[0])->not->toHaveKey('gst_rate');
    expect(AiUsage::where('feature', 'quotation_line_item_suggestion')->exists())->toBeTrue();
});

it('discards a SAC code the team has never actually used, even if the model returns one', function () {
    aiOn();
    // No QuotationItem with any sac_code exists at all — the whitelist is empty.
    fakeAiText('[{"description": "Hindi translation setup", "quantity": 1, "sac_code": "998399"}]');
    $deal = Deal::factory()->create();
    $deal->notes()->create(['user_id' => User::factory()->create()->id, 'body' => 'Client wants a Hindi translation.']);

    $result = app(AiAssistant::class)->suggestQuotationLineItems($deal);

    expect($result[0]['sac_code'])->toBeNull();
});

it('skips the AI call entirely and returns an empty array when the deal has no notes', function () {
    aiOn();
    Http::fake();
    $deal = Deal::factory()->create();

    $result = app(AiAssistant::class)->suggestQuotationLineItems($deal);

    expect($result)->toBe([]);
    Http::assertNothingSent();
});

it('returns null for quotation line item suggestions when AI is disabled', function () {
    config(['services.anthropic.enabled' => false]);
    Http::fake();
    $deal = Deal::factory()->create();

    expect(app(AiAssistant::class)->suggestQuotationLineItems($deal))->toBeNull();
    Http::assertNothingSent();
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

    $assistant = app(AiAssistant::class);
    $suggestion = $assistant->suggestTicketTriage($customer, 'Rankings dropped', 'Our keywords fell off page 1 overnight.');

    expect($suggestion)->not->toBeNull()
        ->and($suggestion['priority'])->toBe(TicketPriority::High)
        ->and($suggestion['service_id'])->toBe($seo->id)
        ->and($suggestion['service_name'])->toBe('SEO')
        ->and($suggestion['reason'])->toContain('Rankings');

    $usage = AiUsage::where('feature', 'ticket_triage_suggestion')->firstOrFail();
    expect($assistant->lastUsageId)->toBe($usage->id);
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

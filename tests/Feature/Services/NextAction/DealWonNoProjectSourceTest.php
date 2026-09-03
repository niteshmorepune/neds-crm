<?php

use App\Enums\DealStage;
use App\Enums\ProjectStatus;
use App\Enums\UserRole;
use App\Models\Deal;
use App\Models\NextActionSnooze;
use App\Models\Project;
use App\Models\User;
use App\Services\NextAction\DealWonNoProjectSource;
use Symfony\Component\HttpKernel\Exception\HttpException;

function dealWonNoProjectSource(): DealWonNoProjectSource
{
    return app(DealWonNoProjectSource::class);
}

it('returns null for a non-Sales user', function () {
    $support = User::factory()->role(UserRole::Support)->create();
    Deal::factory()->create(['owner_id' => $support->id, 'stage' => DealStage::Won, 'won_at' => now()]);

    expect(dealWonNoProjectSource()->next($support))->toBeNull();
});

it('prompts the deal owner to start the project once a deal is Won with no project yet', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();
    $deal = Deal::factory()->create(['owner_id' => $sales->id, 'stage' => DealStage::Won, 'won_at' => now(), 'title' => 'ADTA Group Website']);

    $action = dealWonNoProjectSource()->next($sales);

    expect($action)->not->toBeNull();
    expect($action->subjectId)->toBe($deal->id);
    expect($action->title)->toBe('Start the project for ADTA Group Website');
    expect($action->actionUrl)->toBeNull();
    expect($action->actionLabel)->toBe('Create project now');
});

it('does not prompt once the deal already has a project', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();
    $deal = Deal::factory()->create(['owner_id' => $sales->id, 'stage' => DealStage::Won, 'won_at' => now()]);
    Project::factory()->create(['deal_id' => $deal->id, 'customer_id' => $deal->customer_id, 'owner_id' => $sales->id, 'status' => ProjectStatus::Active]);

    expect(dealWonNoProjectSource()->next($sales))->toBeNull();
});

it('does not prompt a deal still open (not Won)', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();
    Deal::factory()->create(['owner_id' => $sales->id, 'stage' => DealStage::Negotiation]);

    expect(dealWonNoProjectSource()->next($sales))->toBeNull();
});

it("does not surface another rep's won deal", function () {
    $sales = User::factory()->role(UserRole::Sales)->create();
    $other = User::factory()->role(UserRole::Sales)->create();
    Deal::factory()->create(['owner_id' => $other->id, 'stage' => DealStage::Won, 'won_at' => now()]);

    expect(dealWonNoProjectSource()->next($sales))->toBeNull();
});

it('excludes a snoozed deal but includes it again once the snooze expires', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();
    $deal = Deal::factory()->create(['owner_id' => $sales->id, 'stage' => DealStage::Won, 'won_at' => now()]);

    NextActionSnooze::create([
        'user_id' => $sales->id,
        'source_key' => 'deal_won_no_project',
        'subject_type' => Deal::class,
        'subject_id' => $deal->id,
        'snoozed_until' => now()->addMinutes(30),
    ]);

    expect(dealWonNoProjectSource()->next($sales))->toBeNull();

    NextActionSnooze::query()->update(['snoozed_until' => now()->subMinute()]);

    expect(dealWonNoProjectSource()->next($sales)?->subjectId)->toBe($deal->id);
});

it('complete() creates the project via CreateProjectFromDeal', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();
    $deal = Deal::factory()->create(['owner_id' => $sales->id, 'stage' => DealStage::Won, 'won_at' => now()]);

    dealWonNoProjectSource()->complete($sales, $deal->id);

    expect(Project::where('deal_id', $deal->id)->exists())->toBeTrue();
    expect(dealWonNoProjectSource()->next($sales))->toBeNull();
});

it("refuses to create a project for another rep's deal", function () {
    $sales = User::factory()->role(UserRole::Sales)->create();
    $other = User::factory()->role(UserRole::Sales)->create();
    $deal = Deal::factory()->create(['owner_id' => $other->id, 'stage' => DealStage::Won, 'won_at' => now()]);

    dealWonNoProjectSource()->complete($sales, $deal->id);
})->throws(HttpException::class);

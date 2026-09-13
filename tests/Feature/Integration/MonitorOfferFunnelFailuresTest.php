<?php

use App\Enums\UserRole;
use App\Models\SystemAlert;
use App\Models\User;
use App\Notifications\OfferFunnelFailureSpikeNotification;
use App\Support\LogFailureScanner;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;

/**
 * Mocks LogFailureScanner::countSince() directly rather than exercising the
 * real file-reading implementation (that's LogFailureScannerTest's own job)
 * -- lets each test fabricate an exact synthetic failure count for one
 * specific needle deterministically, with the other (non-targeted) needle
 * always returning a harmless zero so only the check under test can fire.
 */
function bindScannerWith(string $needle, int $count, array $reasons = []): void
{
    $scanner = Mockery::mock(LogFailureScanner::class);
    $scanner->shouldReceive('countSince')
        ->withArgs(fn (string $arg, $since) => $arg === $needle)
        ->andReturn(['count' => $count, 'reasons' => $reasons]);
    $scanner->shouldReceive('countSince')
        ->withArgs(fn (string $arg, $since) => $arg !== $needle)
        ->andReturn(['count' => 0, 'reasons' => []]);

    app()->instance(LogFailureScanner::class, $scanner);
}

beforeEach(function () {
    Notification::fake();
});

it('alerts Admin/Manager exactly once when send failures spike past the threshold', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);
    bindScannerWith('SendOfferRecommendationReadyJob: wadesk.in returned non-2xx', 6, ['Template not found or not approved' => 6]);

    Artisan::call('app:monitor-offer-funnel-failures');

    Notification::assertSentTo($admin, OfferFunnelFailureSpikeNotification::class, fn ($n) => str_contains($n->summary, '6') && str_contains($n->summary, 'wadesk.in returned non-2xx'));
    expect(SystemAlert::where('alert_key', 'offer_recommendation_send_failure_spike')->exists())->toBeTrue();
});

it('does not alert on a sub-threshold count', function () {
    User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);
    bindScannerWith('SendOfferRecommendationReadyJob: wadesk.in returned non-2xx', 4);

    Artisan::call('app:monitor-offer-funnel-failures');

    Notification::assertNothingSent();
    expect(SystemAlert::count())->toBe(0);
});

it('does not double-alert for a second spike within the cooldown window', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);
    SystemAlert::create(['alert_key' => 'offer_recommendation_send_failure_spike', 'last_alerted_at' => now()->subHours(2)]);
    bindScannerWith('SendOfferRecommendationReadyJob: wadesk.in returned non-2xx', 10);

    Artisan::call('app:monitor-offer-funnel-failures');

    Notification::assertNothingSentTo($admin);
});

it('alerts again once the cooldown window has passed', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);
    SystemAlert::create(['alert_key' => 'offer_recommendation_send_failure_spike', 'last_alerted_at' => now()->subHours(7)]);
    bindScannerWith('SendOfferRecommendationReadyJob: wadesk.in returned non-2xx', 10);

    Artisan::call('app:monitor-offer-funnel-failures');

    Notification::assertSentTo($admin, OfferFunnelFailureSpikeNotification::class);
});

it('alerts on any single Razorpay order-creation exception -- lower threshold than the send-failure check', function () {
    $manager = User::factory()->create(['role' => UserRole::Manager, 'is_active' => true]);
    bindScannerWith('Razorpay order creation exception', 1, ['cURL error 28: Connection timed out' => 1]);

    Artisan::call('app:monitor-offer-funnel-failures');

    Notification::assertSentTo($manager, OfferFunnelFailureSpikeNotification::class, fn ($n) => str_contains($n->summary, 'Razorpay') && str_contains($n->summary, 'not urgent'));
});

it('does not notify an inactive Admin/Manager or a Sales/Support user', function () {
    $inactive = User::factory()->create(['role' => UserRole::Admin, 'is_active' => false]);
    $sales = User::factory()->create(['role' => UserRole::Sales, 'is_active' => true]);
    bindScannerWith('SendOfferRecommendationReadyJob: wadesk.in returned non-2xx', 6);

    Artisan::call('app:monitor-offer-funnel-failures');

    Notification::assertNothingSentTo($inactive);
    Notification::assertNothingSentTo($sales);
});

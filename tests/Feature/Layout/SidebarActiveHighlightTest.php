<?php

use App\Enums\UserRole;
use App\Models\Lead;
use App\Models\MenuItem;
use App\Models\User;
use Database\Seeders\MenuItemsSeeder;

beforeEach(function () {
    $this->seed(MenuItemsSeeder::class);
});

// --- MenuItem::activePatterns() -------------------------------------------

it('matches only the exact route when there is no dot-namespace to widen', function () {
    $item = new MenuItem(['route' => 'dashboard']);

    expect($item->activePatterns())->toBe(['dashboard']);
});

it('also matches sub-pages under the same first-segment namespace', function () {
    $item = new MenuItem(['route' => 'leads.index']);

    expect($item->activePatterns())->toBe(['leads.index', 'leads.*']);
});

it('does not widen a route living under the shared reports namespace, to avoid cross-highlighting a sibling report page', function () {
    $receivables = new MenuItem(['route' => 'reports.receivables']);
    $collections = new MenuItem(['route' => 'reports.collections']);

    expect($receivables->activePatterns())->toBe(['reports.receivables'])
        ->and($collections->activePatterns())->toBe(['reports.collections']);
});

// --- Rendered sidebar -------------------------------------------------------

/**
 * Sidebar nav links (not other buttons/links elsewhere on the page that
 * might happen to share an href) carry this exact class combination from
 * layouts/sidebar.blade.php's @class(...) block.
 */
function activeAnchorsFor(string $html, string $url): array
{
    preg_match_all('/<a href="'.preg_quote($url, '/').'"[^>]*>/', $html, $matches);

    return array_values(array_filter(
        $matches[0],
        fn ($tag) => str_contains($tag, 'rounded-md text-sm font-medium transition-colors'),
    ));
}

/**
 * True active state is the unprefixed "bg-gray-800" class. The inactive
 * state's hover variant ("hover:bg-gray-800") contains the same substring,
 * so a plain str_contains() can't tell them apart — this requires the token
 * to be its own space-delimited class, not part of "hover:bg-gray-800".
 */
function anchorIsActive(string $anchor): bool
{
    return (bool) preg_match('/(^|\s)bg-gray-800(\s|$)/', $anchor);
}

it('highlights the parent sidebar item on a detail page whose route name differs from the stored index route', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();
    $lead = Lead::factory()->create();

    $html = $this->actingAs($sales)->get(route('leads.show', $lead))->assertOk()->getContent();

    $anchors = activeAnchorsFor($html, route('leads.index'));
    expect($anchors)->not->toBeEmpty();
    foreach ($anchors as $anchor) {
        expect(anchorIsActive($anchor))->toBeTrue();
    }
});

it('does not cross-highlight a sibling item that shares the reports namespace', function () {
    $accounts = User::factory()->role(UserRole::Accounts)->create();

    $html = $this->actingAs($accounts)->get(route('reports.receivables'))->assertOk()->getContent();

    $accountAnchors = activeAnchorsFor($html, route('reports.receivables'));
    $collectionsAnchors = activeAnchorsFor($html, route('reports.collections'));

    expect($accountAnchors)->not->toBeEmpty();
    foreach ($accountAnchors as $anchor) {
        expect(anchorIsActive($anchor))->toBeTrue();
    }
    foreach ($collectionsAnchors as $anchor) {
        expect(anchorIsActive($anchor))->toBeFalse();
    }
});

// --- Grouped sections ---------------------------------------------------

it('renders sidebar items under their assigned group heading', function () {
    $admin = User::factory()->role(UserRole::Admin)->create();

    $this->actingAs($admin)->get(route('dashboard'))
        ->assertOk()
        ->assertSee('My Work')
        ->assertSee('Sales & Pipeline')
        ->assertSee('Finance')
        ->assertSee('Delivery & Support')
        ->assertSee('Team & Insights')
        ->assertSee('Admin & Config');
});

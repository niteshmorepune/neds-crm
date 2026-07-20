<?php

use App\Enums\AnnouncementAudience;
use App\Enums\UserRole;
use App\Models\Announcement;
use App\Models\Contact;
use App\Models\Customer;
use App\Models\User;
use Database\Seeders\MenuItemsSeeder;

beforeEach(function () {
    $this->seed(MenuItemsSeeder::class);
});

// --- Staff Dashboard ---------------------------------------------------------

it('shows an active Staff-audience announcement as a banner on the Dashboard', function () {
    $user = User::factory()->role(UserRole::Sales)->create();
    Announcement::factory()->create([
        'title' => 'Office Closed Tomorrow',
        'audience' => AnnouncementAudience::Staff->value,
        'starts_at' => now()->subHour(),
        'ends_at' => now()->addDay(),
    ]);

    $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertSee('Office Closed Tomorrow');
});

it('shows a Both-audience announcement on the Dashboard too', function () {
    $user = User::factory()->role(UserRole::Sales)->create();
    Announcement::factory()->create([
        'title' => 'Company Update',
        'audience' => AnnouncementAudience::Both->value,
        'starts_at' => now()->subHour(),
    ]);

    $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertSee('Company Update');
});

it('hides a Clients-only announcement from the staff Dashboard', function () {
    $user = User::factory()->role(UserRole::Sales)->create();
    Announcement::factory()->create([
        'title' => 'Client Only Notice',
        'audience' => AnnouncementAudience::Clients->value,
        'starts_at' => now()->subHour(),
    ]);

    $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertDontSee('Client Only Notice');
});

it('hides an expired or not-yet-started announcement from the Dashboard', function () {
    $user = User::factory()->role(UserRole::Sales)->create();
    Announcement::factory()->create([
        'title' => 'Expired Notice',
        'audience' => AnnouncementAudience::Staff->value,
        'starts_at' => now()->subDays(5),
        'ends_at' => now()->subDay(),
    ]);
    Announcement::factory()->create([
        'title' => 'Future Notice',
        'audience' => AnnouncementAudience::Staff->value,
        'starts_at' => now()->addDay(),
    ]);

    $this->actingAs($user)->get(route('dashboard'))->assertOk()
        ->assertDontSee('Expired Notice')->assertDontSee('Future Notice');
});

// --- Client Portal ------------------------------------------------------------

it('shows an active Clients-audience announcement on the Portal home page', function () {
    $customer = Customer::factory()->create();
    $contact = Contact::factory()->portalUser()->create(['customer_id' => $customer->id]);
    Announcement::factory()->create([
        'title' => 'Holiday Notice For Clients',
        'audience' => AnnouncementAudience::Clients->value,
        'starts_at' => now()->subHour(),
    ]);

    $this->actingAs($contact, 'portal')->get(route('portal.home'))->assertOk()->assertSee('Holiday Notice For Clients');
});

it('hides a Staff-only announcement from the Portal home page', function () {
    $customer = Customer::factory()->create();
    $contact = Contact::factory()->portalUser()->create(['customer_id' => $customer->id]);
    Announcement::factory()->create([
        'title' => 'Internal Only Notice',
        'audience' => AnnouncementAudience::Staff->value,
        'starts_at' => now()->subHour(),
    ]);

    $this->actingAs($contact, 'portal')->get(route('portal.home'))->assertOk()->assertDontSee('Internal Only Notice');
});

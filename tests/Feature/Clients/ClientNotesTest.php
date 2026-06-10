<?php

use App\Enums\UserRole;
use App\Livewire\ClientNotes;
use App\Models\Customer;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->role(UserRole::Manager)->create();
    $this->customer = Customer::factory()->create();
});

it('adds a note attributed to the author', function () {
    Livewire::actingAs($this->user)
        ->test(ClientNotes::class, ['customer' => $this->customer, 'canManage' => true])
        ->set('body', 'Called @asha about the renewal.')
        ->call('addNote')
        ->assertHasNoErrors()
        ->assertSet('body', '');

    $note = $this->customer->notes()->first();

    expect($note)->not->toBeNull()
        ->and($note->body)->toBe('Called @asha about the renewal.')
        ->and($note->user_id)->toBe($this->user->id);
});

it('requires a note body', function () {
    Livewire::actingAs($this->user)
        ->test(ClientNotes::class, ['customer' => $this->customer, 'canManage' => true])
        ->set('body', '')
        ->call('addNote')
        ->assertHasErrors(['body' => 'required']);
});

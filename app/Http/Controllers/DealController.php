<?php

namespace App\Http\Controllers;

use App\Enums\DealStage;
use App\Http\Requests\DealUpdateRequest;
use App\Models\Deal;
use App\Models\Service;
use App\Models\User;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class DealController extends Controller
{
    public function show(Deal $deal): View
    {
        $this->authorize('view', $deal);

        $deal->load(['customer', 'service', 'owner', 'lead']);

        return view('deals.show', [
            'deal' => $deal,
            'canManage' => $this->user()->can('update', $deal),
            'stages' => DealStage::cases(),
            'services' => Service::active()->orderBy('sort_order')->get(),
            'owners' => User::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(DealUpdateRequest $request, Deal $deal): RedirectResponse
    {
        $this->authorize('update', $deal);

        $data = $request->validated();

        // Enforce the terminal-stage rule (Won/Lost can't change stage).
        if ($deal->stage->isTerminal() && $data['stage'] !== $deal->stage->value) {
            return back()->withErrors(['stage' => 'Won or Lost deals cannot change stage.']);
        }

        $deal->update([
            'title' => $data['title'],
            'stage' => $data['stage'],
            'service_id' => $data['service_id'] ?? null,
            'owner_id' => $data['owner_id'] ?? null,
            'value' => Money::toPaise($data['value'] ?? null) ?? 0,
            'next_follow_up_at' => $data['next_follow_up_at'] ?? null,
        ]);

        return redirect()->route('deals.show', $deal)->with('status', 'Deal updated.');
    }

    public function destroy(Deal $deal): RedirectResponse
    {
        $this->authorize('delete', $deal);

        $deal->delete();

        return redirect()->route('deals.index')->with('status', 'Deal deleted.');
    }

    private function user(): User
    {
        return auth()->user();
    }
}

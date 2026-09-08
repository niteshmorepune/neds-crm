<?php

namespace App\Http\Controllers;

use App\Enums\DealStage;
use App\Enums\StallReason;
use App\Http\Requests\DealUpdateRequest;
use App\Models\Deal;
use App\Models\Partner;
use App\Models\Quotation;
use App\Models\Service;
use App\Models\User;
use App\Services\SimilarDealFinder;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DealController extends Controller
{
    public function show(Deal $deal, SimilarDealFinder $similarDeals): View
    {
        $this->authorize('view', $deal);

        $deal->load(['customer', 'service', 'owner', 'lead', 'partner', 'quotations']);

        return view('deals.show', [
            'deal' => $deal,
            'canManage' => $this->user()->can('update', $deal),
            'canCreateQuotation' => $this->user()->can('create', Quotation::class),
            'stages' => DealStage::cases(),
            'services' => Service::active()->orderBy('sort_order')->get(),
            'owners' => User::query()->orderBy('name')->get(['id', 'name']),
            'partners' => Partner::orderBy('name')->get(['id', 'name']),
            'similarDeals' => $similarDeals->find($deal),
            'stallReasons' => StallReason::cases(),
        ]);
    }

    /**
     * Quick inline tag from the deal's own page — why a deal with real
     * conversation history isn't moving forward. Phase 1 of the
     * closure-guidance plan (2026-09-08, see CLAUDE.md); mirrors
     * LeadController::updateStallReason() exactly.
     */
    public function updateStallReason(Request $request, Deal $deal): RedirectResponse
    {
        $this->authorize('update', $deal);

        $data = $request->validate([
            'stall_reason' => ['nullable', Rule::enum(StallReason::class)],
        ]);

        $deal->update(['stall_reason' => $data['stall_reason'] ?? null]);

        return back()->with('status', $data['stall_reason'] ? 'Stall reason saved.' : 'Stall reason cleared.');
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
            'lost_reason' => $data['stage'] === DealStage::Lost->value ? $data['lost_reason'] : null,
            'service_id' => $data['service_id'] ?? null,
            'owner_id' => $data['owner_id'] ?? null,
            'partner_id' => $data['partner_id'] ?? null,
            'value' => Money::toPaise($data['value']),
            'confidence' => $data['confidence'] ?? null,
            'next_follow_up_at' => filled($data['next_follow_up_at'] ?? null)
                ? Carbon::createFromFormat('Y-m-d\TH:i', $data['next_follow_up_at'], config('app.display_timezone', 'Asia/Kolkata'))->utc()
                : null,
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

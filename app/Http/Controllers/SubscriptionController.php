<?php

namespace App\Http\Controllers;

use App\Http\Requests\SubscriptionRequest;
use App\Models\Subscription;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Admin-only tracker for internal tool/vendor subscriptions (Claude, hosting,
 * domains, ...) so the owner gets reminded before they renew, instead of it
 * being tribal knowledge.
 */
class SubscriptionController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', Subscription::class);

        return view('subscriptions.index', [
            'subscriptions' => Subscription::orderBy('renewal_date')->get(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Subscription::class);

        return view('subscriptions.create');
    }

    public function store(SubscriptionRequest $request): RedirectResponse
    {
        $this->authorize('create', Subscription::class);

        Subscription::create($request->validatedWithPaise());

        return redirect()->route('subscriptions.index')->with('status', 'Subscription added.');
    }

    public function edit(Subscription $subscription): View
    {
        $this->authorize('update', $subscription);

        return view('subscriptions.edit', compact('subscription'));
    }

    public function update(SubscriptionRequest $request, Subscription $subscription): RedirectResponse
    {
        $this->authorize('update', $subscription);

        $subscription->update($request->validatedWithPaise());

        return redirect()->route('subscriptions.index')->with('status', 'Subscription updated.');
    }

    public function destroy(Subscription $subscription): RedirectResponse
    {
        $this->authorize('delete', $subscription);

        $subscription->delete();

        return redirect()->route('subscriptions.index')->with('status', 'Subscription removed.');
    }
}

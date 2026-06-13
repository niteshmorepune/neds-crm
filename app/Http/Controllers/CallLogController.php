<?php

namespace App\Http\Controllers;

use App\Enums\CallDirection;
use App\Enums\CallOutcome;
use App\Enums\UserRole;
use App\Http\Requests\CallLogStoreRequest;
use App\Models\CallLog;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class CallLogController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', CallLog::class);

        $user = $request->user();
        $isManager = $user->hasRole(UserRole::Admin, UserRole::Manager);

        $calls = CallLog::query()
            ->with(['user', 'callable'])
            ->unless($isManager, fn ($q) => $q->where('user_id', $user->id))
            ->when($isManager && $request->filled('user_id'), fn ($q) => $q->where('user_id', $request->integer('user_id')))
            ->when($request->filled('outcome'), fn ($q) => $q->where('outcome', $request->input('outcome')))
            ->when($request->filled('date'), fn ($q) => $q->whereDate('called_at', $request->date('date')))
            ->when($request->boolean('pending_followup'), fn ($q) => $q->whereNotNull('follow_up_at')->whereNull('follow_up_notified_at'))
            ->latest('called_at')
            ->paginate(20)
            ->withQueryString();

        return view('calls.index', [
            'calls' => $calls,
            'staff' => $isManager ? User::orderBy('name')->get(['id', 'name']) : collect(),
            'outcomes' => CallOutcome::cases(),
            'filters' => $request->only(['user_id', 'outcome', 'date', 'pending_followup']),
            'isManager' => $isManager,
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', CallLog::class);

        return view('calls.create', [
            'directions' => CallDirection::cases(),
            'outcomes' => CallOutcome::cases(),
            'customers' => Customer::orderBy('company_name')->get(['id', 'company_name']),
            'leads' => Lead::orderBy('name')->get(['id', 'name']),
            'selectedCustomer' => $request->integer('customer_id') ?: null,
            'selectedLead' => $request->integer('lead_id') ?: null,
        ]);
    }

    public function store(CallLogStoreRequest $request): RedirectResponse
    {
        $this->authorize('create', CallLog::class);

        $data = $request->validated();

        [$type, $id] = match (true) {
            ! empty($data['customer_id']) => [Customer::class, $data['customer_id']],
            ! empty($data['lead_id']) => [Lead::class, $data['lead_id']],
            default => [null, null],
        };

        $tz = config('app.display_timezone');

        $call = CallLog::create([
            'user_id' => $request->user()->id,
            'callable_type' => $type,
            'callable_id' => $id,
            'direction' => $data['direction'],
            'outcome' => $data['outcome'],
            'duration_minutes' => $data['duration_minutes'] ?? null,
            'notes' => $data['notes'] ?? null,
            'called_at' => Carbon::parse($data['called_at'], $tz)->utc(),
            'next_action' => $data['next_action'] ?? null,
            'follow_up_at' => filled($data['follow_up_at'] ?? null)
                ? Carbon::parse($data['follow_up_at'], $tz)->utc()
                : null,
        ]);

        // Return to the linked record's page when logged from there.
        if ($call->callable_type === Customer::class) {
            return redirect()->route('clients.show', $call->callable_id)->with('status', 'Call logged.');
        }
        if ($call->callable_type === Lead::class) {
            return redirect()->route('leads.show', $call->callable_id)->with('status', 'Call logged.');
        }

        return redirect()->route('calls.index')->with('status', 'Call logged.');
    }
}

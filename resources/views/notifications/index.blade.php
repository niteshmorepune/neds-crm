<x-app-layout>
    <x-slot name="header">Notifications</x-slot>

    <div class="max-w-3xl mx-auto space-y-4">
        @if (session('status'))
            <div class="rounded-md bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">{{ session('status') }}</div>
        @endif

        <div class="overflow-hidden overflow-x-auto rounded-lg bg-white shadow-sm divide-y divide-gray-100">
            @forelse ($notifications as $notification)
                @php($data = $notification->data)
                @php($type = $data['type'] ?? 'task_assigned')
                <div class="flex items-start justify-between gap-4 px-6 py-4">
                    <div>
                        @if ($type === 'call_follow_up')
                            <p class="text-sm font-medium text-gray-900">
                                ⏰ Call follow-up:
                                <a href="{{ $data['url'] }}" class="text-indigo-600 hover:underline">
                                    {{ $data['callable_name'] ?? 'Unknown' }}
                                </a>
                            </p>
                            @if (! empty($data['next_action']))
                                <p class="mt-0.5 text-xs text-gray-700">{{ $data['next_action'] }}</p>
                            @endif
                            <p class="mt-0.5 text-xs text-gray-500">
                                Was due {{ \Illuminate\Support\Carbon::parse($data['follow_up_at'])->timezone(config('app.display_timezone'))->format('d M Y, g:i A') }}
                                · {{ $notification->created_at->timezone(config('app.display_timezone'))->diffForHumans() }}
                            </p>
                        @elseif ($type === 'follow_up_reminder')
                            <p class="text-sm font-medium text-gray-900">
                                🔔 Follow-up reminder:
                                <a href="{{ $data['url'] }}" class="text-indigo-600 hover:underline">
                                    {{ $data['customer_name'] ?? 'Client removed' }}
                                </a>
                            </p>
                            @if (! empty($data['next_action']))
                                <p class="mt-0.5 text-xs text-gray-700">{{ $data['next_action'] }}</p>
                            @endif
                            <p class="mt-0.5 text-xs text-gray-500">
                                Was due {{ \Illuminate\Support\Carbon::parse($data['remind_at'])->timezone(config('app.display_timezone'))->format('d M Y, g:i A') }}
                                · {{ $notification->created_at->timezone(config('app.display_timezone'))->diffForHumans() }}
                            </p>
                        @elseif ($type === 'meeting_invitation')
                            <p class="text-sm font-medium text-gray-900">
                                📅 Meeting invitation: {{ $data['client_name'] ?? 'Client removed' }}
                            </p>
                            <p class="mt-0.5 text-xs text-gray-700">
                                Organised by {{ $data['organiser_name'] ?? 'NEDS Team' }}
                                @if (! empty($data['meet_link']))
                                    · <a href="{{ $data['meet_link'] }}" target="_blank" rel="noopener noreferrer" class="text-indigo-600 hover:underline">Join Meet</a>
                                @endif
                            </p>
                            <p class="mt-0.5 text-xs text-gray-500">
                                @if (! empty($data['occurred_at']))
                                    {{ \Illuminate\Support\Carbon::parse($data['occurred_at'])->timezone(config('app.display_timezone'))->format('d M Y, g:i A') }}
                                @endif
                                @if (! empty($data['duration_minutes'])) · {{ $data['duration_minutes'] }} min @endif
                                · {{ $notification->created_at->timezone(config('app.display_timezone'))->diffForHumans() }}
                            </p>
                        @elseif (! empty($data['message']))
                            @php($typeIcon = match ($type) {
                                'new_lead'                    => '🟢',
                                'lead_reassigned'              => '🔄',
                                'lead_owner_reminder'          => '⏱️',
                                'lead_escalated_untouched'     => '🚨',
                                'hot_lead'                    => '🔥',
                                'new_quotation'               => '📄',
                                'deal_won'                    => '🏆',
                                'new_invoice'                 => '🧾',
                                'payment_recorded'            => '💰',
                                'new_client_onboarded'         => '🆕',
                                'client_advance_recorded'     => '🏦',
                                'recurring_invoice_due_soon'  => '⚠️',
                                'contract_renewal_due_soon'   => '📅',
                                'payment_promise_broken'      => '🚩',
                                'smdost_brief_approved'       => '✅',
                                'leave_request_submitted', 'leave_request_reviewed' => '🌴',
                                'ticket_escalated'            => '🔺',
                                'quotation_needs_approval'     => '📄',
                                'quotation_decision_recorded' => '🤝',
                                'meeting_requested' => '📅',
                                'festival_greeting_drafted'   => '🎉',
                                'monthly_wins_note_drafted'   => '📈',
                                'lead_nurture_drafted'        => '✨',
                                'call_follow_up_auto_set'     => '🤖',
                                default                       => '🔔',
                            })
                            @php($invoiceDeleted = ! empty($data['invoice_id']) && $deletedInvoiceIds->contains($data['invoice_id']))
                            @php($dealDeleted = ! empty($data['deal_id']) && $deletedDealIds->contains($data['deal_id']))
                            @php($leadDeleted = ! empty($data['lead_id']) && $deletedLeadIds->contains($data['lead_id']))
                            @php($resolvedQuotation = $type === 'quotation_needs_approval' && ! empty($data['quotation_id']) ? $resolvedQuotations->get($data['quotation_id']) : null)
                            <p class="text-sm font-medium text-gray-900">
                                {{ $typeIcon }}
                                @if ($invoiceDeleted)
                                    <span class="text-gray-500">{{ $data['message'] }}</span>
                                    <span class="ml-1 text-xs italic text-gray-400">(invoice deleted)</span>
                                @elseif ($dealDeleted)
                                    <span class="text-gray-500">{{ $data['message'] }}</span>
                                    <span class="ml-1 text-xs italic text-gray-400">(deal deleted)</span>
                                @elseif ($leadDeleted)
                                    <span class="text-gray-500">{{ $data['message'] }}</span>
                                    <span class="ml-1 text-xs italic text-gray-400">(lead deleted)</span>
                                @elseif ($resolvedQuotation)
                                    <span class="text-gray-500">{{ $data['message'] }}</span>
                                    <span class="ml-1 text-xs italic text-gray-400">
                                        ({{ strtolower($resolvedQuotation->approval_status->label()) }}{{ $resolvedQuotation->approvedBy ? ' by '.$resolvedQuotation->approvedBy->name : '' }})
                                    </span>
                                @elseif (! empty($data['url']))
                                    <a href="{{ $data['url'] }}" class="text-indigo-600 hover:underline">{{ $data['message'] }}</a>
                                @else
                                    {{ $data['message'] }}
                                @endif
                            </p>
                            <p class="mt-0.5 text-xs text-gray-500">{{ $notification->created_at->timezone(config('app.display_timezone'))->diffForHumans() }}</p>
                        @elseif (! empty($data['task_title']))
                            <p class="text-sm font-medium text-gray-900">
                                @if (! empty($data['url']))
                                    Task assigned: <a href="{{ $data['url'] }}" class="text-indigo-600 hover:underline">{{ $data['task_title'] }}</a>
                                @else
                                    Task assigned: {{ $data['task_title'] }}
                                @endif
                            </p>
                            <p class="mt-0.5 text-xs text-gray-500">
                                @if (! empty($data['project'])) {{ $data['project'] }} · @endif
                                @if (! empty($data['due_date'])) Due {{ \Illuminate\Support\Carbon::parse($data['due_date'])->format('d M Y') }} · @endif
                                {{ $notification->created_at->timezone(config('app.display_timezone'))->diffForHumans() }}
                            </p>
                        @else
                            {{-- Unrecognised notification shape — never let one bad row take down the whole page. --}}
                            <p class="text-sm font-medium text-gray-900">🔔 Notification</p>
                            <p class="mt-0.5 text-xs text-gray-500">{{ $notification->created_at->timezone(config('app.display_timezone'))->diffForHumans() }}</p>
                        @endif
                    </div>
                    <form method="POST" action="{{ route('notifications.destroy', $notification->id) }}" class="shrink-0">
                        @csrf @method('DELETE')
                        <button class="text-xs text-gray-400 hover:text-red-500">Dismiss</button>
                    </form>
                </div>
            @empty
                <div class="px-6 py-10 text-center text-sm text-gray-400">No notifications.</div>
            @endforelse
        </div>

        <div>{{ $notifications->links() }}</div>
    </div>
</x-app-layout>

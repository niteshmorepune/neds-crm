<?php

namespace App\Livewire;

use App\Enums\UserRole;
use App\Enums\VisibilityAuditTouchChannel;
use App\Enums\VisibilityAuditTouchType;
use App\Models\VisibilityAuditTouch;
use App\Services\AiAssistant;
use App\Services\VisibilityAuditFunnelMetrics;
use App\Support\Ai;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Component;

/**
 * On-demand AI panel embedded on the VA Funnel Dashboard — splits "what did
 * AI handle overnight/outside office hours" from "what's the team missing
 * right now" (stuck leads with no staff touch, untagged Meta leads, and
 * unanswered inbound WhatsApp replies), reusing
 * VisibilityAuditFunnelMetrics's existing stuck-lead queries plus three new
 * ones added alongside this component. Admin/Manager only — reachability is
 * already guaranteed by VisibilityAuditDashboardController::index()'s own
 * hasRole(Admin, Manager) gate (the only page that embeds this component),
 * same "no dedicated Policy needed" convention as TeamPerformanceSummary —
 * still asserted inline in generate() as defense-in-depth.
 */
class VisibilityAuditActivitySummary extends Component
{
    public bool $aiEnabled = false;

    /** Ephemeral AI summary shown in a dismissible panel (never persisted). */
    public ?string $summary = null;

    public function mount(): void
    {
        $this->aiEnabled = Ai::enabled();
    }

    public function generate(VisibilityAuditFunnelMetrics $metrics, AiAssistant $ai): void
    {
        abort_unless(Ai::enabled() && auth()->user()?->hasRole(UserRole::Admin, UserRole::Manager), 403);

        $tz = config('app.display_timezone', 'Asia/Kolkata');

        $afterHoursLines = $metrics->afterHoursTouches(now()->subHours(24))
            ->map(fn (VisibilityAuditTouch $touch) => $this->describeTouch($touch, $tz))
            ->all();

        $gapLines = $this->buildGapLines($metrics);

        $this->summary = $ai->summarizeVisibilityAuditActivity($afterHoursLines, $gapLines)
            ?? 'Could not generate a summary right now. Please try again.';
    }

    public function dismiss(): void
    {
        $this->summary = null;
    }

    /**
     * @return list<string>
     */
    private function buildGapLines(VisibilityAuditFunnelMetrics $metrics): array
    {
        $lines = [];

        $awaitingCount = $metrics->awaitingServiceTag();
        if ($awaitingCount > 0) {
            $oldest = $metrics->leadsAwaitingServiceTag(5)
                ->map(fn ($lead) => "{$lead->name} ({$lead->created_at->diffForHumans()})")
                ->implode(', ');
            $lines[] = "- {$awaitingCount} Meta lead(s) have no service tagged and cannot enter this funnel at all (no automation runs until tagged). Oldest waiting: {$oldest}.";
        }

        foreach ($this->unhandledStuckLeads($metrics->stuckAtCheckout()) as $lead) {
            $hours = $this->hoursSince($lead->visibilityAuditFunnelEvents->first()?->created_at);
            $lines[] = "- {$lead->name} (owner: {$lead->owner?->name}) reached checkout {$hours}h ago, no staff follow-up since, hasn't paid.";
        }

        foreach ($this->unhandledStuckLeads($metrics->stuckAtLanding()) as $lead) {
            $hours = $this->hoursSince($lead->visibilityAuditFunnelEvents->first()?->created_at);
            $lines[] = "- {$lead->name} (owner: {$lead->owner?->name}) viewed the offer page {$hours}h ago, no staff follow-up since.";
        }

        foreach ($metrics->unansweredInboundReplies(now()->subHours(2)) as $touch) {
            $hours = $this->hoursSince($touch->occurred_at);
            $lines[] = "- {$touch->lead->name} (owner: {$touch->lead->owner?->name}) sent a WhatsApp reply {$hours}h ago with no staff response yet.";
        }

        return $lines;
    }

    /**
     * stuckAtLanding()/stuckAtCheckout() track funnel *page* progress only
     * — they don't already exclude a lead staff has since replied to (only
     * the recovery-nudge queries do that, to decide whether to send another
     * automated message). Apply that same exclusion here so this panel
     * never flags a lead someone is already mid-conversation with.
     */
    private function unhandledStuckLeads(Collection $leads): Collection
    {
        return $leads->reject(function ($lead) {
            $latestEvent = $lead->visibilityAuditFunnelEvents->first();

            return $latestEvent === null || $lead->hasStaffWhatsappReplySince($latestEvent->created_at);
        })->values();
    }

    private function describeTouch(VisibilityAuditTouch $touch, string $tz): string
    {
        $when = $touch->occurred_at->copy()->timezone($tz)->format('d M, g:i A');
        $leadName = $touch->lead?->name ?? 'Unknown lead';

        $what = match (true) {
            $touch->touch_type === VisibilityAuditTouchType::CustomerReply => "{$leadName} replied on WhatsApp",
            $touch->channel === VisibilityAuditTouchChannel::AiWhatsapp && ! $touch->success => "AI's {$touch->touch_type->label()} to {$leadName} FAILED to send",
            $touch->channel === VisibilityAuditTouchChannel::AiWhatsapp => "AI sent a {$touch->touch_type->label()} to {$leadName}",
            default => "{$touch->touch_type->label()} — {$leadName}",
        };

        return "- {$when}: {$what}";
    }

    private function hoursSince(?Carbon $moment): int
    {
        return $moment === null ? 0 : (int) round($moment->diffInHours(now()));
    }

    public function render()
    {
        return view('livewire.visibility-audit-activity-summary');
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Enums\LeadGoal;
use App\Enums\OfferKey;
use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Services\VisibilityAuditFunnelMetrics;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * wadesk.in → CRM lookup, the reverse direction of /api/webhook/whatsapp —
 * given a phone number, returns enough lead context (campaign, service,
 * declared budget, extra form answers, and a Visibility Audit offer link
 * when eligible) for the after-hours AI assistant to write a specific reply
 * instead of a generic "thanks for filling in the form" placeholder (a real
 * gap found 2026-08-20: the assistant only ever saw raw WhatsApp text, never
 * which ad/campaign or which structured answers the lead actually gave).
 * Same Bearer token as the inbound webhook — same wadesk.in trust boundary,
 * just the other direction, so no new secret was introduced for this.
 *
 * goal/needs_link/website_url/gbp_url (2026-09-08) power the assistant's own
 * goal-question flow (see updateGoal() below) — needs_link is precomputed
 * here (LeadGoal::needsWebsiteOrGbp()) rather than making wadesk's
 * TypeScript re-implement that enum's branching logic.
 *
 * recommended_offer_name/price/url (2026-09-13) close a real gap found live:
 * the after-hours assistant's `visibility_audit_offer_url` field only ever
 * covers the OLD, GBP-specific offer — once the 2026-09-12 unified
 * goal+budget matrix started recommending one of the OTHER 3 offers for a
 * lead, this endpoint had nothing to tell the assistant about that decision
 * at all, so it kept defaulting to GBP regardless of what the matrix
 * actually resolved (confirmed live on lead #382: matrix said
 * lead_generation_audit, the assistant recommended GBP anyway since that
 * was the only offer it had ever been told about). Deliberately omitted
 * when the resolved offer IS GbpAudit — `visibility_audit_offer_url`
 * already covers that case via its own funnel-tracking `.enter` redirect
 * hop, which must stay the one used for GBP so its LandingViewed event
 * still fires; this new field intentionally points at
 * `recommendationUrl()` instead (the `/offers/recommendation/{token}`
 * page), the equivalent tracked entry point for the other 3 offers.
 */
class LeadContextController extends Controller
{
    public function show(Request $request, VisibilityAuditFunnelMetrics $vaMetrics): JsonResponse
    {
        $phone = (string) $request->query('phone', '');

        $lead = $phone !== '' ? Lead::findOpenByPhone($phone) : null;

        if ($lead === null) {
            return response()->json(['found' => false]);
        }

        [$budgetRawAnswer, $additionalAnswers] = $this->extractFormAnswers($lead);
        $recommendedOffer = OfferKey::tryFrom((string) $lead->recommendation_offer_key);
        $recommendedNonGbpOffer = $recommendedOffer !== null && $recommendedOffer !== OfferKey::GbpAudit
            ? $recommendedOffer
            : null;

        return response()->json([
            'found' => true,
            'name' => $lead->name,
            'company' => $lead->company,
            'service' => $lead->service?->name,
            'campaign' => $lead->utm_campaign,
            'estimated_value_rupees' => $lead->estimated_value !== null ? intdiv($lead->estimated_value, 100) : null,
            'budget_question_raw_answer' => $budgetRawAnswer,
            'additional_answers' => $additionalAnswers,
            'visibility_audit_offer_url' => $vaMetrics->isVisibilityAuditCohort($lead)
                ? route('offers.visibility-audit.enter', ['lead' => $lead->id])
                : null,
            'goal' => $lead->goal?->value,
            'needs_link' => $lead->goal?->needsWebsiteOrGbp() ?? false,
            'website_url' => $lead->website_url,
            'gbp_url' => $lead->gbp_url,
            'recommended_offer_name' => $recommendedNonGbpOffer?->shortLabel(),
            'recommended_offer_price' => $recommendedNonGbpOffer?->price(),
            'recommended_offer_url' => $recommendedNonGbpOffer !== null ? $lead->recommendationUrl() : null,
        ]);
    }

    /**
     * wadesk.in → CRM write-back once its after-hours assistant's own
     * goal-question flow gets an answer. Deliberately a partial update —
     * only the fields actually sent are touched, so a goal-only call (the
     * first turn) never clears a website_url that was somehow already set,
     * same "only write what you actually have" discipline as
     * ImportMetaLead's backfill fields. goal transitioning to NotSure fires
     * LeadWantsExpertAdviceNotification via LeadObserver -- nothing to
     * trigger explicitly here.
     */
    public function updateGoal(Request $request): JsonResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'string'],
            'goal' => ['nullable', Rule::enum(LeadGoal::class)],
            'website_url' => ['nullable', 'url', 'max:2048'],
            'gbp_url' => ['nullable', 'url', 'max:2048'],
        ]);

        $lead = Lead::findOpenByPhone($data['phone']);

        if ($lead === null) {
            return response()->json(['updated' => false]);
        }

        $fill = array_filter(
            array_intersect_key($data, array_flip(['goal', 'website_url', 'gbp_url'])),
            fn ($value) => $value !== null,
        );

        if ($fill !== []) {
            $lead->update($fill);
        }

        return response()->json(['updated' => $fill !== []]);
    }

    /**
     * The extra Meta form Q&A (city, goal, budget, ...) only exists as a
     * Note body — ImportMetaLead writes it as "key: value" lines, one per
     * question, prefixed "Additional form answers:" (see its own docblock).
     * Pulls the budget-labelled line out separately (same
     * `str_contains($key, 'budget')` match ImportMetaLead::matchBudget()
     * already uses to decide whether to parse it as a number) so the
     * caller can flag an unparseable answer specifically — the exact
     * "newSURYA CABLE" incident this endpoint exists to fix — rather than
     * leaving it buried in a wall of undifferentiated text.
     *
     * @return array{0: ?string, 1: ?string}
     */
    private function extractFormAnswers(Lead $lead): array
    {
        $note = $lead->notes->first(fn ($n) => str_contains($n->body, 'Additional form answers:'));

        if ($note === null) {
            return [null, null];
        }

        $block = trim(str($note->body)->after('Additional form answers:')->value());
        $lines = array_values(array_filter(explode("\n", $block), fn ($line) => trim($line) !== ''));

        $budgetLine = null;
        $rest = [];

        foreach ($lines as $line) {
            $key = explode(':', $line, 2)[0] ?? '';

            if ($budgetLine === null && str_contains(mb_strtolower($key), 'budget')) {
                $budgetLine = trim(explode(':', $line, 2)[1] ?? '');

                continue;
            }

            $rest[] = $line;
        }

        return [$budgetLine ?: null, $rest !== [] ? implode("\n", $rest) : null];
    }
}

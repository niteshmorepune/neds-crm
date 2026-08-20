<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Services\VisibilityAuditFunnelMetrics;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
        ]);
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

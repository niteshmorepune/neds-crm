<?php

namespace App\Actions;

use App\Models\Lead;
use Illuminate\Support\Facades\DB;

/**
 * Auto-carries real business-data fields from a merged-away duplicate onto
 * the surviving primary Lead -- every field MergeLeadsRequest::
 * MERGEABLE_FIELDS never offered the rep a choice on in the first place
 * (Meta attribution, the offer-recommendation state, UTM, goal/budget,
 * website/GBP links, a scheduled follow-up, telecaller assignment).
 * Confirmed via a real production investigation (2026-09-16) that 15 of 38
 * historical merges silently dropped at least one of these onto the
 * trashed duplicate, with no way to recover it through the UI.
 *
 * Same "fill the primary only if it's currently null, never overwrite an
 * existing value" rule LeadMergeController::store() already uses for
 * alternate_phone -- a merge should never let one lead's real data
 * clobber the other's.
 *
 * meta_leadgen_id and recommendation_token are UNIQUE columns -- carrying
 * them over needs the same null-the-duplicate-first-then-set-on-primary
 * sequencing MergeLeads::handle() already uses for whatsapp_conversation_id
 * (a soft delete alone doesn't free a unique slot). The recommendation
 * bundle (recommendation_key/offer_key/token/generated_at) moves together
 * as one unit, gated on recommendation_token alone -- those four columns
 * together identify ONE specific resolved recommendation (and its own
 * /offers/recommendation/{token} URL, possibly already texted to the lead
 * or embedded in a live payment link), so carrying the token over without
 * its matching offer_key/generated_at would leave an incoherent record,
 * and letting GenerateLeadRecommendation regenerate a fresh one from
 * goal+budget alone would silently replace that already-live token with
 * an unrelated new one.
 *
 * Deliberately does NOT attempt whatsapp_conversation_id's own "both leads
 * had one -> record an additional mapping" handling for recommendation_token.
 * If the PRIMARY already has its own non-null token (both leads
 * independently answered goal+budget and each generated their own
 * recommendation -- the real #346/#411 case), there is no
 * LeadWhatsappConversation-style mapping table a second token could
 * resolve through, so the duplicate's own token is genuinely left
 * unrecovered in that one specific case -- by design, matching the
 * "never overwrite the primary's own value" rule, not an oversight. Flag
 * this explicitly wherever this runs rather than silently treating it as
 * fixed.
 */
class CarryOverMergeFields
{
    /**
     * Plain "fill primary if null" fields -- none of these are unique, so
     * no special sequencing is needed beyond the null-check itself.
     *
     * @var list<string>
     */
    private const NON_UNIQUE_FIELDS = [
        'goal', 'budget_range', 'website_url', 'gbp_url',
        'utm_source', 'utm_medium', 'utm_campaign',
        'next_follow_up_at', 'telecaller_id',
    ];

    public function __construct(private readonly GenerateLeadRecommendation $generateLeadRecommendation) {}

    /**
     * Computes what would change -- safe to call read-only for a dry-run
     * report -- and, unless $dryRun, applies it inside its own transaction.
     * Also gives GenerateLeadRecommendation a chance to resolve a
     * recommendation now that goal+budget may both be present for the
     * first time: a true no-op whenever a carried-over recommendation
     * bundle already matches the matrix's own resolution for that cell, so
     * this can never double-dispatch the first-touch WhatsApp message for
     * a merge that already carried over a valid token.
     *
     * @return array<string, mixed> the fields that would be (or were) written onto $primary, keyed by column name
     */
    public function handle(Lead $primary, Lead $duplicate, bool $dryRun = false): array
    {
        $changes = [];

        foreach (self::NON_UNIQUE_FIELDS as $field) {
            if ($primary->{$field} === null && $duplicate->{$field} !== null) {
                $changes[$field] = $duplicate->{$field};
            }
        }

        if ($primary->meta_leadgen_id === null && $duplicate->meta_leadgen_id !== null) {
            $changes['meta_leadgen_id'] = $duplicate->meta_leadgen_id;
        }

        if ($primary->recommendation_token === null && $duplicate->recommendation_token !== null) {
            $changes['recommendation_key'] = $duplicate->recommendation_key;
            $changes['recommendation_offer_key'] = $duplicate->recommendation_offer_key;
            $changes['recommendation_token'] = $duplicate->recommendation_token;
            $changes['recommendation_generated_at'] = $duplicate->recommendation_generated_at;
        }

        if ($dryRun || $changes === []) {
            return $changes;
        }

        DB::transaction(function () use ($primary, $duplicate, $changes): void {
            $recommendationFields = ['recommendation_key', 'recommendation_offer_key', 'recommendation_token', 'recommendation_generated_at'];
            $plainFill = array_diff_key($changes, array_flip(['meta_leadgen_id', ...$recommendationFields]));

            if ($plainFill !== []) {
                $primary->update($plainFill);
            }

            if (array_key_exists('meta_leadgen_id', $changes)) {
                $duplicate->update(['meta_leadgen_id' => null]);
                $primary->update(['meta_leadgen_id' => $changes['meta_leadgen_id']]);
            }

            if (array_key_exists('recommendation_token', $changes)) {
                $duplicate->update(['recommendation_token' => null]);
                $primary->update(array_intersect_key($changes, array_flip($recommendationFields)));
            }
        });

        $this->generateLeadRecommendation->handle($primary->fresh());

        return $changes;
    }
}

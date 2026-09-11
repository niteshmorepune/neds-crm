<?php

namespace App\Support;

use App\Enums\LeadBudgetRange;
use App\Enums\LeadGoal;
use App\Enums\OfferKey;

/**
 * The single source of truth for the 16-cell (goal x budget) recommendation
 * matrix that drives /offers/recommendation/{token} — see the 2026-09-12
 * CLAUDE.md decisions log entry. Every cell's recommendation name, offer,
 * price and Hindi/English positioning line is a direct, deliberate business
 * decision (not inferred), so this class is a flat literal array, not
 * generated — never derive a cell's offer from its price alone or vice
 * versa; always read both from here.
 *
 * Messaging rules baked into every explanation below (do not violate when
 * editing a cell): a small budget is never framed as "too low" — it's
 * framed as "start with the right diagnostic step"; a large budget is never
 * framed as "you have money to spend" — it's framed as a genuine strategic
 * planning opportunity.
 */
final class OfferRecommendationMatrix
{
    /**
     * @return array<string, array<string, OfferRecommendation>>
     */
    private static function cells(): array
    {
        return [
            LeadGoal::GenerateLeads->value => [
                LeadBudgetRange::Under3000->value => new OfferRecommendation(
                    goal: LeadGoal::GenerateLeads,
                    budget: LeadBudgetRange::Under3000,
                    recommendationKey: 'lead-generation-audit',
                    recommendationName: 'Lead Generation Audit',
                    offerKey: OfferKey::LeadGenerationAudit,
                    headline: 'Lead Generation Audit',
                    positioning: 'आपको leads चाहिए? पहले पता करें leads कहां lose हो रहे हैं.',
                    explanation: 'आपका primary goal More Leads generate करना है. इस budget range में सीधे नए channels पर spend बढ़ाने से पहले यह समझना ज़रूरी है कि आपके current funnel में leads कहाँ lose हो रहे हैं — इसलिए हमारा पहला step Lead Generation Funnel Audit है, न कि कोई बड़ा नया investment.',
                    salesIntent: 'diagnostic',
                ),
                LeadBudgetRange::ThreeToSix->value => new OfferRecommendation(
                    goal: LeadGoal::GenerateLeads,
                    budget: LeadBudgetRange::ThreeToSix,
                    recommendationKey: 'lead-generation-starter',
                    recommendationName: 'Lead Generation Starter',
                    offerKey: OfferKey::LeadGenerationAudit,
                    headline: 'Lead Generation Starter',
                    positioning: 'More Leads के लिए strong foundation बनाइए.',
                    explanation: 'More Leads generate करने के लिए एक strong foundation चाहिए — इसलिए पहला कदम है यह समझना कि आपकी website, ads, forms और follow-up process में leads कहाँ रुक रहे हैं. Lead Generation Funnel Audit इसी foundation को सही तरीके से तैयार करता है, ताकि आगे का spend सही जगह जाए.',
                    salesIntent: 'diagnostic',
                ),
                LeadBudgetRange::SixToTwelve->value => new OfferRecommendation(
                    goal: LeadGoal::GenerateLeads,
                    budget: LeadBudgetRange::SixToTwelve,
                    recommendationKey: 'lead-generation-growth',
                    recommendationName: 'Lead Generation Growth',
                    offerKey: OfferKey::GrowthStrategy,
                    headline: 'Lead Generation Growth',
                    positioning: 'Traffic को enquiries में convert करने का system बनाइए.',
                    explanation: 'इस budget में सिर्फ traffic लाना काफी नहीं — असली goal है traffic को enquiries में convert करना. Personalized Digital Growth Strategy आपके website, ads, forms और follow-up को मिलाकर एक clear conversion system तैयार करने की roadmap देती है.',
                    salesIntent: 'strategic',
                ),
                LeadBudgetRange::TwelvePlus->value => new OfferRecommendation(
                    goal: LeadGoal::GenerateLeads,
                    budget: LeadBudgetRange::TwelvePlus,
                    recommendationKey: 'lead-generation-accelerator',
                    recommendationName: 'Lead Generation Accelerator',
                    offerKey: OfferKey::GrowthStrategy,
                    headline: 'Lead Generation Accelerator',
                    positioning: '₹12,000+ budget है? Complete lead-generation system build कीजिये.',
                    explanation: 'आपके पास meaningful investment करने का scope है. इसलिए पहले एक complete lead-generation system — ads, landing pages, conversion tracking और follow-up — की clear roadmap बनाना सबसे बेहतर first step है, ताकि हर rupee सही channel में जाए.',
                    salesIntent: 'strategic',
                ),
            ],

            LeadGoal::RankHigher->value => [
                LeadBudgetRange::Under3000->value => new OfferRecommendation(
                    goal: LeadGoal::RankHigher,
                    budget: LeadBudgetRange::Under3000,
                    recommendationKey: 'gbp-visibility-audit',
                    recommendationName: 'Google Business Profile Visibility Audit',
                    offerKey: OfferKey::GbpAudit,
                    headline: 'Google Business Profile Visibility Audit',
                    positioning: 'Google पर आपका business क्यों नहीं दिख रहा? पहले जानिये.',
                    explanation: 'आपका goal Google पर बेहतर rank करना है. सबसे पहला और सबसे किफायती कदम है यह जानना कि आपका Google Business Profile अभी कहाँ कमज़ोर है — इसीलिए हम GBP Visibility Audit से शुरुआत recommend करते हैं.',
                    salesIntent: 'diagnostic',
                ),
                LeadBudgetRange::ThreeToSix->value => new OfferRecommendation(
                    goal: LeadGoal::RankHigher,
                    budget: LeadBudgetRange::ThreeToSix,
                    recommendationKey: 'local-seo-starter',
                    recommendationName: 'Local SEO Starter',
                    offerKey: OfferKey::GbpAudit,
                    headline: 'Local SEO Starter',
                    positioning: 'Google पर stronger visibility के लिए right foundation बनाइये.',
                    explanation: 'Google पर stronger visibility सीधे spend बढ़ाने से नहीं, सही foundation से आती है. GBP Visibility Audit आपके profile की असली स्थिति दिखाता है, ताकि आगे की Local SEO activity सही दिशा में हो.',
                    salesIntent: 'diagnostic',
                ),
                LeadBudgetRange::SixToTwelve->value => new OfferRecommendation(
                    goal: LeadGoal::RankHigher,
                    budget: LeadBudgetRange::SixToTwelve,
                    recommendationKey: 'local-seo-growth',
                    recommendationName: 'Local SEO Growth',
                    offerKey: OfferKey::GrowthStrategy,
                    headline: 'Local SEO Growth',
                    positioning: 'Google visibility को long-term growth channel बनाइये.',
                    explanation: 'इस budget में Google visibility को एक बार का काम नहीं, बल्कि एक long-term growth channel की तरह build करना बेहतर रहता है. Personalized Digital Growth Strategy GBP, Local SEO और SEO को मिलाकर एक स्पष्ट roadmap देती है.',
                    salesIntent: 'strategic',
                ),
                LeadBudgetRange::TwelvePlus->value => new OfferRecommendation(
                    goal: LeadGoal::RankHigher,
                    budget: LeadBudgetRange::TwelvePlus,
                    recommendationKey: 'seo-local-growth',
                    recommendationName: 'SEO + Local Growth',
                    offerKey: OfferKey::GrowthStrategy,
                    headline: 'SEO + Local Growth',
                    positioning: 'Google पर serious growth चाहिए? SEO + Local strategy build किजिये.',
                    explanation: 'आपके पास meaningful growth investment करने का scope है. इसलिए पहले एक clear SEO + Local SEO growth roadmap बनाना बेहतर रहेगा, ताकि Google पर आपकी visibility एक consistent, compounding growth channel बने.',
                    salesIntent: 'strategic',
                ),
            ],

            LeadGoal::GrowBusiness->value => [
                LeadBudgetRange::Under3000->value => new OfferRecommendation(
                    goal: LeadGoal::GrowBusiness,
                    budget: LeadBudgetRange::Under3000,
                    recommendationKey: 'digital-visibility-quick-audit',
                    recommendationName: 'Digital Visibility Quick Audit',
                    offerKey: OfferKey::LeadGenerationAudit,
                    headline: 'Digital Visibility Quick Audit',
                    positioning: 'Online growth के लिए पहले biggest opportunity identify किजिये.',
                    explanation: 'Online business grow करने के लिए सबसे पहला कदम है यह जानना कि अभी आपकी सबसे बड़ी opportunity कहाँ छूट रही है — leads, calls या conversions में. Lead Generation Funnel Audit इसी opportunity को साफ़-साफ़ सामने लाता है.',
                    salesIntent: 'diagnostic',
                ),
                LeadBudgetRange::ThreeToSix->value => new OfferRecommendation(
                    goal: LeadGoal::GrowBusiness,
                    budget: LeadBudgetRange::ThreeToSix,
                    recommendationKey: 'website-growth-audit',
                    recommendationName: 'Website Growth Audit',
                    offerKey: OfferKey::WebsiteGrowthAudit,
                    headline: 'Website Growth Audit',
                    positioning: 'Online business growth के लिए strong digital foundation बनाइये.',
                    explanation: 'Online growth तभी टिकाऊ होती है जब आपकी website सिर्फ दिखे नहीं, business भी generate करे. Website + Conversion Growth Audit आपकी website के performance, trust signals और conversion points की पूरी review देता है.',
                    salesIntent: 'diagnostic',
                ),
                LeadBudgetRange::SixToTwelve->value => new OfferRecommendation(
                    goal: LeadGoal::GrowBusiness,
                    budget: LeadBudgetRange::SixToTwelve,
                    recommendationKey: 'digital-growth-strategy',
                    recommendationName: 'Digital Growth Strategy',
                    offerKey: OfferKey::GrowthStrategy,
                    headline: 'Digital Growth Strategy',
                    positioning: 'आपकी online presence को growth engine बनाइये.',
                    explanation: 'इस budget में आपकी online presence को अलग-अलग टुकड़ों में नहीं, एक जुड़े हुए growth engine की तरह प्लान करना बेहतर रहता है. Personalized Digital Growth Strategy website, GBP, SEO और ads को मिलाकर एक स्पष्ट roadmap देती है.',
                    salesIntent: 'strategic',
                ),
                LeadBudgetRange::TwelvePlus->value => new OfferRecommendation(
                    goal: LeadGoal::GrowBusiness,
                    budget: LeadBudgetRange::TwelvePlus,
                    recommendationKey: '360-digital-growth',
                    recommendationName: '360° Digital Growth',
                    offerKey: OfferKey::GrowthStrategy,
                    headline: '360° Digital Growth',
                    positioning: '₹12,000+ budget है? Complete digital growth strategy बनाइये.',
                    explanation: 'आपके पास meaningful growth investment करने का scope है. इसलिए पहले एक complete 360° digital growth roadmap — website, visibility, leads और conversion — बनाना सबसे strategic पहला कदम है.',
                    salesIntent: 'strategic',
                ),
            ],

            LeadGoal::NotSure->value => [
                LeadBudgetRange::Under3000->value => new OfferRecommendation(
                    goal: LeadGoal::NotSure,
                    budget: LeadBudgetRange::Under3000,
                    recommendationKey: 'expert-direction-gbp-audit',
                    recommendationName: 'Expert Direction + GBP Audit',
                    offerKey: OfferKey::GbpAudit,
                    headline: 'Expert Direction + GBP Audit',
                    positioning: 'Sure नहीं है क्या करना चाहिए? पहले clarity लिजिये.',
                    explanation: 'जब clear नहीं है कि कहाँ से शुरू करें, तो सबसे अच्छा पहला कदम है अपनी असली स्थिति समझना. GBP Visibility Audit आपके Google presence की एक साफ़ तस्वीर देता है, जिससे अगला सही कदम तय करना आसान हो जाता है.',
                    salesIntent: 'diagnostic',
                ),
                LeadBudgetRange::ThreeToSix->value => new OfferRecommendation(
                    goal: LeadGoal::NotSure,
                    budget: LeadBudgetRange::ThreeToSix,
                    recommendationKey: 'growth-strategy-starter',
                    recommendationName: 'Growth Strategy Starter',
                    offerKey: OfferKey::GrowthStrategy,
                    headline: 'Growth Strategy Starter',
                    positioning: 'आपका बजट कहां जाना चाहिए? हम identify करेंगे.',
                    explanation: 'जब goal clear नहीं है, तो budget को इधर-उधर खर्च करने के बजाय पहले एक स्पष्ट दिशा तय करना ज़्यादा फायदेमंद है. Personalized Digital Growth Strategy आपकी business की असली स्थिति देखकर बताती है कि आपका बजट कहाँ जाना चाहिए.',
                    salesIntent: 'strategic',
                ),
                LeadBudgetRange::SixToTwelve->value => new OfferRecommendation(
                    goal: LeadGoal::NotSure,
                    budget: LeadBudgetRange::SixToTwelve,
                    recommendationKey: 'digital-growth-strategy',
                    recommendationName: 'Digital Growth Strategy',
                    offerKey: OfferKey::GrowthStrategy,
                    headline: 'Digital Growth Strategy',
                    positioning: 'आपके business के लिए सही growth mix क्या होना चाहिए? चलिए decide करते हैं.',
                    explanation: 'इस budget में सही growth mix — SEO, ads, website या social — decide करना अकेले तय करना मुश्किल हो सकता है. Personalized Digital Growth Strategy आपकी business की स्थिति देखकर एक स्पष्ट, personalized roadmap देती है.',
                    salesIntent: 'strategic',
                ),
                LeadBudgetRange::TwelvePlus->value => new OfferRecommendation(
                    goal: LeadGoal::NotSure,
                    budget: LeadBudgetRange::TwelvePlus,
                    recommendationKey: '360-growth-strategy',
                    recommendationName: '360° Growth Strategy',
                    offerKey: OfferKey::GrowthStrategy,
                    headline: '360° Growth Strategy',
                    positioning: 'Budget strong है. अब उसे right places पर invest करना है.',
                    explanation: 'आपके पास meaningful growth investment करने का scope है. इसलिए पहले एक clear digital growth roadmap बनाना बेहतर रहेगा, ताकि यह बजट सही channels पर, सही order में invest हो.',
                    salesIntent: 'strategic',
                ),
            ],
        ];
    }

    public static function for(LeadGoal $goal, LeadBudgetRange $budget): OfferRecommendation
    {
        return self::cells()[$goal->value][$budget->value];
    }

    /**
     * @return list<OfferRecommendation>
     */
    public static function all(): array
    {
        // Deliberately NOT Collection::flatMap() — every goal's inner array
        // shares the same 4 budget-value keys, so collapsing them the
        // Collection way silently overwrites all but the last goal's row
        // instead of concatenating all 16.
        $all = [];

        foreach (self::cells() as $row) {
            foreach ($row as $recommendation) {
                $all[] = $recommendation;
            }
        }

        return $all;
    }
}

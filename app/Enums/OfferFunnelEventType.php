<?php

namespace App\Enums;

/**
 * The funnel-stage events tracked for the Meta Ads offer/recommendation
 * funnel (App\Support\OfferRecommendationMatrix onward) — mirrors
 * VisibilityAuditFunnelEventType's shape, generalized across all 4 offers.
 * MetaLeadCreated is captured implicitly (Lead::created with a Meta source)
 * rather than as its own event row here — see OfferFunnelMetrics.
 */
enum OfferFunnelEventType: string
{
    case RecommendationCreated = 'recommendation_created';
    case RecommendationViewed = 'recommendation_viewed';
    case OfferViewed = 'offer_viewed';
    case OfferCtaClicked = 'offer_cta_clicked';
    case PaymentStarted = 'payment_started';
    case PaymentSucceeded = 'payment_succeeded';
    case PaymentFailed = 'payment_failed';

    public function label(): string
    {
        return match ($this) {
            self::RecommendationCreated => 'Recommendation created',
            self::RecommendationViewed => 'Recommendation viewed',
            self::OfferViewed => 'Offer page viewed',
            self::OfferCtaClicked => 'Offer CTA clicked',
            self::PaymentStarted => 'Payment started',
            self::PaymentSucceeded => 'Payment succeeded',
            self::PaymentFailed => 'Payment failed',
        };
    }
}

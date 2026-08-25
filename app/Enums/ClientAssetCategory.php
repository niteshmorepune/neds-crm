<?php

namespace App\Enums;

/**
 * Bounded category taxonomy for a client's Assets & Documents library — same
 * shape as TeamResourceCategory (the company-wide equivalent).
 */
enum ClientAssetCategory: string
{
    case BrandAssets = 'brand_assets';
    case WebsiteContent = 'website_content';
    case SocialAssets = 'social_assets';
    case BusinessDocuments = 'business_documents';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::BrandAssets => 'Brand Assets',
            self::WebsiteContent => 'Website Content',
            self::SocialAssets => 'Social Assets',
            self::BusinessDocuments => 'Business Documents',
            self::Other => 'Other',
        };
    }
}

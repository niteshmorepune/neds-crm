<?php

namespace App\Enums;

/**
 * Sidebar section grouping — formalizes the workflow-stage ordering
 * MenuItemsSeeder's own comment already documented (2026-07-25 reorder).
 * Display-only: never used in an authorization check.
 */
enum MenuGroup: string
{
    case MyWork = 'my_work';
    case SalesPipeline = 'sales_pipeline';
    case Finance = 'finance';
    case DeliverySupport = 'delivery_support';
    case TeamInsights = 'team_insights';
    case TeamTools = 'team_tools';
    case AdminConfig = 'admin_config';

    public function label(): string
    {
        return match ($this) {
            self::MyWork => 'My Work',
            self::SalesPipeline => 'Sales & Pipeline',
            self::Finance => 'Finance',
            self::DeliverySupport => 'Delivery & Support',
            self::TeamInsights => 'Team & Insights',
            self::TeamTools => 'Team Tools',
            self::AdminConfig => 'Admin & Config',
        };
    }
}

<?php

namespace App\Support;

/**
 * Dashboard Customization's bounded widget registry — every toggleable
 * block on every role's dashboard partial, keyed by the same $panel value
 * DashboardController::index() already computes. Deliberately plain PHP,
 * not a database table: which widgets EXIST doesn't vary by deployment or
 * change without a code change (unlike MenuItem, whose route/icon/roles are
 * genuinely admin-editable data) — only whether a given user has hidden one
 * is stored (HiddenDashboardWidget). Same "bounded registry, not free text"
 * discipline as CrmQueryCatalog/NudgeAutoDetectType elsewhere in this app.
 */
class DashboardWidgets
{
    /**
     * @return array<string, array<string, string>>
     */
    public static function catalog(): array
    {
        return [
            'admin' => [
                'clients_total' => 'Total Clients',
                'clients_active' => 'Active Clients',
                'clients_inactive' => 'Inactive Clients',
                'leads_total' => 'Total Leads',
                'tasks_overview' => 'Tasks Overview',
                'services_overview' => 'Services Overview',
                'task_summary' => 'Task Summary',
                'daily_reports_links' => 'Daily Reports links',
                'project_dashboard_links' => 'Project Dashboard links',
                'reports_links' => 'Reports links',
            ],
            'sales' => [
                'followups_due' => 'Follow-ups due',
                'won_this_month' => 'Won this month',
                'pipeline_table' => 'Open pipeline by stage',
                'call_priority_list' => 'Who to Call Today',
                'overdue_follow_ups' => 'Overdue follow-ups',
                'my_productivity' => 'Your Productivity This Month',
            ],
            'accounts' => [
                'outstanding' => 'Outstanding receivables',
                'collected_this_month' => 'Collected this month',
                'overdue_invoices' => 'Overdue invoices',
                'unapplied_advances' => 'Unapplied client advances',
                'action_buttons' => 'Report quick links',
                'my_productivity' => 'Your Productivity This Month',
            ],
            'support' => [
                'open_tickets' => 'Open tickets',
                'sla_at_risk' => 'SLA at risk',
                'tasks_total' => 'Total tasks',
                'tasks_pending' => 'Pending tasks',
                'tasks_overdue' => 'Overdue tasks',
                'open_by_priority' => 'Open tickets by priority',
                'my_productivity' => 'Your Productivity This Month',
            ],
            'intern' => [
                'pending_tasks' => 'Pending tasks',
                'completed_today' => 'Completed today',
                'active_projects' => 'Active projects',
                'my_productivity' => 'Your Productivity This Month',
            ],
            'telecaller' => [
                'new_leads' => 'New leads to call',
                'calls_today' => 'Calls made today',
                'followups_due' => 'Follow-ups due',
            ],
        ];
    }

    /**
     * @return array<string, string> widget_key => label, for one panel. Empty for 'blank' or an unknown panel.
     */
    public static function forPanel(string $panel): array
    {
        return self::catalog()[$panel] ?? [];
    }

    public static function isValidKey(string $panel, string $widgetKey): bool
    {
        return array_key_exists($widgetKey, self::forPanel($panel));
    }
}

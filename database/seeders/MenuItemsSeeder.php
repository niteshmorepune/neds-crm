<?php

namespace Database\Seeders;

use App\Enums\MenuGroup;
use App\Enums\UserRole;
use App\Models\MenuItem;
use App\Services\MenuResolver;
use Illuminate\Database\Seeder;

class MenuItemsSeeder extends Seeder
{
    /**
     * The sidebar menu and its role defaults. Idempotent: re-running updates
     * labels/order/roles without creating duplicates. Admin is omitted from
     * the role lists because admin always has full access (MenuResolver).
     *
     * @var array<int, array{key:string, label:string, group:MenuGroup, route:string, icon:string, roles:array<int, UserRole>}>
     */
    private array $items = [];

    public function __construct()
    {
        $all = [UserRole::Manager, UserRole::Sales, UserRole::Support, UserRole::Accounts, UserRole::Intern, UserRole::Telecaller];

        // Ordered by workflow stage, not by build history (confirmed with the
        // owner 2026-07-25): daily habits first, then the Sales pipeline
        // end-to-end (Lead → Deal → Quotation → Client → Incentive), then
        // Finance, then Delivery/Support, then shared oversight tools, then
        // admin-only config at the very bottom. `group` formalizes this same
        // ordering into the collapsible sidebar sections (2026-08-12).
        $this->items = [
            ['key' => 'dashboard',        'label' => 'Dashboard',        'group' => MenuGroup::MyWork,          'route' => 'dashboard',        'icon' => 'dashboard',  'roles' => $all],
            ['key' => 'my-day',           'label' => 'My Day',           'group' => MenuGroup::MyWork,          'route' => 'my-day.index',     'icon' => 'sun',        'roles' => $all],
            ['key' => 'attendance',       'label' => 'Attendance',       'group' => MenuGroup::MyWork,          'route' => 'attendance.index', 'icon' => 'clock',      'roles' => $all],
            ['key' => 'leave-requests',   'label' => 'Leave Requests',   'group' => MenuGroup::MyWork,          'route' => 'leave-requests.index', 'icon' => 'calendar', 'roles' => $all],
            ['key' => 'lead-generation',  'label' => 'Lead Generation',  'group' => MenuGroup::SalesPipeline,   'route' => 'leads.index',      'icon' => 'funnel',     'roles' => [UserRole::Manager, UserRole::Sales, UserRole::Telecaller]],
            ['key' => 'sales-department', 'label' => 'Sales Pipeline',  'group' => MenuGroup::SalesPipeline,  'route' => 'deals.index',      'icon' => 'trending',   'roles' => [UserRole::Manager, UserRole::Sales]],
            ['key' => 'sales-dashboard',  'label' => 'Sales Dashboard',  'group' => MenuGroup::SalesPipeline,   'route' => 'sales-dashboard.index', 'icon' => 'chart-bar', 'roles' => [UserRole::Manager, UserRole::Sales]],
            ['key' => 'quotations',       'label' => 'Quotations',       'group' => MenuGroup::SalesPipeline,   'route' => 'quotations.index', 'icon' => 'document',   'roles' => [UserRole::Manager, UserRole::Sales, UserRole::Accounts]],
            ['key' => 'customer',         'label' => 'Clients',          'group' => MenuGroup::SalesPipeline,   'route' => 'clients.index',    'icon' => 'users',      'roles' => [UserRole::Manager, UserRole::Sales, UserRole::Support, UserRole::Accounts, UserRole::Intern]],
            // "important-links" is a daily reference tool used by everyone,
            // not sales- or finance-specific — grouped with My Work rather
            // than Sales & Pipeline even though it sits here in build order.
            // Label/route repointed to the combined Resources page (Files +
            // Links tabs) — key deliberately left as 'important-links' since
            // updateOrCreate() below matches on key; renaming it would
            // insert a duplicate row and orphan its role assignments.
            ['key' => 'important-links',  'label' => 'Resources',        'group' => MenuGroup::MyWork,          'route' => 'resources.index',  'icon' => 'link',  'roles' => $all],
            ['key' => 'incentives',       'label' => 'Incentives',       'group' => MenuGroup::SalesPipeline,   'route' => 'incentives.index', 'icon' => 'currency-rupee', 'roles' => [UserRole::Manager, UserRole::Sales]],
            ['key' => 'invoices',         'label' => 'Invoices',         'group' => MenuGroup::Finance,         'route' => 'invoices.index',   'icon' => 'receipt',    'roles' => [UserRole::Manager, UserRole::Accounts, UserRole::Sales]],
            ['key' => 'account',          'label' => 'Account',          'group' => MenuGroup::Finance,         'route' => 'reports.receivables', 'icon' => 'wallet',  'roles' => [UserRole::Manager, UserRole::Accounts]],
            ['key' => 'collections',      'label' => 'Collections',      'group' => MenuGroup::Finance,         'route' => 'reports.collections', 'icon' => 'banknotes', 'roles' => [UserRole::Manager, UserRole::Accounts]],
            ['key' => 'contract-renewals', 'label' => 'Contract & Renewals', 'group' => MenuGroup::Finance,     'route' => 'contract-renewals.index', 'icon' => 'refresh', 'roles' => [UserRole::Manager, UserRole::Accounts, UserRole::Sales]],
            ['key' => 'tickets',          'label' => 'Tickets',          'group' => MenuGroup::DeliverySupport, 'route' => 'tickets.index',    'icon' => 'lifebuoy',   'roles' => [UserRole::Manager, UserRole::Support, UserRole::Sales]],
            ['key' => 'calling',          'label' => 'Calling',          'group' => MenuGroup::DeliverySupport, 'route' => 'calls.index',      'icon' => 'phone',      'roles' => [UserRole::Manager, UserRole::Sales, UserRole::Support, UserRole::Telecaller]],
            ['key' => 'project-updates',  'label' => 'Project Updates',  'group' => MenuGroup::DeliverySupport, 'route' => 'projects.index',   'icon' => 'briefcase',  'roles' => [UserRole::Manager, UserRole::Sales, UserRole::Support, UserRole::Intern]],
            ['key' => 'emptask',          'label' => 'Employee Task',    'group' => MenuGroup::DeliverySupport, 'route' => 'tasks.index',      'icon' => 'check',      'roles' => $all],
            ['key' => 'manager-action-center', 'label' => 'Action Center', 'group' => MenuGroup::TeamInsights,  'route' => 'manager-action-center.index', 'icon' => 'bolt', 'roles' => [UserRole::Manager]],
            ['key' => 'approval-center',  'label' => 'Approval Center',  'group' => MenuGroup::TeamInsights,    'route' => 'approval-center.index', 'icon' => 'check-badge', 'roles' => [UserRole::Manager]],
            ['key' => 'team-workload',    'label' => 'Team Workload',    'group' => MenuGroup::TeamInsights,    'route' => 'team-workload.index', 'icon' => 'scale', 'roles' => [UserRole::Manager]],
            ['key' => 'project-health',   'label' => 'Project Health',   'group' => MenuGroup::TeamInsights,    'route' => 'project-health.index', 'icon' => 'heart', 'roles' => [UserRole::Manager]],
            ['key' => 'revenue-at-risk',  'label' => 'Revenue at Risk',  'group' => MenuGroup::TeamInsights,    'route' => 'revenue-at-risk.index', 'icon' => 'exclamation-triangle', 'roles' => [UserRole::Manager]],
            ['key' => 'client-radar',     'label' => 'Client Radar',     'group' => MenuGroup::TeamInsights,    'route' => 'client-radar.index', 'icon' => 'radar',    'roles' => [UserRole::Manager]],
            ['key' => 'employee-360',     'label' => 'Employee 360°',    'group' => MenuGroup::TeamInsights,    'route' => 'employees.index',  'icon' => 'identification', 'roles' => [UserRole::Manager]],
            ['key' => 'team-targets',     'label' => 'Team Targets',     'group' => MenuGroup::TeamInsights,    'route' => 'role-targets.index', 'icon' => 'flag', 'roles' => [UserRole::Manager]],
            ['key' => 'manager-calendar', 'label' => 'Manager Calendar', 'group' => MenuGroup::TeamInsights,    'route' => 'manager-calendar.index', 'icon' => 'calendar-days', 'roles' => [UserRole::Manager]],
            ['key' => 'daily-reports',    'label' => 'Daily Reports',    'group' => MenuGroup::TeamInsights,    'route' => 'daily-reports.index', 'icon' => 'clipboard', 'roles' => $all],
            ['key' => 'quarterly-awards', 'label' => 'Best Employee',    'group' => MenuGroup::TeamInsights,    'route' => 'quarterly-awards.index', 'icon' => 'trophy',  'roles' => $all],
            ['key' => 'partners',         'label' => 'Partners',         'group' => MenuGroup::TeamInsights,    'route' => 'partners.index',   'icon' => 'users',      'roles' => [UserRole::Manager]],
            ['key' => 'announcements',    'label' => 'Notice Board',     'group' => MenuGroup::TeamInsights,    'route' => 'announcements.index', 'icon' => 'megaphone', 'roles' => [UserRole::Manager]],
            ['key' => 'team-nudges',      'label' => 'Team Nudges',      'group' => MenuGroup::TeamInsights,    'route' => 'team-nudges.index', 'icon' => 'bell',       'roles' => [UserRole::Manager]],
            ['key' => 'categories',       'label' => 'Services',         'group' => MenuGroup::AdminConfig,     'route' => 'services.index',   'icon' => 'tag',        'roles' => [UserRole::Manager]],
            ['key' => 'lead-assignment-rules', 'label' => 'Lead Assignment Rules', 'group' => MenuGroup::AdminConfig, 'route' => 'lead-assignment-rules.index', 'icon' => 'funnel', 'roles' => [UserRole::Manager]],
            ['key' => 'festivals',        'label' => 'Festivals',        'group' => MenuGroup::AdminConfig,     'route' => 'festivals.index',  'icon' => 'calendar',   'roles' => [UserRole::Manager]],
            ['key' => 'subscriptions',    'label' => 'Subscriptions',    'group' => MenuGroup::AdminConfig,     'route' => 'subscriptions.index', 'icon' => 'credit-card', 'roles' => []], // admin only
            ['key' => 'expenses',         'label' => 'Expenses',         'group' => MenuGroup::Finance,         'route' => 'expenses.index',   'icon' => 'banknotes',  'roles' => [UserRole::Manager, UserRole::Accounts]],
            ['key' => 'users',            'label' => 'Users',            'group' => MenuGroup::AdminConfig,     'route' => 'users.index',      'icon' => 'users',      'roles' => []], // admin only
            ['key' => 'menu-controller',  'label' => 'Menu Controller',  'group' => MenuGroup::AdminConfig,     'route' => 'menu-controller',  'icon' => 'sliders',    'roles' => []], // admin only
        ];
    }

    public function run(): void
    {
        foreach ($this->items as $sort => $data) {
            $item = MenuItem::updateOrCreate(
                ['key' => $data['key']],
                [
                    'label' => $data['label'],
                    'group' => $data['group'],
                    'route' => $data['route'],
                    'icon' => $data['icon'],
                    'sort_order' => $sort + 1,
                ],
            );

            $item->syncRoles($data['roles']);
        }

        // Menu data changed — invalidate any cached sidebars/access lists.
        app(MenuResolver::class)->flush();
    }
}

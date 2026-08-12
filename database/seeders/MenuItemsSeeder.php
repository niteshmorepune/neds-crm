<?php

namespace Database\Seeders;

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
     * @var array<int, array{key:string, label:string, route:string, icon:string, roles:array<int, UserRole>}>
     */
    private array $items = [];

    public function __construct()
    {
        $all = [UserRole::Manager, UserRole::Sales, UserRole::Support, UserRole::Accounts, UserRole::Intern, UserRole::Telecaller];

        // Ordered by workflow stage, not by build history (confirmed with the
        // owner 2026-07-25): daily habits first, then the Sales pipeline
        // end-to-end (Lead → Deal → Quotation → Client → Incentive), then
        // Finance, then Delivery/Support, then shared oversight tools, then
        // admin-only config at the very bottom.
        $this->items = [
            ['key' => 'dashboard',        'label' => 'Dashboard',        'route' => 'dashboard',        'icon' => 'dashboard',  'roles' => $all],
            ['key' => 'my-day',           'label' => 'My Day',           'route' => 'my-day.index',     'icon' => 'sun',        'roles' => $all],
            ['key' => 'attendance',       'label' => 'Attendance',       'route' => 'attendance.index', 'icon' => 'clock',      'roles' => $all],
            ['key' => 'leave-requests',   'label' => 'Leave Requests',   'route' => 'leave-requests.index', 'icon' => 'calendar', 'roles' => $all],
            ['key' => 'lead-generation',  'label' => 'Lead Generation',  'route' => 'leads.index',      'icon' => 'funnel',     'roles' => [UserRole::Manager, UserRole::Sales, UserRole::Telecaller]],
            ['key' => 'sales-department', 'label' => 'Sales Department',  'route' => 'deals.index',      'icon' => 'trending',   'roles' => [UserRole::Manager, UserRole::Sales]],
            ['key' => 'sales-dashboard',  'label' => 'Sales Dashboard',  'route' => 'sales-dashboard.index', 'icon' => 'chart-bar', 'roles' => [UserRole::Manager, UserRole::Sales]],
            ['key' => 'quotations',       'label' => 'Quotations',       'route' => 'quotations.index', 'icon' => 'document',   'roles' => [UserRole::Manager, UserRole::Sales, UserRole::Accounts]],
            ['key' => 'customer',         'label' => 'Clients',          'route' => 'clients.index',    'icon' => 'users',      'roles' => [UserRole::Manager, UserRole::Sales, UserRole::Support, UserRole::Accounts, UserRole::Intern]],
            // Label/route repointed to the combined Resources page (Files +
            // Links tabs) — key deliberately left as 'important-links' since
            // updateOrCreate() below matches on key; renaming it would
            // insert a duplicate row and orphan its role assignments.
            ['key' => 'important-links',  'label' => 'Resources',        'route' => 'resources.index',       'icon' => 'link',  'roles' => $all],
            ['key' => 'incentives',       'label' => 'Incentives',       'route' => 'incentives.index', 'icon' => 'currency-rupee', 'roles' => [UserRole::Manager, UserRole::Sales]],
            ['key' => 'invoices',         'label' => 'Invoices',         'route' => 'invoices.index',   'icon' => 'receipt',    'roles' => [UserRole::Manager, UserRole::Accounts, UserRole::Sales]],
            ['key' => 'account',          'label' => 'Account',          'route' => 'reports.receivables', 'icon' => 'wallet',  'roles' => [UserRole::Manager, UserRole::Accounts]],
            ['key' => 'collections',      'label' => 'Collections',      'route' => 'reports.collections', 'icon' => 'banknotes', 'roles' => [UserRole::Manager, UserRole::Accounts]],
            ['key' => 'contract-renewals', 'label' => 'Contract & Renewals', 'route' => 'contract-renewals.index', 'icon' => 'refresh', 'roles' => [UserRole::Manager, UserRole::Accounts, UserRole::Sales]],
            ['key' => 'tickets',          'label' => 'Tickets',          'route' => 'tickets.index',    'icon' => 'lifebuoy',   'roles' => [UserRole::Manager, UserRole::Support, UserRole::Sales]],
            ['key' => 'calling',          'label' => 'Calling',          'route' => 'calls.index',      'icon' => 'phone',      'roles' => [UserRole::Manager, UserRole::Sales, UserRole::Support, UserRole::Telecaller]],
            ['key' => 'project-updates',  'label' => 'Project Updates',  'route' => 'projects.index',   'icon' => 'briefcase',  'roles' => [UserRole::Manager, UserRole::Sales, UserRole::Support, UserRole::Intern]],
            ['key' => 'emptask',          'label' => 'Employee Task',    'route' => 'tasks.index',      'icon' => 'check',      'roles' => $all],
            ['key' => 'manager-action-center', 'label' => 'Action Center', 'route' => 'manager-action-center.index', 'icon' => 'bolt', 'roles' => [UserRole::Manager]],
            ['key' => 'approval-center',  'label' => 'Approval Center',  'route' => 'approval-center.index', 'icon' => 'check-badge', 'roles' => [UserRole::Manager]],
            ['key' => 'team-workload',    'label' => 'Team Workload',    'route' => 'team-workload.index', 'icon' => 'scale', 'roles' => [UserRole::Manager]],
            ['key' => 'project-health',   'label' => 'Project Health',   'route' => 'project-health.index', 'icon' => 'heart', 'roles' => [UserRole::Manager]],
            ['key' => 'revenue-at-risk',  'label' => 'Revenue at Risk',  'route' => 'revenue-at-risk.index', 'icon' => 'exclamation-triangle', 'roles' => [UserRole::Manager]],
            ['key' => 'client-radar',     'label' => 'Client Radar',     'route' => 'client-radar.index', 'icon' => 'radar',    'roles' => [UserRole::Manager]],
            ['key' => 'employee-360',     'label' => 'Employee 360°',    'route' => 'employees.index',  'icon' => 'identification', 'roles' => [UserRole::Manager]],
            ['key' => 'manager-calendar', 'label' => 'Manager Calendar', 'route' => 'manager-calendar.index', 'icon' => 'calendar-days', 'roles' => [UserRole::Manager]],
            ['key' => 'daily-reports',    'label' => 'Daily Reports',    'route' => 'daily-reports.index', 'icon' => 'clipboard', 'roles' => $all],
            ['key' => 'quarterly-awards', 'label' => 'Best Employee',    'route' => 'quarterly-awards.index', 'icon' => 'trophy',  'roles' => $all],
            ['key' => 'partners',         'label' => 'Partners',         'route' => 'partners.index',   'icon' => 'users',      'roles' => [UserRole::Manager]],
            ['key' => 'announcements',    'label' => 'Notice Board',     'route' => 'announcements.index', 'icon' => 'megaphone', 'roles' => [UserRole::Manager]],
            ['key' => 'team-nudges',      'label' => 'Team Nudges',      'route' => 'team-nudges.index', 'icon' => 'bell',       'roles' => [UserRole::Manager]],
            ['key' => 'categories',       'label' => 'Services',         'route' => 'services.index',   'icon' => 'tag',        'roles' => [UserRole::Manager]],
            ['key' => 'festivals',        'label' => 'Festivals',        'route' => 'festivals.index',  'icon' => 'calendar',   'roles' => [UserRole::Manager]],
            ['key' => 'subscriptions',    'label' => 'Subscriptions',    'route' => 'subscriptions.index', 'icon' => 'credit-card', 'roles' => []], // admin only
            ['key' => 'expenses',         'label' => 'Expenses',         'route' => 'expenses.index',   'icon' => 'banknotes',  'roles' => [UserRole::Manager, UserRole::Accounts]],
            ['key' => 'users',            'label' => 'Users',            'route' => 'users.index',      'icon' => 'users',      'roles' => []], // admin only
            ['key' => 'menu-controller',  'label' => 'Menu Controller',  'route' => 'menu-controller',  'icon' => 'sliders',    'roles' => []], // admin only
        ];
    }

    public function run(): void
    {
        foreach ($this->items as $sort => $data) {
            $item = MenuItem::updateOrCreate(
                ['key' => $data['key']],
                [
                    'label' => $data['label'],
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

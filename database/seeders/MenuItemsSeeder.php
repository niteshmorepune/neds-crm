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
        $all = [UserRole::Manager, UserRole::Sales, UserRole::Support, UserRole::Accounts];

        $this->items = [
            ['key' => 'dashboard',        'label' => 'Dashboard',        'route' => 'dashboard',        'icon' => 'dashboard',  'roles' => $all],
            ['key' => 'attendance',       'label' => 'Attendance',       'route' => 'attendance',       'icon' => 'clock',      'roles' => $all],
            ['key' => 'lead-generation',  'label' => 'Lead Generation',  'route' => 'lead-generation',  'icon' => 'funnel',     'roles' => [UserRole::Manager, UserRole::Sales]],
            ['key' => 'sales-department', 'label' => 'Sales Department',  'route' => 'sales-department', 'icon' => 'trending',   'roles' => [UserRole::Manager, UserRole::Sales]],
            ['key' => 'account',          'label' => 'Account',          'route' => 'account',          'icon' => 'wallet',     'roles' => [UserRole::Manager, UserRole::Accounts]],
            ['key' => 'project-updates',  'label' => 'Project Updates',  'route' => 'project-updates',  'icon' => 'briefcase',  'roles' => [UserRole::Manager, UserRole::Sales, UserRole::Support]],
            ['key' => 'categories',       'label' => 'Categories',       'route' => 'categories',       'icon' => 'tag',        'roles' => [UserRole::Manager]],
            ['key' => 'quotations',       'label' => 'Quotations',       'route' => 'quotations',       'icon' => 'document',   'roles' => [UserRole::Manager, UserRole::Sales, UserRole::Accounts]],
            ['key' => 'customer',         'label' => 'Clients',          'route' => 'clients.index',    'icon' => 'users',      'roles' => $all],
            ['key' => 'invoices',         'label' => 'Invoices',         'route' => 'invoices',         'icon' => 'receipt',    'roles' => [UserRole::Manager, UserRole::Accounts]],
            ['key' => 'calling',          'label' => 'Calling',          'route' => 'calling',          'icon' => 'phone',      'roles' => [UserRole::Manager, UserRole::Sales, UserRole::Support]],
            ['key' => 'emptask',          'label' => 'Emptask',          'route' => 'emptask',          'icon' => 'check',      'roles' => $all],
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

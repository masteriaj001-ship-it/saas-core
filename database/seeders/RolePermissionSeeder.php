<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'create_work_orders', 'edit_work_orders', 'delete_work_orders', 'view_work_orders',
            'create_assets', 'edit_assets', 'delete_assets', 'view_assets',
            'create_items', 'edit_items', 'delete_items', 'view_items',
            'create_contacts', 'edit_contacts', 'delete_contacts', 'view_contacts',
            'create_transactions', 'edit_transactions', 'delete_transactions', 'view_transactions',
        ];

        foreach ($permissions as $perm) {
            Permission::findOrCreate($perm);
        }

        $owner  = Role::findOrCreate('owner');
        $admin  = Role::findOrCreate('admin');
        $editor = Role::findOrCreate('editor');
        $viewer = Role::findOrCreate('viewer');

        $owner->givePermissionTo(Permission::all());

        $admin->givePermissionTo(
            Permission::where('name', 'not like', 'delete_%')->get()
        );

        $editor->givePermissionTo(
            Permission::whereIn('name', [
                'view_work_orders', 'create_work_orders', 'edit_work_orders',
                'view_assets', 'create_assets', 'edit_assets',
                'view_items', 'create_items', 'edit_items',
                'view_contacts', 'create_contacts', 'edit_contacts',
                'view_transactions', 'create_transactions', 'edit_transactions',
            ])->get()
        );

        $viewer->givePermissionTo(
            Permission::where('name', 'like', 'view_%')->get()
        );
    }
}

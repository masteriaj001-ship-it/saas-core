<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $models = [
            'work_orders', 'assets', 'items', 'contacts', 'transactions',
            'invoices', 'budgets', 'budget_items', 'locations', 'service_catalogs',
            'work_order_checklist_items', 'document_sequences', 'sms_codes',
            'warehouses', 'stock_movements', 'credit_accounts', 'credit_transactions',
            'suppliers', 'purchase_orders', 'price_lists', 'appointments', 'workshop_bays',
        ];

        $actions = ['view', 'create', 'edit', 'delete'];

        $permissions = [];
        foreach ($models as $model) {
            foreach ($actions as $action) {
                $permissions[] = "{$action}_{$model}";
            }
        }

        foreach ($permissions as $perm) {
            Permission::findOrCreate($perm);
        }

        $owner = Role::findOrCreate('owner');
        $admin = Role::findOrCreate('admin');
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
                'view_invoices', 'create_invoices', 'edit_invoices',
                'view_budgets', 'create_budgets', 'edit_budgets',
                'view_budget_items', 'create_budget_items', 'edit_budget_items',
                'view_locations', 'create_locations', 'edit_locations',
                'view_service_catalogs', 'create_service_catalogs', 'edit_service_catalogs',
                'view_work_order_checklist_items', 'create_work_order_checklist_items', 'edit_work_order_checklist_items',
                'view_document_sequences', 'create_document_sequences', 'edit_document_sequences',
                'view_sms_codes', 'create_sms_codes', 'edit_sms_codes',
                'view_warehouses', 'create_warehouses', 'edit_warehouses',
                'view_stock_movements', 'create_stock_movements', 'edit_stock_movements',
                'view_suppliers', 'create_suppliers', 'edit_suppliers',
                'view_purchase_orders', 'create_purchase_orders', 'edit_purchase_orders',
                'view_price_lists', 'create_price_lists', 'edit_price_lists',
                'view_appointments', 'create_appointments', 'edit_appointments',
                'view_workshop_bays', 'create_workshop_bays', 'edit_workshop_bays',
            ])->get()
        );

        $viewer->givePermissionTo(
            Permission::where('name', 'like', 'view_%')->get()
        );
    }
}

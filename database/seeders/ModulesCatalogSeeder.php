<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\ModuleCatalog;
use Illuminate\Database\Seeder;

class ModulesCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $modules = [
            ['slug' => 'inventory',     'name' => 'Inventario',       'description' => 'Gestión de inventario y productos'],
            ['slug' => 'transactions',  'name' => 'Transacciones',    'description' => 'Facturación y transacciones'],
            ['slug' => 'contacts',      'name' => 'Contactos',        'description' => 'Gestión de clientes y proveedores'],
            ['slug' => 'work_orders',   'name' => 'Órdenes de trabajo', 'description' => 'Gestión de órdenes de servicio'],
            ['slug' => 'reports',       'name' => 'Reportes',         'description' => 'Reportes y análisis'],
        ];

        foreach ($modules as $module) {
            ModuleCatalog::firstOrCreate(
                ['slug' => $module['slug']],
                $module
            );
        }
    }
}

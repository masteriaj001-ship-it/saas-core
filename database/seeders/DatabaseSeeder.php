<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Contact;
use App\Models\Item;
use App\Models\Tenant;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\User;
use App\Modules\Talleres\Models\Asset;
use App\Modules\Talleres\Models\WorkOrder;
use App\Modules\Talleres\Models\WorkOrderItem;
use App\Services\TenantManager;
use App\Services\Transactions\TransactionService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(TenantManager $tenantManager): void
    {
        if (! app()->environment('local', 'testing')) {
            $this->command?->warn('DatabaseSeeder solo se ejecuta en local/testing.');

            return;
        }

        DB::statement('SET session_replication_role = replica');

        $this->cleanTables();

        $tenant = Tenant::create([
            'name' => 'Taller Mecánica Demo',
            'slug' => 'demo-taller',
            'plan' => 'pro',
            'is_active' => true,
            'settings' => [
                'timezone' => 'America/Bogota',
                'currency' => 'COP',
            ],
        ]);

        $tenantManager->setTenantContext($tenant->id);

        $this->call(RolePermissionSeeder::class);

        $admin = User::create([
            'name' => 'Admin Demo',
            'email' => 'admin@demo.com',
            'password' => Hash::make('password'),
        ]);

        $admin->assignRole('owner');

        $this->seedAssets();
        $this->seedItems();
        $this->seedContacts();
        $this->seedWorkOrders();
        $this->seedTransactions();

        $tenantManager->clearTenantContext();

        DB::statement('SET session_replication_role = origin');

        $this->command?->info('Demo data seeded successfully.');
    }

    private function cleanTables(): void
    {
        TransactionItem::query()->forceDelete();
        Transaction::query()->forceDelete();
        WorkOrderItem::query()->forceDelete();
        WorkOrder::query()->forceDelete();
        Item::query()->forceDelete();
        Asset::query()->forceDelete();
        Contact::query()->forceDelete();

        DB::table('model_has_roles')->delete();
        DB::table('model_has_permissions')->delete();
        DB::table('role_has_permissions')->delete();
        DB::table('roles')->delete();
        DB::table('permissions')->delete();

        User::query()->forceDelete();
        Tenant::query()->forceDelete();
    }

    private function seedAssets(): void
    {
        $assets = [
            ['name' => 'iPhone 15 Pro Max',            'code' => 'ASSET-001', 'asset_type' => 'phones',    'status' => 'active',      'acquired_at' => '2024-09-20'],
            ['name' => 'Samsung Galaxy S24 Ultra',     'code' => 'ASSET-002', 'asset_type' => 'phones',    'status' => 'active',      'acquired_at' => '2024-02-15'],
            ['name' => 'Xiaomi Redmi Note 13',          'code' => 'ASSET-003', 'asset_type' => 'phones',    'status' => 'maintenance', 'acquired_at' => '2023-12-10'],
            ['name' => 'MacBook Pro 16" M3 Max',        'code' => 'ASSET-004', 'asset_type' => 'computers', 'status' => 'active',      'acquired_at' => '2024-01-05'],
            ['name' => 'Dell Latitude 5540',            'code' => 'ASSET-005', 'asset_type' => 'computers', 'status' => 'active',      'acquired_at' => '2023-06-20'],
            ['name' => 'HP EliteDesk 800 G9',           'code' => 'ASSET-006', 'asset_type' => 'computers', 'status' => 'active',      'acquired_at' => '2023-09-15'],
            ['name' => 'Lenovo ThinkPad X1 Carbon',     'code' => 'ASSET-007', 'asset_type' => 'computers', 'status' => 'maintenance', 'acquired_at' => '2022-11-30'],
            ['name' => 'Toyota Hilux 2024',             'code' => 'ASSET-008', 'asset_type' => 'vehicles',  'status' => 'active',      'acquired_at' => '2024-03-01'],
            ['name' => 'Chevrolet NKR 2022',             'code' => 'ASSET-009', 'asset_type' => 'vehicles',  'status' => 'disposed',    'acquired_at' => '2022-07-15'],
            ['name' => 'Moto Suzuki V-Strom 650',       'code' => 'ASSET-010', 'asset_type' => 'vehicles',  'status' => 'active',      'acquired_at' => '2023-05-10'],
        ];

        foreach ($assets as $data) {
            Asset::create($data);
        }
    }

    private function seedItems(): void
    {
        $items = [
            ['sku' => 'ITEM-0001', 'name' => 'Filtro de Aceite TO-673',         'item_type' => 'spare',        'unit' => 'piece', 'price' => 25000,   'cost' => 18000,   'stock' => 45,  'min_stock' => 10],
            ['sku' => 'ITEM-0002', 'name' => 'Bujía NGK Iridium',                'item_type' => 'spare',        'unit' => 'piece', 'price' => 18000,   'cost' => 12000,   'stock' => 60,  'min_stock' => 20],
            ['sku' => 'ITEM-0003', 'name' => 'Pastilla de Freno Delantera',      'item_type' => 'spare',        'unit' => 'piece', 'price' => 85000,   'cost' => 55000,   'stock' => 12,  'min_stock' => 15],
            ['sku' => 'ITEM-0004', 'name' => 'Correa de Distribución',           'item_type' => 'spare',        'unit' => 'piece', 'price' => 120000,  'cost' => 85000,   'stock' => 5,   'min_stock' => 8],
            ['sku' => 'ITEM-0005', 'name' => 'Amortiguador Delantero',           'item_type' => 'spare',        'unit' => 'piece', 'price' => 210000,  'cost' => 145000,  'stock' => 3,   'min_stock' => 10],
            ['sku' => 'ITEM-0006', 'name' => 'Batería MAC 42Ah',                 'item_type' => 'spare',        'unit' => 'piece', 'price' => 320000,  'cost' => 240000,  'stock' => 8,   'min_stock' => 5],
            ['sku' => 'ITEM-0007', 'name' => 'Llantas 205/55 R16',               'item_type' => 'spare',        'unit' => 'piece', 'price' => 450000,  'cost' => 320000,  'stock' => 16,  'min_stock' => 8],
            ['sku' => 'ITEM-0008', 'name' => 'Aceite Motor 10W40 (1L)',          'item_type' => 'spare',        'unit' => 'unit',  'price' => 35000,   'cost' => 24000,   'stock' => 120, 'min_stock' => 30],
            ['sku' => 'ITEM-0009', 'name' => 'Refrigerante Verde (1L)',          'item_type' => 'spare',        'unit' => 'unit',  'price' => 22000,   'cost' => 15000,   'stock' => 3,   'min_stock' => 20],
            ['sku' => 'ITEM-0010', 'name' => 'Líquido de Frenos DOT 4',          'item_type' => 'spare',        'unit' => 'unit',  'price' => 28000,   'cost' => 19000,   'stock' => 25,  'min_stock' => 10],
            ['sku' => 'ITEM-0011', 'name' => 'Kit de Herramientas 100pz',        'item_type' => 'product',      'unit' => 'unit',  'price' => 580000,  'cost' => 420000,  'stock' => 7,   'min_stock' => 5],
            ['sku' => 'ITEM-0012', 'name' => 'Gato Hidráulico 3T',               'item_type' => 'product',      'unit' => 'unit',  'price' => 320000,  'cost' => 230000,  'stock' => 4,   'min_stock' => 3],
            ['sku' => 'ITEM-0013', 'name' => 'Lámpara LED Recargable',           'item_type' => 'product',      'unit' => 'unit',  'price' => 45000,   'cost' => 32000,   'stock' => 30,  'min_stock' => 10],
            ['sku' => 'ITEM-0014', 'name' => 'Cargador Batería 12V',             'item_type' => 'product',      'unit' => 'unit',  'price' => 185000,  'cost' => 130000,  'stock' => 2,   'min_stock' => 5],
            ['sku' => 'ITEM-0015', 'name' => 'Compresor de Aire Portátil',       'item_type' => 'product',      'unit' => 'unit',  'price' => 420000,  'cost' => 310000,  'stock' => 6,   'min_stock' => 4],
            ['sku' => 'ITEM-0016', 'name' => 'Caballetes de Seguridad (par)',    'item_type' => 'product',      'unit' => 'piece', 'price' => 156000,  'cost' => 110000,  'stock' => 9,   'min_stock' => 6],
            ['sku' => 'ITEM-0017', 'name' => 'Guantes Mecánico Reforzados',      'item_type' => 'product',      'unit' => 'piece', 'price' => 28000,   'cost' => 19000,   'stock' => 1,   'min_stock' => 20],
            ['sku' => 'ITEM-0018', 'name' => 'Overol Mecánico Talla M',          'item_type' => 'product',      'unit' => 'unit',  'price' => 95000,   'cost' => 68000,   'stock' => 15,  'min_stock' => 10],
            ['sku' => 'ITEM-0019', 'name' => 'Servicio de Scanner Automotriz',   'item_type' => 'service',      'unit' => 'unit',  'price' => 120000,  'cost' => 0,       'stock' => 999, 'min_stock' => 1],
            ['sku' => 'ITEM-0020', 'name' => 'Servicio de Alineación y Balanceo', 'item_type' => 'service',      'unit' => 'unit',  'price' => 80000,   'cost' => 0,       'stock' => 999, 'min_stock' => 1],
            ['sku' => 'ITEM-0021', 'name' => 'Servicio de Cambio de Aceite',     'item_type' => 'service',      'unit' => 'unit',  'price' => 35000,   'cost' => 0,       'stock' => 999, 'min_stock' => 1],
            ['sku' => 'ITEM-0022', 'name' => 'Servicio de Frenos (eje)',         'item_type' => 'service',      'unit' => 'unit',  'price' => 150000,  'cost' => 0,       'stock' => 999, 'min_stock' => 1],
            ['sku' => 'ITEM-0023', 'name' => 'Servicio de Diagnóstico General',  'item_type' => 'service',      'unit' => 'unit',  'price' => 60000,   'cost' => 0,       'stock' => 999, 'min_stock' => 1],
            ['sku' => 'ITEM-0024', 'name' => 'Acero Laminado 3/8" (kg)',         'item_type' => 'raw_material', 'unit' => 'kg',    'price' => 8500,    'cost' => 5500,    'stock' => 200, 'min_stock' => 50],
            ['sku' => 'ITEM-0025', 'name' => 'Pintura Automotriz Azul (1L)',     'item_type' => 'raw_material', 'unit' => 'lt',    'price' => 95000,   'cost' => 65000,   'stock' => 18,  'min_stock' => 10],
        ];

        foreach ($items as $data) {
            Item::create($data);
        }
    }

    private function seedContacts(): void
    {
        $contacts = [
            ['contact_type' => 'client',   'name' => 'Carlos Andrés Martínez',   'email' => 'carlos.martinez@gmail.com',     'phone' => '3001234567', 'tax_id' => '900123456', 'address' => 'Cra 45 #23-12, Bogotá'],
            ['contact_type' => 'client',   'name' => 'María Fernanda López',      'email' => 'maria.lopez@outlook.com',       'phone' => '3102345678', 'tax_id' => '900234567', 'address' => 'Cll 78 #12-34, Medellín'],
            ['contact_type' => 'client',   'name' => 'Pedro Antonio Ramírez',     'email' => 'pedro.ramirez@yahoo.com',       'phone' => '3203456789', 'tax_id' => '900345678', 'address' => 'Av 68 #45-67, Cali'],
            ['contact_type' => 'client',   'name' => 'Ana Cecilia Torres',         'email' => 'ana.torres@hotmail.com',        'phone' => '3004567890', 'tax_id' => '900456789', 'address' => 'Cra 30 #10-20, Barranquilla'],
            ['contact_type' => 'client',   'name' => 'Juan David Pérez',           'email' => 'juan.perez@empresa.co',         'phone' => '3105678901', 'tax_id' => '900567890', 'address' => 'Cll 100 #15-30, Bogotá'],
            ['contact_type' => 'client',   'name' => 'Luisa Fernanda Gómez',       'email' => 'luisa.gomez@gmail.com',         'phone' => '3206789012', 'tax_id' => '900678901', 'address' => 'Cra 50 #8-15, Bucaramanga'],
            ['contact_type' => 'client',   'name' => 'Diego Alejandro Rojas',      'email' => 'diego.rojas@outlook.com',       'phone' => '3007890123', 'tax_id' => '900789012', 'address' => 'Cll 25 #30-45, Pereira'],
            ['contact_type' => 'client',   'name' => 'Laura Carolina Sánchez',     'email' => 'laura.sanchez@gmail.com',       'phone' => '3108901234', 'tax_id' => '900890123', 'address' => 'Cra 15 #20-35, Cartagena'],
            ['contact_type' => 'supplier', 'name' => 'Autorepuestos El Tigre',     'email' => 'ventas@eltigre.com',            'phone' => '3151234567', 'tax_id' => '901234567', 'address' => 'Av Caracas #34-12, Bogotá'],
            ['contact_type' => 'supplier', 'name' => 'Lubricantes Total Colombia',  'email' => 'pedidos@total.co',             'phone' => '3152345678', 'tax_id' => '901234568', 'address' => 'Cra 68 #56-78, Bogotá'],
            ['contact_type' => 'supplier', 'name' => 'Distribuidora Michelin',      'email' => 'ventas@michelin.co',           'phone' => '3153456789', 'tax_id' => '901234569', 'address' => 'Av 68 #12-45, Bogotá'],
            ['contact_type' => 'supplier', 'name' => 'Herramientas Industriales SAS', 'email' => 'info@herramientas.com',        'phone' => '3154567890', 'tax_id' => '901234570', 'address' => 'Cll 13 #27-50, Bogotá'],
            ['contact_type' => 'supplier', 'name' => 'Baterías MAC S.A.S.',         'email' => 'comercial@bateriasmac.com',     'phone' => '3155678901', 'tax_id' => '901234571', 'address' => 'Autop Norte #100-20, Bogotá'],
            ['contact_type' => 'employee', 'name' => 'Jorge Enrique Morales',       'email' => 'jorge.morales@taller.com',      'phone' => '3001112233', 'tax_id' => '800111222', 'address' => 'Cra 20 #15-10, Bogotá'],
            ['contact_type' => 'employee', 'name' => 'Ricardo Antonio Castro',      'email' => 'ricardo.castro@taller.com',     'phone' => '3102223344', 'tax_id' => '800333444', 'address' => 'Cll 40 #22-18, Bogotá'],
        ];

        foreach ($contacts as $data) {
            Contact::create($data);
        }
    }

    private function seedWorkOrders(): void
    {
        $assets = Asset::all();
        $clients = Contact::where('contact_type', 'client')->get();
        $items = Item::all();

        $workOrders = [
            ['title' => 'Cambio de aceite y filtros',            'description' => 'Cambio de aceite 10W40 y filtro de aceite. Revisión general.',                        'status' => 'completed',   'priority' => 'normal',  'asset_idx' => 0, 'contact_idx' => 0, 'item_indices' => [[0, 1], [7, 4]]],
            ['title' => 'Revisión de frenos delanteros',         'description' => 'Inspección y cambio de pastillas de freno delanteras.',                                 'status' => 'completed',   'priority' => 'high',    'asset_idx' => 0, 'contact_idx' => 1, 'item_indices' => [[2, 1]]],
            ['title' => 'Mantenimiento correctivo elevador',     'description' => 'Fuga de aceite hidráulico en elevador 2T. Reparación urgente.',                          'status' => 'completed',   'priority' => 'urgent',  'asset_idx' => 3, 'contact_idx' => 2, 'item_indices' => [[13, 1], [23, 5]]],
            ['title' => 'Diagnóstico eléctrico vehículo',       'description' => 'Vehículo no enciende. Diagnóstico completo del sistema eléctrico.',                       'status' => 'completed',   'priority' => 'high',    'asset_idx' => 1, 'contact_idx' => 3, 'item_indices' => [[18, 1]]],
            ['title' => 'Alineación y balanceo completo',        'description' => 'Alineación y balanceo de las 4 llantas. Cliente reporta vibraciones.',                  'status' => 'completed',   'priority' => 'normal',  'asset_idx' => 0, 'contact_idx' => 4, 'item_indices' => [[19, 1]]],
            ['title' => 'Cambio de correa de distribución',      'description' => 'Reemplazo de correa de distribución y tensor. Mantenimiento preventivo 60,000km.',        'status' => 'in_progress', 'priority' => 'high',    'asset_idx' => 1, 'contact_idx' => 1, 'item_indices' => [[3, 1], [0, 1], [7, 3]]],
            ['title' => 'Reparación de suspensión delantera',   'description' => 'Cambio de amortiguadores delanteros y rotulación.',                                       'status' => 'in_progress', 'priority' => 'normal',  'asset_idx' => 0, 'contact_idx' => 0, 'item_indices' => [[4, 2]]],
            ['title' => 'Revisión general moto',                'description' => 'Puesta a punto de moto Suzuki GN 125. Cambio de bujía y aceite.',                         'status' => 'in_progress', 'priority' => 'normal',  'asset_idx' => 2, 'contact_idx' => 5, 'item_indices' => [[1, 1], [7, 2]]],
            ['title' => 'Cambio de llantas',                     'description' => 'Montaje y balanceo de 2 llantas 205/55 R16.',                                             'status' => 'in_progress', 'priority' => 'normal',  'asset_idx' => 0, 'contact_idx' => 6, 'item_indices' => [[6, 2]]],
            ['title' => 'Reparación de compresor',              'description' => 'Compresor de aire 200L no presuriza. Revisión de válvulas y sellos.',                     'status' => 'in_progress', 'priority' => 'high',    'asset_idx' => 4, 'contact_idx' => 6, 'item_indices' => [[14, 1]]],
            ['title' => 'Instalación de scanner automotriz',    'description' => 'Configuración y calibración del scanner Launch en taller.',                               'status' => 'in_progress', 'priority' => 'low',     'asset_idx' => 7, 'contact_idx' => 7, 'item_indices' => []],
            ['title' => 'Servicio de pintura puerta derecha',   'description' => 'Reparación de abolladura y pintura de puerta derecha.',                                    'status' => 'in_progress', 'priority' => 'normal',  'asset_idx' => 1, 'contact_idx' => 2, 'item_indices' => [[24, 1], [16, 2]]],
            ['title' => 'Mantenimiento preventivo torno CNC',   'description' => 'Lubricación general y calibración de ejes del torno CNC.',                                'status' => 'in_progress', 'priority' => 'normal',  'asset_idx' => 5, 'contact_idx' => 4, 'item_indices' => []],
            ['title' => 'Cambio de batería',                    'description' => 'Vehículo no arranca en frío. Diagnosis: batería descargada. Reemplazo.',                   'status' => 'in_progress', 'priority' => 'urgent',  'asset_idx' => 1, 'contact_idx' => 3, 'item_indices' => [[5, 1]]],
            ['title' => 'Revisión de líquidos general',         'description' => 'Revisión y cambio de refrigerante y líquido de frenos.',                                   'status' => 'in_progress', 'priority' => 'normal',  'asset_idx' => 0, 'contact_idx' => 5, 'item_indices' => [[8, 3], [9, 2]]],
            ['title' => 'Cambio de embrague',                   'description' => 'Kit de embrague completo para Toyota Hilux.',                                             'status' => 'draft',       'priority' => 'normal',  'asset_idx' => 0, 'contact_idx' => 0, 'item_indices' => []],
            ['title' => 'Reparación de soldadura',              'description' => 'Soldadura de soporte trasero en camión Chevrolet.',                                       'status' => 'draft',       'priority' => 'low',     'asset_idx' => 1, 'contact_idx' => 7, 'item_indices' => [[23, 2]]],
            ['title' => 'Diagnóstico de motor',                'description' => 'Cliente reporta ruido extraño en motor. Scanner y comprobación mecánica.',                  'status' => 'draft',       'priority' => 'high',    'asset_idx' => 0, 'contact_idx' => 6, 'item_indices' => [[18, 1]]],
            ['title' => 'Mantenimiento correctivo moto',        'description' => 'Freno trasero no responde correctamente. Revisión y ajuste.',                              'status' => 'draft',       'priority' => 'normal',  'asset_idx' => 2, 'contact_idx' => 1, 'item_indices' => [[2, 1]]],
            ['title' => 'Revisión general post-venta',          'description' => 'Revisión completa después de reparación mayor. Garantía de 30 días.',                      'status' => 'draft',       'priority' => 'low',     'asset_idx' => 1, 'contact_idx' => 3, 'item_indices' => []],
        ];

        $woPrefix = 'WO-';

        foreach ($workOrders as $i => $wo) {
            $asset = $assets[$wo['asset_idx']] ?? $assets[0];
            $contact = $clients[$wo['contact_idx']] ?? $clients[0];

            $num = $i + 1;
            $code = $woPrefix.str_pad((string) $num, 4, '0', STR_PAD_LEFT);

            $startedAt = null;
            $completedAt = null;

            if ($wo['status'] === 'in_progress') {
                $startedAt = now()->subDays(rand(1, 15));
            } elseif ($wo['status'] === 'completed') {
                $startedAt = now()->subDays(rand(10, 30));
                $completedAt = now()->subDays(rand(1, 9));
            }

            /** @var WorkOrder $workOrder */
            $workOrder = WorkOrder::create([
                'asset_id' => $asset->id,
                'contact_id' => $contact->id,
                'code' => $code,
                'title' => $wo['title'],
                'description' => $wo['description'],
                'status' => $wo['status'],
                'priority' => $wo['priority'],
                'started_at' => $startedAt,
                'completed_at' => $completedAt,
            ]);

            foreach ($wo['item_indices'] as [$itemIdx, $qty]) {
                $item = $items[$itemIdx] ?? null;
                if (! $item) {
                    continue;
                }

                WorkOrderItem::create([
                    'work_order_id' => $workOrder->id,
                    'item_id' => $item->id,
                    'quantity' => $qty,
                    'unit_price' => $item->price,
                    'description' => null,
                ]);
            }
        }
    }

    private function seedTransactions(): void
    {
        $clients = Contact::where('contact_type', 'client')->get();
        $suppliers = Contact::where('contact_type', 'supplier')->get();
        $items = Item::all();
        $admin = User::first();
        $service = app(TransactionService::class);

        $sales = [
            ['contact_idx' => 0, 'status' => 'issued', 'payment_method' => 'transfer', 'items' => [[0, 2, 19], [7, 4, 19]]],
            ['contact_idx' => 1, 'status' => 'issued', 'payment_method' => 'cash',     'items' => [[2, 1, 19], [6, 4, 19], [3, 1, 19]]],
            ['contact_idx' => 2, 'status' => 'issued', 'payment_method' => 'card',     'items' => [[5, 1, 19], [18, 1, 0]]],
            ['contact_idx' => 3, 'status' => 'issued', 'payment_method' => 'transfer', 'items' => [[1, 4, 19], [7, 6, 19], [9, 3, 19]]],
            ['contact_idx' => 4, 'status' => 'issued', 'payment_method' => 'check',    'items' => [[11, 1, 19], [19, 1, 0]]],
            ['contact_idx' => 5, 'status' => 'draft',   'payment_method' => 'cash',     'items' => [[4, 2, 19], [8, 4, 19]]],
            ['contact_idx' => 6, 'status' => 'draft',   'payment_method' => 'credit',   'items' => [[13, 1, 19], [14, 2, 19]]],
            ['contact_idx' => 0, 'status' => 'issued', 'payment_method' => 'transfer', 'items' => [[0, 3, 19], [7, 5, 19], [21, 1, 5]]],
            ['contact_idx' => 1, 'status' => 'cancelled', 'payment_method' => 'cash',   'items' => [[18, 1, 0], [22, 1, 5]]],
            ['contact_idx' => 2, 'status' => 'issued', 'payment_method' => 'card',     'items' => [[19, 1, 5], [23, 5, 0], [20, 2, 0]]],
        ];

        $purchases = [
            ['contact_idx' => 0, 'status' => 'issued', 'payment_method' => 'transfer', 'items' => [[0, 20, 19], [7, 30, 19]]],
            ['contact_idx' => 1, 'status' => 'issued', 'payment_method' => 'credit',   'items' => [[7, 50, 19], [8, 20, 19], [9, 15, 19]]],
            ['contact_idx' => 2, 'status' => 'draft',   'payment_method' => 'transfer', 'items' => [[6, 10, 19], [11, 5, 19]]],
            ['contact_idx' => 3, 'status' => 'issued', 'payment_method' => 'check',    'items' => [[5, 8, 19], [13, 5, 19]]],
            ['contact_idx' => 4, 'status' => 'cancelled', 'payment_method' => 'transfer', 'items' => [[12, 20, 19]]],
        ];

        foreach ($sales as $sale) {
            $contact = $clients[$sale['contact_idx']] ?? $clients[0];
            $itemsData = [];
            foreach ($sale['items'] as [$itemIdx, $qty, $taxRate]) {
                $item = $items[$itemIdx] ?? $items[0];
                $itemsData[] = [
                    'item_id' => $item->id,
                    'quantity' => $qty,
                    'unit_price' => $item->price,
                    'tax_rate' => $taxRate,
                    'tax_amount' => 0,
                    'discount_amount' => 0,
                    'total_item_amount' => 0,
                ];
            }

            $transaction = $service->createWithItems([
                'tenant_id' => Tenant::first()->id,
                'contact_id' => $contact->id,
                'type' => 'sale',
                'status' => 'draft',
                'payment_method' => $sale['payment_method'],
                'notes' => null,
                'created_by' => $admin?->id,
                'total_retentions' => fake()->randomFloat(2, 0, 50000),
            ], $itemsData);

            if ($sale['status'] === 'issued') {
                $service->issue($transaction);
            } elseif ($sale['status'] === 'cancelled') {
                $service->issue($transaction);
                $service->cancel($transaction);
            }
        }

        foreach ($purchases as $purchase) {
            $contact = $suppliers[$purchase['contact_idx']] ?? $suppliers[0];
            $itemsData = [];
            foreach ($purchase['items'] as [$itemIdx, $qty, $taxRate]) {
                $item = $items[$itemIdx] ?? $items[0];
                $itemsData[] = [
                    'item_id' => $item->id,
                    'quantity' => $qty,
                    'unit_price' => $item->cost,
                    'tax_rate' => $taxRate,
                    'tax_amount' => 0,
                    'discount_amount' => 0,
                    'total_item_amount' => 0,
                ];
            }

            $transaction = $service->createWithItems([
                'tenant_id' => Tenant::first()->id,
                'contact_id' => $contact->id,
                'type' => 'purchase',
                'status' => 'draft',
                'payment_method' => $purchase['payment_method'],
                'notes' => null,
                'created_by' => $admin?->id,
                'total_retentions' => fake()->randomFloat(2, 0, 30000),
            ], $itemsData);

            if ($purchase['status'] === 'issued') {
                $service->issue($transaction);
            } elseif ($purchase['status'] === 'cancelled') {
                $service->issue($transaction);
                $service->cancel($transaction);
            }
        }
    }
}

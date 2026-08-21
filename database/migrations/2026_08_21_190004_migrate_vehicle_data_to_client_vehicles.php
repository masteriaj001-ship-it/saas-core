<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Migrar vehículos de clientes desde assets a client_vehicles
        DB::unprepared("
            INSERT INTO client_vehicles (
                id, tenant_id, owner_contact_id, plate, brand, model, version, year,
                vin, engine_number, color, fuel_type, vehicle_type, current_mileage,
                metadata, created_at, updated_at
            )
            SELECT
                gen_random_uuid(), tenant_id, owner_contact_id, plate, brand, model, version, year,
                vin, engine_number, color, fuel_type, vehicle_type, current_mileage,
                COALESCE(metadata, '{}'::jsonb), created_at, updated_at
            FROM assets
            WHERE asset_type = 'vehicle'
              AND deleted_at IS NULL;
        ");

        // Actualizar work_orders para apuntar a client_vehicles
        DB::unprepared("
            UPDATE work_orders
            SET client_vehicle_id = cv.id
            FROM client_vehicles cv, assets a
            WHERE work_orders.asset_id = a.id
              AND work_orders.asset_id IS NOT NULL
              AND a.asset_type = 'vehicle'
              AND a.deleted_at IS NULL
              AND cv.plate = a.plate
              AND cv.tenant_id = a.tenant_id;
        ");
    }

    public function down(): void
    {
        // No revertir - los datos permanecen en ambas tablas
    }
};

<?php

declare(strict_types=1);

namespace App\Modules\Talleres\Actions;

use App\Enums\WorkOrderStatusEnum;
use App\Models\Contact;
use App\Modules\Talleres\Models\Asset;
use App\Modules\Talleres\Models\WorkOrder;
use App\Modules\Talleres\Services\WorkOrderCodeGenerator;
use App\Services\TenantManager;
use Illuminate\Support\Facades\DB;

final class CreateWorkOrderReceptionAction
{
    public function __construct(
        private readonly TenantManager $tenantManager,
        private readonly WorkOrderCodeGenerator $codeGenerator,
    ) {}

    public function execute(array $data): WorkOrder
    {
        $tenantId = $this->tenantManager->getCurrentTenantId();

        return DB::transaction(function () use ($data, $tenantId) {

            $contact = $this->resolveContact($data, $tenantId);

            $asset = $this->resolveAsset($data, $tenantId);

            $code = $this->codeGenerator->next();

            return WorkOrder::create([
                'tenant_id' => $tenantId,
                'contact_id' => $contact?->id,
                'asset_id' => $asset?->id,
                'code' => $code,
                'title' => $data['title'] ?? sprintf('Recepción: %s', $asset?->name ?? 'Sin vehículo'),
                'status' => WorkOrderStatusEnum::Received,
                'priority' => $data['priority'] ?? 'medium',
                'mileage_km' => $data['mileage_km'] ?? null,
                'battery_level' => $data['battery_level'] ?? null,
                'aesthetic_notes' => $data['aesthetic_notes'] ?? null,
                'reception_notes' => $data['reception_notes'] ?? null,
                'fuel_level' => $data['fuel_level'] ?? null,
                'service_description' => $data['service_description'] ?? null,
                'metadata' => [
                    'source' => 'quick_reception',
                    'mileage_km' => $data['mileage_km'] ?? null,
                    'battery_level' => $data['battery_level'] ?? null,
                    'aesthetic_notes' => $data['aesthetic_notes'] ?? null,
                ],
            ]);
        });
    }

    private function resolveContact(array $data, string $tenantId): ?Contact
    {
        if (isset($data['contact_id'])) {
            return Contact::query()
                ->tenant()
                ->where('id', $data['contact_id'])
                ->first();
        }

        $phone = $data['contact_phone'] ?? null;
        $documentNumber = $data['contact_document_number'] ?? null;
        $name = $data['contact_name'] ?? null;

        if (! $phone && ! $documentNumber && ! $name) {
            return null;
        }

        $query = Contact::query()->tenant()->where('tenant_id', $tenantId);

        if ($phone) {
            $query->where('phone', $phone);
        } elseif ($documentNumber) {
            $query->where('document_number', $documentNumber);
        }

        $existing = $query->first();
        if ($existing) {
            return $existing;
        }

        if (! $name) {
            return null;
        }

        return Contact::create([
            'tenant_id' => $tenantId,
            'name' => $name,
            'phone' => $phone,
            'document_number' => $documentNumber,
            'contact_type' => 'client',
        ]);
    }

    private function resolveAsset(array $data, string $tenantId): ?Asset
    {
        if (isset($data['asset_id'])) {
            return Asset::query()
                ->tenant()
                ->where('id', $data['asset_id'])
                ->first();
        }

        $plate = $data['asset_plate'] ?? null;
        $name = $data['asset_name'] ?? null;
        $brand = $data['asset_brand'] ?? null;
        $model = $data['asset_model'] ?? null;

        if (! $plate && ! $name) {
            return null;
        }

        $query = Asset::query()->tenant()->where('tenant_id', $tenantId);

        if ($plate) {
            $query->where('plate', $plate);
        }

        $existing = $query->first();
        if ($existing) {
            return $existing;
        }

        if (! $name && ! $plate) {
            return null;
        }

        return Asset::create([
            'tenant_id' => $tenantId,
            'name' => $name ?? ($plate ? "Vehículo {$plate}" : 'Sin nombre'),
            'plate' => $plate,
            'brand' => $brand,
            'model' => $model,
            'asset_type' => 'vehicle',
        ]);
    }
}

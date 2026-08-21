<?php

declare(strict_types=1);

namespace App\Modules\Budget\Services;

use App\Models\Contact;
use App\Modules\Budget\Models\Budget;
use App\Modules\Talleres\Models\ClientVehicle;
use App\Modules\Talleres\Models\WorkOrder;
use App\Modules\Talleres\Services\WorkOrderCodeGenerator;
use Illuminate\Support\Facades\DB;

class BudgetConversionService
{
    public function __construct(
        private readonly WorkOrderCodeGenerator $codeGenerator,
    ) {}

    public function convert(Budget $budget): WorkOrder
    {
        return DB::transaction(function () use ($budget) {
            $contact = $this->resolveContact($budget);

            $clientVehicle = ClientVehicle::create([
                'tenant_id' => $budget->tenant_id,
                'owner_contact_id' => $contact->id,
                'brand' => $budget->vehicle_data['make'] ?? null,
                'model' => $budget->vehicle_data['model'] ?? null,
                'plate' => $budget->vehicle_data['plate'] ?? null,
                'year' => $budget->vehicle_data['year'] ?? null,
                'color' => $budget->vehicle_data['color'] ?? null,
            ]);

            $workOrder = WorkOrder::create([
                'tenant_id' => $budget->tenant_id,
                'code' => $this->codeGenerator->next(),
                'contact_id' => $contact->id,
                'client_vehicle_id' => $clientVehicle->id,
                'status' => 'received',
                'title' => "OT desde {$budget->code}",
                'service_description' => $budget->notes,
            ]);

            foreach ($budget->items as $item) {
                $workOrder->items()->create([
                    'tenant_id' => $budget->tenant_id,
                    'description' => $item->description,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'discount' => $item->discount,
                    'type' => 'service',
                ]);
            }

            $budget->update([
                'converted_to_work_order_id' => $workOrder->id,
                'contact_id' => $contact->id,
            ]);

            return $workOrder;
        });
    }

    private function resolveContact(Budget $budget): Contact
    {
        if ($budget->contact_id) {
            return $budget->contact;
        }

        $contact = null;

        if ($budget->contact_phone) {
            $contact = Contact::where('tenant_id', $budget->tenant_id)
                ->where('phone', $budget->contact_phone)
                ->first();
        }

        if (! $contact && $budget->contact_email) {
            $contact = Contact::where('tenant_id', $budget->tenant_id)
                ->where('email', $budget->contact_email)
                ->first();
        }

        if ($contact) {
            return $contact;
        }

        return Contact::create([
            'tenant_id' => $budget->tenant_id,
            'contact_type' => 'client',
            'name' => $budget->contact_name,
            'phone' => $budget->contact_phone,
            'email' => $budget->contact_email,
        ]);
    }
}

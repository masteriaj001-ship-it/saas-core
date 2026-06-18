<?php

declare(strict_types=1);

namespace App\Modules\Facturacion\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'document_type' => $this->document_type->value,
            'document_number' => $this->document_number,
            'status' => $this->status->value,
            'issued_at' => $this->issued_at,
            'due_at' => $this->due_at,
            'subtotal' => (float) $this->subtotal,
            'discount_total' => (float) $this->discount_total,
            'tax_total' => (float) $this->tax_total,
            'grand_total' => (float) $this->grand_total,
            'notes' => $this->notes,
            'cufe' => $this->cufe,
            'qr_code_url' => $this->qr_code_url,
            'contact' => new ContactResource($this->whenLoaded('contact')),
            'items' => InvoiceItemResource::collection($this->whenLoaded('items')),
            'work_order_id' => $this->work_order_id,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}

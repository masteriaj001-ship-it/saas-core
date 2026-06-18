<?php

declare(strict_types=1);

namespace App\Modules\Facturacion\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateInvoiceRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['nullable', 'array', 'min:1'],
            'items.*.description' => ['required_with:items', 'string', 'max:500'],
            'items.*.quantity' => ['required_with:items', 'numeric', 'min:0.01'],
            'items.*.unit_price' => ['required_with:items', 'numeric', 'min:0'],
            'items.*.discount' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $invoice = $this->route('invoice');

            if ($invoice && $invoice->status->value !== 'draft') {
                $validator->errors()->add(
                    'invoice',
                    'Solo documentos en borrador pueden modificarse.'
                );
            }
        });
    }
}

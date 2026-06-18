<?php

declare(strict_types=1);

namespace App\Modules\Facturacion\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CancelInvoiceRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:500'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $invoice = $this->route('invoice');

            if ($invoice && $invoice->status->value !== 'issued') {
                $validator->errors()->add(
                    'invoice',
                    'Solo documentos emitidos pueden cancelarse.'
                );
            }
        });
    }
}

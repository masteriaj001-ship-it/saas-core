<?php

declare(strict_types=1);

namespace App\Http\Requests\WorkOrders;

use App\Models\WorkOrder;
use Illuminate\Foundation\Http\FormRequest;

class CreateWorkOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', WorkOrder::class);
    }

    public function rules(): array
    {
        return [
            'asset_id' => ['required', 'uuid', 'exists:assets,id'],
            'contact_id' => ['nullable', 'uuid', 'exists:contacts,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'priority' => ['nullable', 'string', 'in:low,normal,high,urgent'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}

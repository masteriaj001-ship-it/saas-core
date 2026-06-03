<?php

declare(strict_types=1);

namespace App\Http\Requests\WorkOrders;

use Illuminate\Foundation\Http\FormRequest;

class UpdateWorkOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('work_order'));
    }

    public function rules(): array
    {
        return [
            'contact_id' => ['nullable', 'uuid', 'exists:contacts,id'],
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'priority' => ['nullable', 'string', 'in:low,normal,high,urgent'],
            'status' => ['sometimes', 'string', 'in:draft,in_progress,completed,cancelled'],
            'started_at' => ['nullable', 'date'],
            'completed_at' => ['nullable', 'date'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}

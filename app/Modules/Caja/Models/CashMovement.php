<?php

declare(strict_types=1);

namespace App\Modules\Caja\Models;

use App\Models\Tenant;
use App\Models\User;
use App\Modules\Facturacion\Models\Invoice;
use App\Modules\Talleres\Models\WorkOrder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashMovement extends Model
{
    use \App\Models\Concerns\BelongsToTenant, HasUuids;

    protected $fillable = [
        'tenant_id',
        'shift_id',
        'work_order_id',
        'invoice_id',
        'type',
        'payment_method',
        'amount',
        'description',
        'created_by',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'id' => 'string',
            'tenant_id' => 'string',
            'shift_id' => 'string',
            'work_order_id' => 'string',
            'invoice_id' => 'string',
            'amount' => 'decimal:2',
            'metadata' => 'array',
        ];
    }

    protected $guarded = [];

    public function shift(): BelongsTo
    {
        return $this->belongsTo(CashShift::class, 'shift_id');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class, 'work_order_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeOfType($query, string $type): void
    {
        $query->where('type', $type);
    }

    public function scopeByMethod($query, string $method): void
    {
        $query->where('payment_method', $method);
    }

    public function scopeForShift($query, string $shiftId): void
    {
        $query->where('shift_id', $shiftId);
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Caja\Models;

use App\Modules\Talleres\Models\WorkOrder;
use App\Models\Contact;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Database\Model as BaseModel;

class CashMovement extends BaseModel
{
    use \App\Modules\Talleres\Traits\BelongsToTenant;

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

    protected $casts = [
        'tenant_id' => 'uuid',
        'shift_id' => 'uuid',
        'work_order_id' => 'uuid',
        'invoice_id' => 'uuid',
        'type' => 'string',
        'payment_method' => 'string',
        'amount' => 'decimal:2',
        'metadata' => 'array',
    ];

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
        return $this->belongsTo(\App\Modules\Facturacion\Models\Invoice::class, 'invoice_id');
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
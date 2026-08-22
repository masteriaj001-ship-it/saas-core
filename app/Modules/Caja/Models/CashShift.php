<?php

declare(strict_types=1);

namespace App\Modules\Caja\Models;

use App\Enums\WorkOrderStatusEnum;
use App\Modules\Talleres\Models\WorkOrder;
use App\Models\Contact;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Database\Model as BaseModel;

class CashShift extends BaseModel
{
    use \App\Modules\Talleres\Traits\BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'opened_by',
        'closed_by',
        'opened_at',
        'closed_at',
        'initial_amount',
        'expected_cash',
        'actual_cash',
        'difference',
        'status',
        'notes',
        'metadata',
    ];

    protected $casts = [
        'tenant_id' => 'uuid',
        'opened_by' => 'uuid',
        'closed_by' => 'uuid',
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
        'initial_amount' => 'decimal:2',
        'expected_cash' => 'decimal:2',
        'actual_cash' => 'decimal:2',
        'difference' => 'decimal:2',
        'metadata' => 'array',
    ];

    protected $guarded = [];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function openedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function cashMovements(): HasMany
    {
        return $this->hasMany(CashMovement::class, 'shift_id');
    }

    public static function boot(): void
    {
        parent::boot();

        static::creating(function ($shift) {
            if (! $shift->tenant_id) {
                $shift->tenant_id = fn () => Tenant::getCurrent()?->id;
            }
        });
    }

    public function scopeOpen($query): void
    {
        $query->where('status', 'open');
    }

    public function scopeTenantQuery($query): void
    {
        $query->where('tenant_id', fn () => Tenant::current()?->id);
    }

    public function totalSales(): \Illuminate\Database\Eloquent\Casts\DecimalCast
    {
        return $this->cashMovements()
            ->where('type', 'sale')
            ->sum('amount');
    }

    public function totalExpenses(): \Illuminate\Database\Eloquent\Casts\DecimalCast
    {
        return $this->cashMovements()
            ->where('type', 'expense')
            ->sum('amount');
    }

    public function totalCash(): \Illuminate\Database\Eloquent\Casts\DecimalCast
    {
        return $this->cashMovements()
            ->where('type', 'income')
            ->sum('amount');
    }

    public function netAmount(): \Illuminate\Database\Eloquent\Casts\DecimalCast
    {
        return $this->totalSales() - $this->totalExpenses();
    }

    public function close(User $user, float $actualCash, ?string $notes = null): bool
    {
        if ($this->status !== 'open') {
            return false;
        }

        $this->closed_by = $user->id;
        $this->closed_at = now();
        $this->actual_cash = $actualCash;
        $this->difference = $actualCash - $this->expected_cash;
        $this->status = 'closed';
        $this->notes = $notes;

        return $this->save();
    }

    public static function canOpen(): bool
    {
        return ! static::open()->tenantQuery()->exists();
    }
}
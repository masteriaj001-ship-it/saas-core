<?php

declare(strict_types=1);

namespace App\Modules\Caja\Models;

use App\Models\Tenant;
use App\Models\User;
use App\Modules\Caja\Exceptions\TurnoCerradoException;
use App\Services\TenantManager;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CashShift extends Model
{
    use \App\Models\Concerns\BelongsToTenant, HasUuids;

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

    protected function casts(): array
    {
        return [
            'id' => 'string',
            'tenant_id' => 'string',
            'opened_by' => 'string',
            'closed_by' => 'string',
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
            'initial_amount' => 'decimal:2',
            'expected_cash' => 'decimal:2',
            'actual_cash' => 'decimal:2',
            'difference' => 'decimal:2',
            'metadata' => 'array',
        ];
    }

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
                $shift->tenant_id = app(TenantManager::class)->getCurrentTenantId();
            }
        });
    }

    public function scopeOpen($query): void
    {
        $query->where('status', 'open');
    }

    public function scopeTenantQuery($query): void
    {
        $query->where('tenant_id', app(TenantManager::class)->getCurrentTenantId());
    }

    public function totalSales(): float
    {
        return (float) $this->cashMovements()
            ->where('type', 'sale')
            ->sum('amount');
    }

    public function totalExpenses(): float
    {
        return (float) $this->cashMovements()
            ->where('type', 'expense')
            ->sum('amount');
    }

    public function totalCash(): float
    {
        return (float) $this->cashMovements()
            ->where('type', 'income')
            ->sum('amount');
    }

    public function netAmount(): float
    {
        return $this->totalSales() - $this->totalExpenses();
    }

    public function close(User $user, float $actualCash, ?string $notes = null): bool
    {
        $this->refresh();

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

    public static function openShift(User $user, float $initialAmount): static
    {
        if (! static::canOpen()) {
            throw new TurnoCerradoException(
                'Ya existe un turno abierto para este tenant.'
            );
        }

        return static::create([
            'opened_by' => $user->id,
            'initial_amount' => $initialAmount,
            'expected_cash' => $initialAmount,
            'status' => 'open',
            'opened_at' => now(),
        ]);
    }

    public function reopen(): never
    {
        throw new TurnoCerradoException(
            'Un turno cerrado no puede reabrirse.'
        );
    }
}

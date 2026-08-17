<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\InvoiceDocumentTypeEnum;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tenant extends Model
{
    use Auditable, HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'organization_id',
        'name',
        'slug',
        'plan',
        'is_active',
        'settings',
        'onboarding_completed',
    ];

    protected $casts = [
        'id' => 'string',
        'organization_id' => 'string',
        'is_active' => 'boolean',
        'onboarding_completed' => 'boolean',
        'settings' => 'array',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function owner(): BelongsTo
    {
        return $this->organization->owner();
    }

    public function modules(): HasMany
    {
        return $this->hasMany(TenantModule::class);
    }

    public function hasModule(string $moduleSlug): bool
    {
        return $this->modules()
            ->where('module_slug', $moduleSlug)
            ->where('is_active', true)
            ->where(function (Builder $q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->exists();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function esResponsableIva(): bool
    {
        return (bool) ($this->settings['es_responsable_iva'] ?? false);
    }

    public function regimen(): string
    {
        return $this->settings['regimen'] ?? 'declarante';
    }

    public function documentTypeForRegimen(): InvoiceDocumentTypeEnum
    {
        return match ($this->regimen()) {
            'no_declarante' => InvoiceDocumentTypeEnum::Pos,
            default => InvoiceDocumentTypeEnum::Invoice,
        };
    }

    public function posPrinterDriver(): string
    {
        return $this->settings['pos_hardware']['printer_driver'] ?? 'window_print';
    }

    public function posPrinterHost(): string
    {
        return $this->settings['pos_hardware']['printer_host'] ?? '127.0.0.1';
    }

    public function posPrinterPort(): int
    {
        return (int) ($this->settings['pos_hardware']['printer_port'] ?? 9100);
    }

    public function posCashDrawerEnabled(): bool
    {
        return (bool) ($this->settings['pos_hardware']['cash_drawer_after_payment'] ?? true);
    }
}

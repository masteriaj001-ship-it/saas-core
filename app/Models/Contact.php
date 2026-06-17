<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ContactRoleEnum;
use App\Enums\DocumentTypeEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Contact extends TenantModel
{
    use HasFactory;

    protected $fillable = [
        'contact_type',
        'name',
        'tax_id',
        'email',
        'phone',
        'address',
        'metadata',
        'document_type',
        'document_number',
        'city',
        'is_active',
        'blocked_until',
    ];

    protected function casts(): array
    {
        return array_merge(parent::casts(), [
            'metadata' => 'array',
            'document_type' => DocumentTypeEnum::class,
            'is_active' => 'boolean',
            'blocked_until' => 'datetime',
        ]);
    }

    public function roles(): HasMany
    {
        return $this->hasMany(ContactRole::class);
    }

    public function hasRole(ContactRoleEnum $role): bool
    {
        return $this->roles()->where('role_code', $role->value)->exists();
    }

    public function scopeWithRole(Builder $query, ContactRoleEnum $role): Builder
    {
        return $query->whereHas('roles', fn (Builder $q) => $q->where('role_code', $role->value)
        );
    }
}

<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Notifications\ResetPasswordNotification;
use Database\Factories\UserFactory;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthentication;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthenticationRecovery;
use Filament\Auth\MultiFactor\Email\Contracts\HasEmailAuthentication;
use Filament\Models\Contracts\HasTenants;
use Filament\Panel;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Crypt;
use Laravel\Sanctum\HasApiTokens;
use SensitiveParameter;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements HasAppAuthentication, HasAppAuthenticationRecovery, HasEmailAuthentication, HasTenants
{
    /** @use HasFactory<UserFactory> */
    use BelongsToTenant, HasApiTokens, HasFactory, HasRoles, HasUuids, Notifiable, SoftDeletes;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'name',
        'email',
        'password',
        'user_type',
    ];

    protected $guarded = [
        'id',
        'tenant_id',
        'is_superadmin',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'two_factor_confirmed_at',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    protected $casts = [
        'id' => 'string',
        'tenant_id' => 'string',
        'user_type' => 'string',
        'is_superadmin' => 'boolean',
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'two_factor_recovery_codes' => 'array',
        'two_factor_confirmed_at' => 'datetime',
    ];

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }

    public function getTenants(Panel $panel): Collection
    {
        if ($this->is_superadmin) {
            return Tenant::all();
        }

        return collect([$this->tenant])->filter();
    }

    public function canAccessTenant(Model $tenant): bool
    {
        if ($this->is_superadmin) {
            return true;
        }

        return $this->tenant_id === $tenant->id;
    }

    public function getAppAuthenticationSecret(): ?string
    {
        if (blank($this->two_factor_secret)) {
            return null;
        }

        return Crypt::decryptString($this->two_factor_secret);
    }

    public function saveAppAuthenticationSecret(#[SensitiveParameter] ?string $secret): void
    {
        $this->two_factor_secret = $secret !== null
            ? Crypt::encryptString($secret)
            : null;

        $this->save();
    }

    public function getAppAuthenticationHolderName(): string
    {
        return $this->name;
    }

    public function getAppAuthenticationRecoveryCodes(): ?array
    {
        return $this->two_factor_recovery_codes;
    }

    public function saveAppAuthenticationRecoveryCodes(#[SensitiveParameter] ?array $codes): void
    {
        $this->two_factor_recovery_codes = $codes;
        $this->save();
    }

    public function hasEmailAuthentication(): bool
    {
        return $this->two_factor_confirmed_at !== null;
    }

    public function toggleEmailAuthentication(bool $condition): void
    {
        $this->two_factor_confirmed_at = $condition ? now() : null;
        $this->save();
    }
}

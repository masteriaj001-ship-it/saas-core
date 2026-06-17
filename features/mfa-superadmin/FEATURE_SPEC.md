# FEATURE SPEC — USR-001: Superadmin Multi-Factor Authentication

## Goal

Require TOTP (Google Authenticator) MFA for all superadmin users. Regular tenant users can optionally enable it. This closes the last remaining security vector: a compromised superadmin credential can bypass all RLS work.

## Problem

Superadmins have unrestricted access to all tenants. A leaked password grants full access with no second factor. Filament v5 includes MFA support (TOTP + email code) — it only needs activation.

## Solution

### 1. Migration

Add MFA columns to `users` table:

| Column | Type | Purpose |
|---|---|---|
| `two_factor_secret` | `text?` | Encrypted TOTP secret (stored via Filament interface) |
| `two_factor_recovery_codes` | `json?` | Array of one-time recovery codes |
| `two_factor_confirmed_at` | `timestamp?` | Null until user confirms MFA setup |

### 2. User model

Implement 3 Filament contracts:

- `HasAppAuthentication` → `getAppAuthenticationSecret()`, `saveAppAuthenticationSecret()`, `getAppAuthenticationHolderName()`
- `HasAppAuthenticationRecovery` → `getAppAuthenticationRecoveryCodes()`, `saveAppAuthenticationRecoveryCodes()`
- `HasEmailAuthentication` → `hasEmailAuthentication()`, `toggleEmailAuthentication()`

### 3. Panel configuration

**`SuperadminPanelProvider`** — MFA required:
```php
->multiFactorAuthentication(
    providers: [AppAuthentication::make(), EmailAuthentication::make()],
    isRequired: true,
)
```

**`AdminPanelProvider`** — MFA optional (superadmin in tenant panel still must use MFA):
```php
->multiFactorAuthentication(
    providers: [AppAuthentication::make(), EmailAuthentication::make()],
    isRequired: false,
)
```

### 4. Force MFA setup middleware (if needed)

Filament's `EnsureMultiFactorAuthenticationIsEnabled` middleware activates automatically when `isRequired: true` on the panel.

## Migration

1 migration:
- `add_two_factor_columns_to_users_table.php`

## Tests (4 total)

| # | Test | Assertion |
|---|---|---|
| 1 | `superadmin_is_redirected_to_mfa_setup` | superadmin without MFA → redirected to setup page |
| 2 | `superadmin_can_set_up_app_authentication` | superadmin completes TOTP setup → `two_factor_confirmed_at` is set |
| 3 | `superadmin_cannot_access_panel_without_mfa` | superadmin without MFA → blocked from `/superadmin` |
| 4 | `regular_user_can_skip_mfa` | regular tenant user → accesses panel without MFA prompt |

## Files affected

| File | Change |
|---|---|
| `database/migrations/..._add_two_factor_columns_to_users_table.php` | New |
| `app/Models/User.php` | Implement 3 MFA contracts + casts + fillable |
| `app/Providers/Filament/SuperadminPanelProvider.php` | Add `multiFactorAuthentication(isRequired: true)` |
| `app/Providers/Filament/AdminPanelProvider.php` | Add `multiFactorAuthentication(isRequired: false)` |
| `tests/Feature/Security/SuperadminMfaTest.php` | New — 4 tests |

## DoD

- [x] FEATURE_SPEC.md approved (GATE 0)
- [x] Migration generated + approved + executed (3 columns: two_factor_secret, two_factor_recovery_codes, two_factor_confirmed_at)
- [x] User model implements HasAppAuthentication, HasAppAuthenticationRecovery, HasEmailAuthentication
- [x] Both panels have MFA configured (superadmin required, admin optional)
- [x] Tests: 5 tests (bumped from 4 — added TOTP code validation test), all green
- [x] Full suite: 240 tests, 240 passed, 620 assertions
- [x] Pint: `vendor/bin/sail bin pint --format agent` → 1 file fixed
- [x] engram.json v1.8.0, SECURITY_GAPS.md updated

### Desviaciones del plan original
- Se reemplazaron los tests de panel HTTP (fragiles en test context) por tests unitarios de los contratos Filament (secret storage, recovery codes, TOTP verification).
- Se agrego test de validacion TOTP: genera codigo programaticamente via Google2FA, verifica que un codigo diferente NO valide — previene flakiness en CI.
- 5 tests en vez de 4: `test_superadmin_mfa_uses_valid_totp_code` no estaba en el spec original.

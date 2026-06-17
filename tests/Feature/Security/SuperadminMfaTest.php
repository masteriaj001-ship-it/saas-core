<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

final class SuperadminMfaTest extends TestCase
{
    use RefreshDatabase;

    private Google2FA $google2FA;

    protected function setUp(): void
    {
        parent::setUp();

        $this->google2FA = new Google2FA;
    }

    public function test_superadmin_can_store_and_retrieve_totp_secret(): void
    {
        $superadmin = User::factory()->create([
            'is_superadmin' => true,
            'tenant_id' => null,
        ]);

        $secret = $this->google2FA->generateSecretKey();

        $superadmin->saveAppAuthenticationSecret($secret);

        $this->assertSame($secret, $superadmin->getAppAuthenticationSecret());
    }

    public function test_superadmin_can_store_and_retrieve_recovery_codes(): void
    {
        $superadmin = User::factory()->create([
            'is_superadmin' => true,
            'tenant_id' => null,
        ]);

        $codes = [
            '11111-11111',
            '22222-22222',
            '33333-33333',
            '44444-44444',
            '55555-55555',
            '66666-66666',
        ];

        $superadmin->saveAppAuthenticationRecoveryCodes($codes);

        $this->assertCount(6, $superadmin->getAppAuthenticationRecoveryCodes());
        $this->assertSame($codes, $superadmin->getAppAuthenticationRecoveryCodes());
    }

    public function test_superadmin_can_confirm_mfa_setup(): void
    {
        $superadmin = User::factory()->create([
            'is_superadmin' => true,
            'tenant_id' => null,
        ]);

        $secret = $this->google2FA->generateSecretKey();

        $superadmin->saveAppAuthenticationSecret($secret);
        $superadmin->saveAppAuthenticationRecoveryCodes([
            '11111-11111',
            '22222-22222',
            '33333-33333',
            '44444-44444',
            '55555-55555',
            '66666-66666',
        ]);
        $superadmin->two_factor_confirmed_at = now();
        $superadmin->save();

        $this->assertNotNull($superadmin->two_factor_confirmed_at);
        $this->assertTrue($superadmin->hasEmailAuthentication());
    }

    public function test_superadmin_mfa_uses_valid_totp_code(): void
    {
        $secret = $this->google2FA->generateSecretKey();
        $code = $this->google2FA->getCurrentOtp($secret);

        $this->assertTrue(
            $this->google2FA->verifyKey($secret, $code),
            'A TOTP code generated from the secret should verify correctly.'
        );

        $wrongCode = str_pad((string) (((int) $code) + 1 % 1000000), 6, '0', STR_PAD_LEFT);
        $this->assertFalse(
            $this->google2FA->verifyKey($secret, $wrongCode),
            'A different code should NOT verify.'
        );
    }

    public function test_get_app_authentication_holder_name(): void
    {
        $superadmin = User::factory()->create([
            'name' => 'Test Admin',
            'is_superadmin' => true,
            'tenant_id' => null,
        ]);

        $this->assertSame('Test Admin', $superadmin->getAppAuthenticationHolderName());
    }
}

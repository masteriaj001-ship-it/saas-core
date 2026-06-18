<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Http\Responses\LoginResponse;
use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class LoginRedirectTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_is_redirected_to_dashboard(): void
    {
        $tenant = Tenant::factory()->create(['onboarding_completed' => true]);
        $user = User::factory()->client()->create([
            'tenant_id' => $tenant->id,
        ]);

        $request = Request::create('/login', 'POST');
        $request->setUserResolver(fn () => $user);

        $response = (new LoginResponse)->toResponse($request);

        $this->assertEquals(Filament::getUrl(), $response->getTargetUrl());
    }

    public function test_staff_redirect_respects_intended_url(): void
    {
        $tenant = Tenant::factory()->create(['onboarding_completed' => true]);
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'user_type' => 'staff',
        ]);

        $request = Request::create('/login', 'POST');
        $request->setUserResolver(fn () => $user);

        $response = (new LoginResponse)->toResponse($request);

        // Without an intended URL, staff should also get Filament::getUrl() as fallback
        $this->assertEquals(Filament::getUrl(), $response->getTargetUrl());
    }

    public function test_client_user_type_constant(): void
    {
        $tenant = Tenant::factory()->create();
        $staff = User::factory()->create(['tenant_id' => $tenant->id]);
        $client = User::factory()->client()->create(['tenant_id' => $tenant->id]);

        $this->assertEquals('staff', $staff->user_type);
        $this->assertEquals('client', $client->user_type);
    }
}

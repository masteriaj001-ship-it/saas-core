<?php

declare(strict_types=1);

namespace Tests\Feature\Onboarding;

use App\Filament\Pages\Onboarding;
use App\Http\Middleware\EnsureOnboardingIsCompleted;
use App\Models\Category;
use App\Models\Item;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Talleres\Models\Asset;
use App\Services\TenantTemplateSeeder;
use Database\Seeders\ModulesCatalogSeeder;
use Filament\Facades\Filament;
use Filament\FilamentManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Livewire\Livewire;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class OnboardingWizardTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $incompleteTenant;

    private User $incompleteUser;

    private Tenant $completedTenant;

    private User $completedUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ModulesCatalogSeeder::class);

        $this->incompleteTenant = Tenant::factory()->create(['onboarding_completed' => false]);
        $this->incompleteUser = User::factory()->create([
            'tenant_id' => $this->incompleteTenant->id,
            'is_superadmin' => false,
        ]);

        $this->completedTenant = Tenant::factory()->create(['onboarding_completed' => true]);
        $this->completedUser = User::factory()->create([
            'tenant_id' => $this->completedTenant->id,
            'is_superadmin' => false,
        ]);
    }

    private function callMiddleware(
        User $user,
        ?Tenant $tenant,
        string $uri = '/admin/test-tenant/dashboard',
        ?string $routeName = null,
    ): Response {
        $filamentMock = $this->createMock(FilamentManager::class);
        $filamentMock->method('getTenant')->willReturn($tenant);
        Filament::swap($filamentMock);

        $request = Request::create($uri, 'GET');
        $request->setUserResolver(fn () => $user);

        if ($routeName) {
            $route = new Route(['GET', 'POST'], $uri, []);
            $route->name($routeName);
            $request->setRouteResolver(fn () => $route);
        }

        $middleware = new EnsureOnboardingIsCompleted;

        return $middleware->handle($request, fn ($req) => response('OK', 200));
    }

    public function test_unauthenticated_user_cannot_access_onboarding(): void
    {
        $response = $this->callMiddleware(
            $this->incompleteUser,
            null,
        );

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_user_with_completed_onboarding_is_not_redirected(): void
    {
        $response = $this->callMiddleware(
            $this->completedUser,
            $this->completedTenant,
            '/admin/'.$this->completedTenant->slug.'/dashboard',
        );

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_user_without_completed_onboarding_is_redirected_to_wizard(): void
    {
        $response = $this->callMiddleware(
            $this->incompleteUser,
            $this->incompleteTenant,
            '/admin/'.$this->incompleteTenant->slug.'/dashboard',
        );

        $this->assertSame(302, $response->getStatusCode());
        $this->assertStringContainsString('/onboarding', $response->headers->get('Location'));
    }

    public function test_user_cannot_bypass_wizard_by_navigating_to_resource_url(): void
    {
        $response = $this->callMiddleware(
            $this->incompleteUser,
            $this->incompleteTenant,
            '/admin/'.$this->incompleteTenant->slug.'/assets',
        );

        $this->assertSame(302, $response->getStatusCode());
        $this->assertStringContainsString('/onboarding', $response->headers->get('Location'));
    }

    public function test_onboarding_page_itself_is_accessible_without_loop(): void
    {
        $response = $this->callMiddleware(
            $this->incompleteUser,
            $this->incompleteTenant,
            '/admin/'.$this->incompleteTenant->slug.'/onboarding',
            'filament.admin.pages.onboarding',
        );

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_completing_onboarding_marks_tenant_as_completed(): void
    {
        $seeder = $this->app->make(TenantTemplateSeeder::class);
        $seeder->seed($this->incompleteTenant);

        $this->assertTrue($this->incompleteTenant->fresh()->onboarding_completed);
    }

    public function test_template_seeder_creates_defaults(): void
    {
        $tenant = Tenant::factory()->create(['onboarding_completed' => false]);

        $seeder = $this->app->make(TenantTemplateSeeder::class);
        $seeder->seed($tenant);

        $this->assertCount(4, Category::where('tenant_id', $tenant->id)->get());
        $this->assertCount(4, Item::where('tenant_id', $tenant->id)->get());
        $this->assertCount(2, Asset::where('tenant_id', $tenant->id)->get());
        $this->assertTrue($tenant->fresh()->onboarding_completed);
    }

    public function test_onboarding_wizard_has_no_industry_select(): void
    {
        $this->actingAs($this->incompleteUser);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Filament::setTenant($this->incompleteTenant);

        Livewire::test(Onboarding::class)
            ->assertFormFieldDoesNotExist('industry');
    }

    public function test_onboarding_wizard_submits_successfully(): void
    {
        $this->actingAs($this->incompleteUser);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Filament::setTenant($this->incompleteTenant);

        Livewire::test(Onboarding::class)
            ->call('complete')
            ->assertHasNoErrors();

        $this->assertTrue($this->incompleteTenant->fresh()->onboarding_completed);
    }
}

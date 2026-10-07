<?php

declare(strict_types=1);

namespace Tests\Feature\Talleres;

use App\Enums\ContactRoleEnum;
use App\Filament\Resources\ContactResource\Pages\ListContacts;
use App\Models\Contact;
use App\Models\ContactRole;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantManager;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ContactTableTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->for($this->tenant)->create();

        $this->actingAs($this->user);
        app(TenantManager::class)->setTenantContext($this->tenant->id);
        $this->seed(RolePermissionSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->user->assignRole('owner');
        Filament::setCurrentPanel(app('filament')->getPanel('admin'));
        Filament::setTenant($this->tenant);
    }

    public function test_list_renders_contact_with_roles(): void
    {
        $contact = Contact::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        ContactRole::factory()->create([
            'contact_id' => $contact->id,
            'tenant_id' => $this->tenant->id,
            'role_code' => ContactRoleEnum::Mechanic->value,
        ]);

        ContactRole::factory()->create([
            'contact_id' => $contact->id,
            'tenant_id' => $this->tenant->id,
            'role_code' => ContactRoleEnum::ServiceAdvisor->value,
        ]);

        $this->assertCount(2, $contact->fresh()->roles);

        $this->assertCount(2, $contact->fresh()->roles);

        Livewire::test(ListContacts::class)
            ->assertSuccessful()
            ->assertCanSeeTableRecords([$contact])
            ->assertSee('Mecánico')
            ->assertSee('Asesor de Servicio');
    }
}

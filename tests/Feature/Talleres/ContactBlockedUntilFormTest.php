<?php

declare(strict_types=1);

namespace Tests\Feature\Talleres;

use App\Filament\Resources\ContactResource\Pages\EditContact;
use App\Models\Contact;
use App\Models\Organization;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantManager;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

final class ContactBlockedUntilFormTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $user;

    private Contact $contact;

    protected function setUp(): void
    {
        parent::setUp();

        $org = Organization::factory()->create();
        $this->tenant = Tenant::factory()->create([
            'organization_id' => $org->id,
            'is_active' => true,
        ]);
        $this->user = User::factory()->for($this->tenant)->create();

        $this->actingAs($this->user);
        app(TenantManager::class)->setTenantContext($this->tenant->id);
        $this->seed(RolePermissionSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->user->assignRole('owner');
        Filament::setCurrentPanel(app('filament')->getPanel('admin'));
        Filament::setTenant($this->tenant);

        $this->contact = Contact::factory()->client()->create([
            'tenant_id' => $this->tenant->id,
            'metadata' => [],
            'blocked_until' => now()->addDays(4),
        ]);
    }

    protected function tearDown(): void
    {
        app(TenantManager::class)->clearTenantContext();
        parent::tearDown();
    }

    public function test_blocked_until_visible_for_owner(): void
    {
        Livewire::test(EditContact::class, ['record' => $this->contact->id])
            ->assertSuccessful()
            ->assertFormFieldVisible('blocked_until');
    }

    public function test_blocked_until_visible_for_admin(): void
    {
        $admin = User::factory()->for($this->tenant)->create();
        $admin->assignRole('admin');
        $this->actingAs($admin);

        Livewire::test(EditContact::class, ['record' => $this->contact->id])
            ->assertSuccessful()
            ->assertFormFieldVisible('blocked_until');
    }

    public function test_blocked_until_hidden_for_editor(): void
    {
        $editor = User::factory()->for($this->tenant)->create();
        $editor->assignRole('editor');
        $this->actingAs($editor);

        Livewire::test(EditContact::class, ['record' => $this->contact->id])
            ->assertSuccessful()
            ->assertFormFieldHidden('blocked_until');
    }

    public function test_clearing_blocked_until_persists_null(): void
    {
        Livewire::test(EditContact::class, ['record' => $this->contact->id])
            ->fillForm(['blocked_until' => null])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('contacts', [
            'id' => $this->contact->id,
            'blocked_until' => null,
        ]);

        $this->assertNull($this->contact->fresh()->blocked_until);
    }
}

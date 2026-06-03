<?php

declare(strict_types=1);

namespace Tests\Feature\Onboarding;

use App\Models\Category;
use App\Models\Contact;
use App\Models\Item;
use App\Models\Location;
use App\Models\TenantModule;
use App\Models\User;
use Database\Seeders\ModulesCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationWithDefaultsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ModulesCatalogSeeder::class);
    }

    public function test_creates_default_location(): void
    {
        $this->post(route('register'), [
            'name' => 'Test User',
            'business_name' => 'Test Business',
            'email' => 'location@example.com',
            'password' => 'SecurePass1!',
            'password_confirmation' => 'SecurePass1!',
        ]);

        $user = User::where('email', 'location@example.com')->first();

        $location = Location::where('tenant_id', $user->tenant_id)->first();
        $this->assertNotNull($location);
        $this->assertEquals('Sede Principal', $location->name);
        $this->assertTrue($location->is_main);
        $this->assertTrue($location->is_active);
    }

    public function test_creates_industry_categories(): void
    {
        $this->post(route('register'), [
            'name' => 'Test User',
            'business_name' => 'Mecánico Taller',
            'email' => 'mechanic@example.com',
            'industry' => 'mechanic',
            'password' => 'SecurePass1!',
            'password_confirmation' => 'SecurePass1!',
        ]);

        $user = User::where('email', 'mechanic@example.com')->first();
        $categories = Category::where('tenant_id', $user->tenant_id)->get();

        $this->assertCount(4, $categories);
        $this->assertEquals('Repuestos', $categories[0]->name);
    }

    public function test_creates_industry_items(): void
    {
        $this->post(route('register'), [
            'name' => 'Test User',
            'business_name' => 'Restaurante Test',
            'email' => 'restaurant@example.com',
            'industry' => 'restaurant',
            'password' => 'SecurePass1!',
            'password_confirmation' => 'SecurePass1!',
        ]);

        $user = User::where('email', 'restaurant@example.com')->first();
        $items = Item::where('tenant_id', $user->tenant_id)->get();

        $this->assertCount(3, $items);
        $this->assertEquals('Agua mineral', $items[0]->name);
    }

    public function test_creates_default_contacts(): void
    {
        $this->post(route('register'), [
            'name' => 'Test User',
            'business_name' => 'Contact Test',
            'email' => 'contacts@example.com',
            'password' => 'SecurePass1!',
            'password_confirmation' => 'SecurePass1!',
        ]);

        $user = User::where('email', 'contacts@example.com')->first();
        $contacts = Contact::where('tenant_id', $user->tenant_id)->get();

        $this->assertCount(2, $contacts);
        $this->assertEquals('Cliente Ejemplo', $contacts[0]->name);
        $this->assertEquals('client', $contacts[0]->contact_type);
        $this->assertEquals('Proveedor Ejemplo', $contacts[1]->name);
        $this->assertEquals('supplier', $contacts[1]->contact_type);
    }

    public function test_activates_default_modules(): void
    {
        $this->post(route('register'), [
            'name' => 'Test User',
            'business_name' => 'Modules Test',
            'email' => 'modules@example.com',
            'password' => 'SecurePass1!',
            'password_confirmation' => 'SecurePass1!',
        ]);

        $user = User::where('email', 'modules@example.com')->first();
        $modules = TenantModule::where('tenant_id', $user->tenant_id)->get();

        $this->assertCount(3, $modules);
        $this->assertTrue($modules->pluck('module_slug')->contains('inventory'));
        $this->assertTrue($modules->pluck('module_slug')->contains('transactions'));
        $this->assertTrue($modules->pluck('module_slug')->contains('contacts'));
    }

    public function test_uses_general_defaults_when_no_industry(): void
    {
        $this->post(route('register'), [
            'name' => 'Test User',
            'business_name' => 'General Business',
            'email' => 'general@example.com',
            'password' => 'SecurePass1!',
            'password_confirmation' => 'SecurePass1!',
        ]);

        $user = User::where('email', 'general@example.com')->first();
        $categories = Category::where('tenant_id', $user->tenant_id)->get();
        $items = Item::where('tenant_id', $user->tenant_id)->get();

        $this->assertCount(4, $categories);
        $this->assertEquals('General', $categories[0]->name);
        $this->assertCount(3, $items);
        $this->assertEquals('Producto de ejemplo', $items[0]->name);
    }
}

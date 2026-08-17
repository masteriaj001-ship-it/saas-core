<?php

declare(strict_types=1);

namespace Tests\Feature\Facturacion;

use App\Enums\InvoiceDocumentTypeEnum;
use App\Enums\InvoiceStatusEnum;
use App\Enums\PaymentMethodEnum;
use App\Filament\Widgets\DailySalesChart;
use App\Filament\Widgets\FinancialStatsOverview;
use App\Filament\Widgets\PaymentMethodsBreakdown;
use App\Filament\Widgets\TopItemsWidget;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Facturacion\Models\Invoice;
use App\Modules\Facturacion\Models\InvoicePayment;
use App\Services\TenantManager;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class FinancialDashboardTest extends TestCase
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
        Filament::setCurrentPanel(app('filament')->getPanel('admin'));
        Filament::setTenant($this->tenant);
    }

    public function test_financial_stats_overview_renders(): void
    {
        Livewire::test(FinancialStatsOverview::class)
            ->assertStatus(200);
    }

    public function test_financial_stats_shows_zero_when_no_invoices(): void
    {
        Livewire::test(FinancialStatsOverview::class)
            ->assertSee(['$0', '$0'])
            ->assertSee('0 '.__('facturas pagadas'));
    }

    public function test_financial_stats_shows_today_sales(): void
    {
        $invoice = Invoice::factory()
            ->for($this->tenant)
            ->paid()
            ->create([
                'document_type' => InvoiceDocumentTypeEnum::Pos,
                'grand_total' => 50000,
                'issued_at' => now(),
            ]);

        Livewire::test(FinancialStatsOverview::class)
            ->assertSee('$50.000')
            ->assertSee('1 '.__('facturas pagadas'));
    }

    public function test_financial_stats_shows_monthly_total(): void
    {
        Invoice::factory()
            ->for($this->tenant)
            ->paid()
            ->create([
                'document_type' => InvoiceDocumentTypeEnum::Pos,
                'grand_total' => 100000,
                'issued_at' => now(),
            ]);

        Invoice::factory()
            ->for($this->tenant)
            ->paid()
            ->create([
                'document_type' => InvoiceDocumentTypeEnum::Pos,
                'grand_total' => 75000,
                'issued_at' => now(),
            ]);

        Livewire::test(FinancialStatsOverview::class)
            ->assertSee('$175.000')
            ->assertSee('2 '.__('facturas este mes'));
    }

    public function test_financial_stats_ignores_cancelled_invoices(): void
    {
        Invoice::factory()
            ->for($this->tenant)
            ->cancelled()
            ->create([
                'grand_total' => 50000,
                'issued_at' => now(),
            ]);

        Livewire::test(FinancialStatsOverview::class)
            ->assertSee('$0');
    }

    public function test_daily_sales_chart_renders(): void
    {
        Livewire::test(DailySalesChart::class)
            ->assertStatus(200);
    }

    public function test_daily_sales_chart_shows_data(): void
    {
        Invoice::factory()
            ->for($this->tenant)
            ->paid()
            ->create([
                'grand_total' => 30000,
                'issued_at' => now(),
            ]);

        Livewire::test(DailySalesChart::class)
            ->assertStatus(200);
    }

    public function test_top_items_widget_renders(): void
    {
        Livewire::test(TopItemsWidget::class)
            ->assertStatus(200);
    }

    public function test_top_items_widget_shows_selling_items(): void
    {
        $invoice = Invoice::factory()
            ->for($this->tenant)
            ->paid()
            ->create(['issued_at' => now()]);

        $invoice->items()->create([
            'description' => 'Filtro de aceite',
            'quantity' => 3,
            'unit_price' => 15000,
            'discount' => 0,
            'tax_rate' => 0,
            'tax_amount' => 0,
            'subtotal' => 45000,
            'total' => 45000,
        ]);

        Livewire::test(TopItemsWidget::class)
            ->assertSee('Filtro de aceite')
            ->assertSee('3');
    }

    public function test_payment_methods_breakdown_renders(): void
    {
        Livewire::test(PaymentMethodsBreakdown::class)
            ->assertStatus(200);
    }

    public function test_payment_methods_shows_zero_when_no_payments(): void
    {
        Livewire::test(PaymentMethodsBreakdown::class)
            ->assertSee('$0')
            ->assertSee('0 '.__('pagos'));
    }

    public function test_payment_methods_shows_cash_payments(): void
    {
        $invoice = Invoice::factory()
            ->for($this->tenant)
            ->create([
                'document_type' => InvoiceDocumentTypeEnum::Pos,
                'grand_total' => 25000,
                'status' => InvoiceStatusEnum::Paid,
                'issued_at' => now(),
            ]);

        InvoicePayment::create([
            'invoice_id' => $invoice->id,
            'tenant_id' => $this->tenant->id,
            'payment_method' => PaymentMethodEnum::Cash,
            'amount' => 25000,
            'cash_received' => 30000,
            'change_due' => 5000,
            'paid_at' => now(),
        ]);

        Livewire::test(PaymentMethodsBreakdown::class)
            ->assertSee('$25.000')
            ->assertSee('1 '.__('pagos'));
    }

    public function test_payment_methods_breakdown_by_type(): void
    {
        $invoice1 = Invoice::factory()->for($this->tenant)->create([
            'document_type' => InvoiceDocumentTypeEnum::Pos,
            'grand_total' => 10000,
            'status' => InvoiceStatusEnum::Paid,
            'issued_at' => now(),
        ]);
        InvoicePayment::create([
            'invoice_id' => $invoice1->id,
            'tenant_id' => $this->tenant->id,
            'payment_method' => PaymentMethodEnum::Cash,
            'amount' => 10000,
            'paid_at' => now(),
        ]);

        $invoice2 = Invoice::factory()->for($this->tenant)->create([
            'document_type' => InvoiceDocumentTypeEnum::Pos,
            'grand_total' => 20000,
            'status' => InvoiceStatusEnum::Paid,
            'issued_at' => now(),
        ]);
        InvoicePayment::create([
            'invoice_id' => $invoice2->id,
            'tenant_id' => $this->tenant->id,
            'payment_method' => PaymentMethodEnum::Card,
            'amount' => 20000,
            'paid_at' => now(),
        ]);

        Livewire::test(PaymentMethodsBreakdown::class)
            ->assertSee('$10.000')
            ->assertSee('$20.000')
            ->assertSee('33.3%')
            ->assertSee('66.7%');
    }

    public function test_financial_stats_isolation_between_tenants(): void
    {
        $otherTenant = Tenant::factory()->create();

        Invoice::factory()
            ->for($this->tenant)
            ->paid()
            ->create([
                'grand_total' => 50000,
                'issued_at' => now(),
            ]);

        Invoice::factory()
            ->for($otherTenant)
            ->paid()
            ->create([
                'grand_total' => 999999,
                'issued_at' => now(),
            ]);

        Livewire::test(FinancialStatsOverview::class)
            ->assertSee('$50.000')
            ->assertDontSee('999.999');
    }
}

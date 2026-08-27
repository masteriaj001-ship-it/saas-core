<?php

declare(strict_types=1);

namespace Tests\Feature\Facturacion;

use App\Enums\InvoiceStatusEnum;
use App\Enums\PaymentMethodEnum;
use App\Models\Contact;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Facturacion\Exceptions\CreditLimitExceededException;
use App\Modules\Facturacion\Models\CreditAccount;
use App\Modules\Facturacion\Models\CreditTransaction;
use App\Modules\Facturacion\Models\Invoice;
use App\Modules\Facturacion\Notifications\OverdueCreditNotification;
use App\Modules\Facturacion\Services\CreditAccountService;
use App\Modules\Facturacion\Services\CreditReportService;
use App\Services\TenantManager;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

final class CreditAccountTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $user;

    private Contact $contact;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create(['onboarding_completed' => true]);
        $this->user = User::factory()->for($this->tenant)->create();
        $this->contact = Contact::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        app(TenantManager::class)->setTenantContext($this->tenant->id);
        $this->seed(RolePermissionSeeder::class);

        $this->user->assignRole('owner');
        $this->actingAs($this->user);
    }

    public function test_credit_account_can_be_created(): void
    {
        $account = CreditAccount::factory()->create([
            'tenant_id' => $this->tenant->id,
            'contact_id' => $this->contact->id,
        ]);

        $this->assertDatabaseHas('credit_accounts', [
            'id' => $account->id,
            'tenant_id' => $this->tenant->id,
        ]);
    }

    public function test_credit_account_belongs_to_contact(): void
    {
        $account = CreditAccount::factory()->create([
            'tenant_id' => $this->tenant->id,
            'contact_id' => $this->contact->id,
        ]);

        $this->assertEquals($this->contact->id, $account->contact->id);
    }

    public function test_credit_account_has_transactions(): void
    {
        $account = CreditAccount::factory()->create([
            'tenant_id' => $this->tenant->id,
            'contact_id' => $this->contact->id,
        ]);

        CreditTransaction::factory()->charge()->create([
            'tenant_id' => $this->tenant->id,
            'credit_account_id' => $account->id,
        ]);

        $this->assertCount(1, $account->transactions);
    }

    public function test_credit_account_is_overdue_when_balance_positive_and_overdue_charges(): void
    {
        $account = CreditAccount::factory()->withBalance(100000)->create([
            'tenant_id' => $this->tenant->id,
            'contact_id' => $this->contact->id,
        ]);

        CreditTransaction::factory()->overdue()->create([
            'tenant_id' => $this->tenant->id,
            'credit_account_id' => $account->id,
        ]);

        $this->assertTrue($account->is_overdue);
    }

    public function test_credit_account_overdue_amount_calculates_correctly(): void
    {
        $account = CreditAccount::factory()->withBalance(150000)->create([
            'tenant_id' => $this->tenant->id,
            'contact_id' => $this->contact->id,
        ]);

        CreditTransaction::factory()->overdue()->create([
            'tenant_id' => $this->tenant->id,
            'credit_account_id' => $account->id,
            'amount' => 100000,
        ]);

        CreditTransaction::factory()->overdue()->create([
            'tenant_id' => $this->tenant->id,
            'credit_account_id' => $account->id,
            'amount' => 50000,
        ]);

        $this->assertEquals(150000, $account->overdue_amount);
    }

    public function test_one_credit_account_per_contact_per_tenant(): void
    {
        CreditAccount::factory()->create([
            'tenant_id' => $this->tenant->id,
            'contact_id' => $this->contact->id,
            'created_by' => $this->user->id,
        ]);

        $this->expectException(UniqueConstraintViolationException::class);

        CreditAccount::factory()->create([
            'tenant_id' => $this->tenant->id,
            'contact_id' => $this->contact->id,
            'created_by' => $this->user->id,
        ]);
    }

    public function test_charge_creates_transaction_and_updates_balance(): void
    {
        $account = CreditAccount::factory()->create([
            'tenant_id' => $this->tenant->id,
            'contact_id' => $this->contact->id,
            'credit_limit' => 500000,
            'current_balance' => 0,
        ]);

        $invoice = Invoice::factory()->create([
            'tenant_id' => $this->tenant->id,
            'contact_id' => $this->contact->id,
            'grand_total' => 100000,
            'status' => InvoiceStatusEnum::Draft,
            'payment_method' => PaymentMethodEnum::Credit,
        ]);

        $service = app(CreditAccountService::class);
        $transaction = $service->charge($invoice, 100000);

        $this->assertEquals('charge', $transaction->type);
        $this->assertEquals(100000, (float) $transaction->amount);
        $this->assertEquals($invoice->id, $transaction->invoice_id);

        $account->refresh();
        $this->assertEquals(100000, (float) $account->current_balance);
    }

    public function test_charge_throws_on_inactive_account(): void
    {
        $account = CreditAccount::factory()->inactive()->create([
            'tenant_id' => $this->tenant->id,
            'contact_id' => $this->contact->id,
        ]);

        $invoice = Invoice::factory()->create([
            'tenant_id' => $this->tenant->id,
            'contact_id' => $this->contact->id,
            'grand_total' => 100000,
        ]);

        $this->expectException(\InvalidArgumentException::class);

        app(CreditAccountService::class)->charge($invoice, 100000);
    }

    public function test_charge_throws_on_limit_exceeded(): void
    {
        $account = CreditAccount::factory()->create([
            'tenant_id' => $this->tenant->id,
            'contact_id' => $this->contact->id,
            'credit_limit' => 100000,
            'current_balance' => 80000,
        ]);

        $invoice = Invoice::factory()->create([
            'tenant_id' => $this->tenant->id,
            'contact_id' => $this->contact->id,
            'grand_total' => 50000,
        ]);

        $this->expectException(CreditLimitExceededException::class);

        app(CreditAccountService::class)->charge($invoice, 50000);
    }

    public function test_payment_creates_transaction_and_updates_balance(): void
    {
        $account = CreditAccount::factory()->withBalance(100000)->create([
            'tenant_id' => $this->tenant->id,
            'contact_id' => $this->contact->id,
            'credit_limit' => 500000,
        ]);

        $service = app(CreditAccountService::class);
        $transaction = $service->payment($account, 50000, reference: 'Transferencia #123');

        $this->assertEquals('payment', $transaction->type);
        $this->assertEquals(50000, (float) $transaction->amount);
        $this->assertEquals('Transferencia #123', $transaction->reference);

        $account->refresh();
        $this->assertEquals(50000, (float) $account->current_balance);
    }

    public function test_payment_throws_on_zero_amount(): void
    {
        $account = CreditAccount::factory()->withBalance(100000)->create([
            'tenant_id' => $this->tenant->id,
            'contact_id' => $this->contact->id,
        ]);

        $this->expectException(\InvalidArgumentException::class);

        app(CreditAccountService::class)->payment($account, 0);
    }

    public function test_reverse_charge_reverts_balance(): void
    {
        $account = CreditAccount::factory()->withBalance(100000)->create([
            'tenant_id' => $this->tenant->id,
            'contact_id' => $this->contact->id,
            'credit_limit' => 500000,
        ]);

        $invoice = Invoice::factory()->create([
            'tenant_id' => $this->tenant->id,
            'contact_id' => $this->contact->id,
            'grand_total' => 100000,
        ]);

        CreditTransaction::factory()->charge()->create([
            'tenant_id' => $this->tenant->id,
            'credit_account_id' => $account->id,
            'amount' => 100000,
            'invoice_id' => $invoice->id,
        ]);

        $service = app(CreditAccountService::class);
        $transaction = $service->reverseCharge($invoice);

        $this->assertNotNull($transaction);
        $this->assertEquals('charge_reverse', $transaction->type);

        $account->refresh();
        $this->assertEquals(0, (float) $account->current_balance);
    }

    public function test_reverse_charge_returns_null_when_no_charge(): void
    {
        $invoice = Invoice::factory()->create([
            'tenant_id' => $this->tenant->id,
            'contact_id' => $this->contact->id,
        ]);

        $service = app(CreditAccountService::class);
        $result = $service->reverseCharge($invoice);

        $this->assertNull($result);
    }

    public function test_get_statement_returns_correct_data(): void
    {
        $account = CreditAccount::factory()->withBalance(100000)->create([
            'tenant_id' => $this->tenant->id,
            'contact_id' => $this->contact->id,
            'credit_limit' => 500000,
        ]);

        CreditTransaction::factory()->charge()->create([
            'tenant_id' => $this->tenant->id,
            'credit_account_id' => $account->id,
            'amount' => 100000,
        ]);

        CreditTransaction::factory()->payment()->create([
            'tenant_id' => $this->tenant->id,
            'credit_account_id' => $account->id,
            'amount' => 30000,
        ]);

        $service = app(CreditReportService::class);
        $statement = $service->getStatement($account);

        $this->assertEquals($this->contact->name, $statement['contact']);
        $this->assertEquals(500000, $statement['credit_limit']);
        $this->assertEquals(100000, $statement['current_balance']);
        $this->assertEquals(100000, $statement['total_charges']);
        $this->assertEquals(30000, $statement['total_payments']);
    }

    public function test_get_aging_report_groups_by_buckets(): void
    {
        $account = CreditAccount::factory()->withBalance(250000)->create([
            'tenant_id' => $this->tenant->id,
            'contact_id' => $this->contact->id,
            'credit_limit' => 500000,
        ]);

        CreditTransaction::factory()->create([
            'tenant_id' => $this->tenant->id,
            'credit_account_id' => $account->id,
            'type' => 'charge',
            'amount' => 100000,
            'due_date' => now()->subDays(15),
        ]);

        CreditTransaction::factory()->create([
            'tenant_id' => $this->tenant->id,
            'credit_account_id' => $account->id,
            'type' => 'charge',
            'amount' => 150000,
            'due_date' => now()->subDays(45),
        ]);

        $service = app(CreditReportService::class);
        $report = $service->getAgingReport();

        $this->assertCount(1, $report);
        $this->assertEquals(100000, $report[0]['buckets']['1_30']);
        $this->assertEquals(150000, $report[0]['buckets']['31_60']);
    }

    public function test_check_overdue_command_sends_notifications(): void
    {
        $account = CreditAccount::factory()->withBalance(100000)->create([
            'tenant_id' => $this->tenant->id,
            'contact_id' => $this->contact->id,
            'credit_limit' => 500000,
        ]);

        CreditTransaction::factory()->overdue()->create([
            'tenant_id' => $this->tenant->id,
            'credit_account_id' => $account->id,
            'amount' => 100000,
        ]);

        Notification::fake();

        $this->artisan('credit:check-overdue')
            ->assertExitCode(0);

        Notification::assertSentTo(
            [$this->user],
            OverdueCreditNotification::class,
        );
    }

    public function test_check_overdue_command_no_output_when_no_overdue(): void
    {
        $this->artisan('credit:check-overdue')
            ->expectsOutput('No overdue credit accounts found.')
            ->assertExitCode(0);
    }
}

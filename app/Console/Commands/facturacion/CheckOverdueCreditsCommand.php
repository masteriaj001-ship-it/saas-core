<?php

declare(strict_types=1);

namespace App\Console\Commands\facturacion;

use App\Models\Tenant;
use App\Models\User;
use App\Modules\Facturacion\Notifications\OverdueCreditNotification;
use App\Modules\Facturacion\Services\CreditReportService;
use App\Services\TenantManager;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

class CheckOverdueCreditsCommand extends Command
{
    protected $signature = 'credit:check-overdue {--tenant= : Filter by specific tenant ID}';

    protected $description = 'Check for overdue credit accounts and send notifications';

    public function handle(CreditReportService $reportService): int
    {
        $tenantId = $this->option('tenant');

        $tenantIds = $tenantId
            ? [$tenantId]
            : Tenant::query()->orderBy('id')->pluck('id')->all();

        $found = false;

        foreach ($tenantIds as $id) {
            app(TenantManager::class)->setTenantContext($id);
            $found = $this->processTenant($reportService) || $found;
        }

        if (! $found) {
            $this->info('No overdue credit accounts found.');
        }

        return self::SUCCESS;
    }

    private function processTenant(CreditReportService $reportService): bool
    {
        $overdueAccounts = $reportService->getOverdueAccounts();

        if ($overdueAccounts->isEmpty()) {
            return false;
        }

        $this->warn("Found {$overdueAccounts->count()} overdue accounts:");

        foreach ($overdueAccounts as $account) {
            $overdueAmount = (float) $account->overdueCharges()->sum('amount');
            $minDueDate = $account->overdueCharges()->min('due_date');
            $daysOverdue = $minDueDate
                ? (int) Carbon::parse($minDueDate)->diffInDays(now())
                : 0;

            $this->line("  - {$account->contact->name}: $".number_format($overdueAmount, 2)." ({$daysOverdue} days)");

            $this->notifyTenant($account, $overdueAmount, $daysOverdue);
        }

        return true;
    }

    private function notifyTenant($account, float $overdueAmount, int $daysOverdue): void
    {
        $users = User::role(['owner', 'editor'])
            ->where('tenant_id', $account->tenant_id)
            ->get();

        if ($users->isEmpty()) {
            return;
        }

        Notification::send($users, new OverdueCreditNotification(
            account: $account,
            overdueAmount: $overdueAmount,
            daysOverdue: $daysOverdue,
        ));
    }
}

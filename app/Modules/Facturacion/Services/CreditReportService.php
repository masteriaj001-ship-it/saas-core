<?php

declare(strict_types=1);

namespace App\Modules\Facturacion\Services;

use App\Modules\Facturacion\Models\CreditAccount;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

class CreditReportService
{
    public function getStatement(CreditAccount $account, ?Carbon $asOf = null): array
    {
        $asOf = $asOf ?? now();

        $transactions = $account->transactions()
            ->whereDate('created_at', '<=', $asOf)
            ->orderBy('created_at', 'asc')
            ->get();

        $charges = $transactions->where('type', 'charge');
        $payments = $transactions->whereIn('type', ['payment', 'charge_reverse']);

        $overdue = $charges->filter(
            fn ($t) => $t->due_date && $t->due_date->lt($asOf) && $this->isUnpaid($t, $transactions)
        );

        return [
            'contact' => $account->contact->name,
            'credit_limit' => (float) $account->credit_limit,
            'current_balance' => (float) $account->current_balance,
            'available_credit' => (float) max(0, (float) $account->credit_limit - (float) $account->current_balance),
            'total_charges' => (float) $charges->sum('amount'),
            'total_payments' => (float) $payments->sum('amount'),
            'overdue_amount' => (float) $overdue->sum('amount'),
            'overdue_count' => $overdue->count(),
            'transactions' => $transactions,
            'as_of' => $asOf->toDateTimeString(),
        ];
    }

    private function isUnpaid($charge, $transactions): bool
    {
        $paymentByInvoice = $transactions->first(
            fn ($t) => $t->type === 'payment' && $t->invoice_id === $charge->invoice_id
        );

        if ($paymentByInvoice) {
            return false;
        }

        $reversal = $transactions->first(
            fn ($t) => $t->type === 'charge_reverse' && $t->invoice_id === $charge->invoice_id
        );

        return ! $reversal;
    }

    public function getOverdueAccounts(?int $daysOverdue = null): Collection
    {
        $query = CreditAccount::where('current_balance', '>', 0)
            ->whereHas('transactions', function ($q) use ($daysOverdue) {
                $q->where('type', 'charge')
                    ->where('due_date', '<', now());
                if ($daysOverdue) {
                    $q->where('due_date', '<', now()->subDays($daysOverdue));
                }
            })
            ->with('contact');

        return $query->get();
    }

    public function getAgingReport(): array
    {
        $accounts = CreditAccount::where('current_balance', '>', 0)
            ->with(['transactions' => function ($q) {
                $q->where('type', 'charge');
            }])
            ->get();

        $report = [];

        foreach ($accounts as $account) {
            $buckets = [
                'current' => 0,
                '1_30' => 0,
                '31_60' => 0,
                '61_90' => 0,
                '90_plus' => 0,
            ];

            foreach ($account->transactions as $charge) {
                if (! $charge->due_date || $charge->due_date->isFuture()) {
                    $buckets['current'] += (float) $charge->amount;

                    continue;
                }

                $daysOverdue = (int) $charge->due_date->diffInDays(now());

                match (true) {
                    $daysOverdue <= 30 => $buckets['1_30'] += (float) $charge->amount,
                    $daysOverdue <= 60 => $buckets['31_60'] += (float) $charge->amount,
                    $daysOverdue <= 90 => $buckets['61_90'] += (float) $charge->amount,
                    default => $buckets['90_plus'] += (float) $charge->amount,
                };
            }

            if (array_sum($buckets) > 0) {
                $report[] = [
                    'contact' => $account->contact->name,
                    'phone' => $account->contact->phone ?? null,
                    'total_balance' => (float) $account->current_balance,
                    'buckets' => $buckets,
                ];
            }
        }

        return $report;
    }
}

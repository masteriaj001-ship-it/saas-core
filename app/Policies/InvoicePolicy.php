<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Modules\Facturacion\Models\Invoice;

class InvoicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view_invoices');
    }

    public function view(User $user, Invoice $invoice): bool
    {
        return $user->can('view_invoices');
    }

    public function create(User $user): bool
    {
        return $user->can('create_invoices');
    }

    public function update(User $user, Invoice $invoice): bool
    {
        return $user->can('edit_invoices');
    }

    public function delete(User $user, Invoice $invoice): bool
    {
        return $user->can('delete_invoices');
    }
}

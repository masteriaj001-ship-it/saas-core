<?php

declare(strict_types=1);

namespace App\Modules\Facturacion\Models;

use App\Enums\PaymentMethodEnum;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoicePayment extends Model
{
    use BelongsToTenant, HasUuids;

    protected $guarded = [
        'id',
        'tenant_id',
        'created_at',
        'updated_at',
    ];

    protected $fillable = [
        'invoice_id',
        'payment_method',
        'amount',
        'cash_received',
        'change_due',
        'reference',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'id' => 'string',
            'tenant_id' => 'string',
            'payment_method' => PaymentMethodEnum::class,
            'amount' => 'decimal:2',
            'cash_received' => 'decimal:2',
            'change_due' => 'decimal:2',
            'paid_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}

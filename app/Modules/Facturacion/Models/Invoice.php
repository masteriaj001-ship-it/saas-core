<?php

declare(strict_types=1);

namespace App\Modules\Facturacion\Models;

use App\Enums\InvoiceDocumentTypeEnum;
use App\Enums\InvoiceStatusEnum;
use App\Models\Contact;
use App\Models\TenantModel;
use App\Modules\Talleres\Models\WorkOrder;
use Database\Factories\InvoiceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends TenantModel
{
    use HasFactory;

    protected static function newFactory(): InvoiceFactory
    {
        return InvoiceFactory::new();
    }

    protected $fillable = [
        'work_order_id',
        'contact_id',
        'document_type',
        'prefix',
        'sequence',
        'pos_sequence',
        'document_number',
        'status',
        'issued_at',
        'due_at',
        'subtotal',
        'discount_total',
        'tax_total',
        'grand_total',
        'notes',
        'cufe',
        'qr_code_url',
    ];

    protected function casts(): array
    {
        return array_merge(parent::casts(), [
            'document_type' => InvoiceDocumentTypeEnum::class,
            'status' => InvoiceStatusEnum::class,
            'issued_at' => 'datetime',
            'due_at' => 'datetime',
            'subtotal' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'tax_total' => 'decimal:2',
            'grand_total' => 'decimal:2',
        ]);
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }
}

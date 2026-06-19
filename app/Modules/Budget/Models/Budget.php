<?php

declare(strict_types=1);

namespace App\Modules\Budget\Models;

use App\Enums\BudgetStatusEnum;
use App\Models\Contact;
use App\Models\TenantModel;
use App\Models\User;
use App\Modules\Talleres\Models\WorkOrder;
use Database\Factories\BudgetFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Budget extends TenantModel
{
    use HasFactory;

    protected static function newFactory(): BudgetFactory
    {
        return BudgetFactory::new();
    }

    protected $fillable = [
        'code',
        'contact_id',
        'contact_name',
        'contact_phone',
        'contact_email',
        'vehicle_data',
        'status',
        'subtotal',
        'discount_total',
        'tax_total',
        'grand_total',
        'notes',
        'sent_at',
        'responded_at',
        'approved_at',
        'rejected_at',
        'rejection_reason',
        'converted_to_work_order_id',
        'created_by',
    ];

    protected $casts = [
        'vehicle_data' => 'array',
        'status' => BudgetStatusEnum::class,
        'subtotal' => 'float',
        'discount_total' => 'float',
        'tax_total' => 'float',
        'grand_total' => 'float',
        'sent_at' => 'datetime',
        'responded_at' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(BudgetItem::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function convertedWorkOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class, 'converted_to_work_order_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

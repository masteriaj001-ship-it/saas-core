<?php

declare(strict_types=1);

namespace App\Modules\Facturacion\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class DocumentSequence extends Model
{
    use BelongsToTenant, HasUuids;

    protected $table = 'document_sequences';

    protected $fillable = [
        'type',
        'prefix',
        'last_sequence',
    ];

    protected $guarded = [
        'id',
        'tenant_id',
        'created_at',
        'updated_at',
    ];

    protected function casts(): array
    {
        return [
            'id' => 'string',
            'tenant_id' => 'string',
            'last_sequence' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}

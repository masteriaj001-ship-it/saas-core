<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ModuleCatalog extends Model
{
    use HasUuids;

    protected $table = 'modules_catalog';

    protected $fillable = [
        'slug',
        'name',
        'description',
        'is_active',
    ];

    public $incrementing = false;

    protected $keyType = 'string';

    protected function casts(): array
    {
        return [
            'id' => 'string',
            'is_active' => 'boolean',
        ];
    }
}

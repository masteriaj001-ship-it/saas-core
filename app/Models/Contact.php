<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class Contact extends TenantModel
{
    use HasFactory;

    protected $fillable = [
        'contact_type',
        'name',
        'tax_id',
        'email',
        'phone',
        'address',
        'metadata',
    ];

    protected function casts(): array
    {
        return array_merge(parent::casts(), [
            'metadata' => 'array',
        ]);
    }
}

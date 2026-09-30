<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\DB;

class EmailUniqueGlobally implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $exists = DB::selectOne(
            'SELECT public.email_exists(?) AS exists',
            [$value]
        );

        if ($exists?->exists) {
            $fail('Este correo electrónico ya está registrado.');
        }
    }
}

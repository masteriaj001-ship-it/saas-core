<?php

declare(strict_types=1);

namespace App\Modules\Talleres\Exceptions;

use Exception;

class TenantRegistrationException extends Exception
{
    public static function duplicateEmail(string $email): self
    {
        return new self("A user with email {$email} already exists.");
    }

    public static function duplicateSlug(string $slug): self
    {
        return new self("A tenant with slug {$slug} already exists.");
    }

    public static function registrationFailed(string $reason): self
    {
        return new self("Tenant registration failed: {$reason}");
    }
}

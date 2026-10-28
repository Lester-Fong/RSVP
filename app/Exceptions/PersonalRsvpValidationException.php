<?php

namespace App\Exceptions;

use Illuminate\Validation\ValidationException;

class PersonalRsvpValidationException extends ValidationException
{
    public string $kind = 'validation';

    public static function because(string $field, string $message, string $kind = 'validation'): self
    {
        $exception = static::withMessages([$field => $message]);
        $exception->kind = $kind;

        return $exception;
    }
}

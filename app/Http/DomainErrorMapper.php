<?php

namespace App\Http;

use Illuminate\Validation\ValidationException;
use Throwable;

final class DomainErrorMapper
{
    public static function map(Throwable $e): Throwable
    {
        if (! str_starts_with($e::class, 'Guixa\\') || ! str_contains($e::class, '\\Domain\\Error\\')) {
            return $e;
        }

        $key = 'errors.'.class_basename($e);
        $message = trans()->has($key) ? __($key) : $e->getMessage();

        return ValidationException::withMessages(['domain' => $message]);
    }
}

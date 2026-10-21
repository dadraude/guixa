<?php

namespace App\Http;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/** Turns a `Guixa\<Context>\Domain\Error\*` exception into a form error (422 for JSON). */
final class DomainErrorResponse
{
    public static function matches(Throwable $e): bool
    {
        return str_starts_with($e::class, 'Guixa\\') && str_contains($e::class, '\\Domain\\Error\\');
    }

    public static function for(Throwable $e, Request $request): ?Response
    {
        if (! self::matches($e)) {
            return null;
        }

        $message = self::message($e);

        if ($request->expectsJson()) {
            return response()->json(['message' => $message, 'errors' => ['domain' => [$message]]], 422);
        }

        return back()->withInput()->withErrors(['domain' => $message]);
    }

    private static function message(Throwable $e): string
    {
        $key = 'errors.'.class_basename($e);

        return trans()->has($key) ? __($key) : $e->getMessage();
    }
}

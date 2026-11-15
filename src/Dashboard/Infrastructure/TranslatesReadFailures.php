<?php

namespace Guixa\Dashboard\Infrastructure;

use Guixa\Dashboard\Domain\Error\ReadUnavailable;
use Illuminate\Database\QueryException;

/** Only a failed database read is a known failure; authorization and unexpected errors keep their own path. */
trait TranslatesReadFailures
{
    /**
     * @template T
     *
     * @param  callable(): T  $read
     * @return T
     */
    private function guarded(string $source, callable $read): mixed
    {
        try {
            return $read();
        } catch (QueryException $failure) {
            throw ReadUnavailable::source($source, $failure);
        }
    }
}

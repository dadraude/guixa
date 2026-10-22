<?php

namespace Guixa\Membership\Domain\ValueObject;

use Guixa\Membership\Domain\Error\InvalidEmail;

final readonly class Email
{
    private function __construct(public string $value) {}

    public static function from(string $raw): self
    {
        $normalized = strtolower(trim($raw));

        if (filter_var($normalized, FILTER_VALIDATE_EMAIL) === false) {
            throw InvalidEmail::for($raw);
        }

        return new self($normalized);
    }
}

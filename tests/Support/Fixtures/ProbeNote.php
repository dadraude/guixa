<?php

namespace Tests\Support\Fixtures;

final class ProbeNote
{
    public function __construct(public readonly string $id, public string $text) {}
}

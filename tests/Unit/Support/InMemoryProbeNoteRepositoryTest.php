<?php

namespace Tests\Unit\Support;

use PHPUnit\Framework\TestCase;
use Tests\Support\Contracts\ProbeNoteRepositoryContract;
use Tests\Support\Fixtures\ProbeNoteRepository;
use Tests\Support\InMemory\InMemoryProbeNoteRepository;

final class InMemoryProbeNoteRepositoryTest extends TestCase
{
    use ProbeNoteRepositoryContract;

    protected function repository(): ProbeNoteRepository
    {
        return new InMemoryProbeNoteRepository;
    }
}

<?php

namespace Tests\Support\Fixtures;

interface ProbeNoteRepository
{
    public function nextId(): string;

    public function add(ProbeNote $note): void;

    public function get(string $id): ?ProbeNote;

    public function delete(string $id): void;

    /** @return list<ProbeNote> */
    public function list(): array;
}

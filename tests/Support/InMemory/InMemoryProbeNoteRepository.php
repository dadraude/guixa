<?php

namespace Tests\Support\InMemory;

use Tests\Support\Fixtures\ProbeNote;
use Tests\Support\Fixtures\ProbeNoteRepository;

/** @extends InMemoryRepository<ProbeNote> */
final class InMemoryProbeNoteRepository extends InMemoryRepository implements ProbeNoteRepository
{
    public function add(ProbeNote $note): void
    {
        $this->save($note);
    }

    public function get(string $id): ?ProbeNote
    {
        return $this->find($id);
    }

    public function delete(string $id): void
    {
        $this->remove($id);
    }

    public function list(): array
    {
        return $this->all();
    }

    protected function idOf(object $item): string
    {
        return $item->id;
    }
}

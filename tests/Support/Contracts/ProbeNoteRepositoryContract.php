<?php

namespace Tests\Support\Contracts;

use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Fixtures\ProbeNote;
use Tests\Support\Fixtures\ProbeNoteRepository;

/**
 * Contract-test pattern: the behaviour a repository port promises lives in one trait, so each test class
 * keeps its own parent. The in-memory test extends PHPUnit's TestCase and uses the trait; the Eloquent test
 * (tests/Integration) extends Tests\TestCase and uses RefreshDatabase and the trait. Each only says how to
 * build the repository, so the Application tests can trust the in-memory one.
 *
 * A real port gets its own `<Noun>RepositoryContract` next to this one, copying this shape.
 */
trait ProbeNoteRepositoryContract
{
    abstract protected function repository(): ProbeNoteRepository;

    #[Test]
    public function it_generates_distinct_uuid_v7_ids(): void
    {
        $repository = $this->repository();

        $first = $repository->nextId();

        $this->assertNotSame($first, $repository->nextId());
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $first);
    }

    #[Test]
    public function it_finds_what_was_added(): void
    {
        $repository = $this->repository();
        $note = new ProbeNote($repository->nextId(), 'milk');

        $repository->add($note);

        $this->assertEquals($note, $repository->get($note->id));
    }

    #[Test]
    public function it_ignores_changes_to_a_fetched_note_until_it_is_added_again(): void
    {
        $repository = $this->repository();
        $note = new ProbeNote($repository->nextId(), 'milk');
        $repository->add($note);

        $note->text = 'oil';
        $repository->get($note->id)->text = 'wine';

        $this->assertSame('milk', $repository->get($note->id)?->text);
        $this->assertSame('milk', $repository->list()[0]->text);
    }

    #[Test]
    public function it_returns_null_for_an_unknown_id(): void
    {
        $repository = $this->repository();

        $this->assertNull($repository->get($repository->nextId()));
    }

    #[Test]
    public function it_replaces_a_note_added_twice(): void
    {
        $repository = $this->repository();
        $id = $repository->nextId();

        $repository->add(new ProbeNote($id, 'milk'));
        $repository->add(new ProbeNote($id, 'oil'));

        $this->assertSame('oil', $repository->get($id)?->text);
        $this->assertCount(1, $repository->list());
    }

    #[Test]
    public function it_deletes_a_note_and_ignores_unknown_ids(): void
    {
        $repository = $this->repository();
        $note = new ProbeNote($repository->nextId(), 'milk');
        $repository->add($note);

        $repository->delete($note->id);
        $repository->delete($repository->nextId());

        $this->assertNull($repository->get($note->id));
        $this->assertSame([], $repository->list());
    }
}

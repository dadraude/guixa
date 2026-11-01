<?php

namespace Tests\Support\Contracts;

use DateTimeImmutable;
use Guixa\Sales\Domain\Basket;
use Guixa\Sales\Domain\BasketLine;
use Guixa\Sales\Domain\BasketRepository;
use Guixa\Sales\Domain\Error\BasketNotEditable;
use Guixa\Shared\Domain\Quantity;
use PHPUnit\Framework\Attributes\Test;
use Throwable;

trait BasketRepositoryContract
{
    /** @return array{BasketRepository,string,string,string,string,string} repository, manager, household, product, return A, return B */
    abstract protected function basketWorld(): array;

    private function basket(BasketRepository $repository, string $manager, string $household, array $lines): Basket
    {
        return new Basket($repository->nextId(), $household, 'Household K', 'draft', $manager,
            new DateTimeImmutable('2026-10-30T10:00:00+00:00'), $lines);
    }

    private function line(BasketRepository $repository, string $basketId, string $product, int $price = 1200): BasketLine
    {
        return new BasketLine($repository->nextId(), $basketId, $product, 'Oil', 'bottle', $price,
            false, null, Quantity::fromString('1'), new DateTimeImmutable('2026-10-30T10:00:00+00:00'));
    }

    #[Test]
    public function it_reloads_distinct_return_lines_and_retains_their_snapshots(): void
    {
        [$repository, $manager, $household, , $returnA, $returnB] = $this->basketWorld();
        $basket = $this->basket($repository, $manager, $household, []);
        $created = new DateTimeImmutable('2026-10-30T10:00:00+00:00');
        $first = new BasketLine($repository->nextId(), $basket->id, null, 'Glass jar', 'item', 50,
            false, null, Quantity::fromString('1'), $created, kind: 'return', returnItemId: $returnA);
        $second = new BasketLine($repository->nextId(), $basket->id, null, 'Bottle', 'item', 25,
            false, null, Quantity::fromString('2'), $created, kind: 'return', returnItemId: $returnB);

        $repository->save($basket->withLines([$first, $second]));
        $reloaded = $repository->get($basket->id);
        $this->assertCount(2, $reloaded->activeLines());
        $this->assertSame(-100, $reloaded->totalCents());
        $this->assertSame(['Glass jar', 'Bottle'], array_map(fn (BasketLine $line) => $line->name, $reloaded->activeLines()));
    }

    #[Test]
    public function it_reloads_current_lines_and_keeps_archived_product_history(): void
    {
        [$repository, $manager, $household, $product] = $this->basketWorld();
        $basket = $this->basket($repository, $manager, $household, []);
        $first = $this->line($repository, $basket->id, $product);
        $repository->save($basket->withLines([$first]));
        $this->assertSame(1200, $repository->get($basket->id)->activeLineFor($product)->priceCents);

        $archived = $first->removedAt(new DateTimeImmutable('2026-10-30T11:00:00+00:00'));
        $repository->save($basket->withLines([$archived]));
        $this->assertTrue($repository->hasProductHistory($product));
        $this->assertCount(0, $repository->get($basket->id)->activeLines());

        $second = $this->line($repository, $basket->id, $product, 1500);
        $repository->save($basket->withLines([$archived, $second]));
        $this->assertCount(2, $repository->get($basket->id)->lines);
        $this->assertSame(1500, $repository->get($basket->id)->activeLineFor($product)->priceCents);
    }

    #[Test]
    public function it_rejects_two_active_lines_for_one_product(): void
    {
        [$repository, $manager, $household, $product] = $this->basketWorld();
        $basket = $this->basket($repository, $manager, $household, []);
        $first = $this->line($repository, $basket->id, $product);
        $second = $this->line($repository, $basket->id, $product);

        $error = null;
        try {
            $repository->save($basket->withLines([$first, $second]));
        } catch (Throwable $caught) {
            $error = $caught;
        }
        $this->assertNotNull($error, 'Two active lines for one product must be rejected.');
        $this->assertNull($repository->get($basket->id));
    }

    #[Test]
    public function it_retains_the_validated_snapshot_but_allows_first_collection(): void
    {
        [$repository, $manager, $household, $product] = $this->basketWorld();
        $basket = $this->basket($repository, $manager, $household, []);
        $line = $this->line($repository, $basket->id, $product);
        $repository->save($basket->withLines([$line]));
        $validated = $repository->get($basket->id)->validated($manager, new DateTimeImmutable('2026-10-30T12:00:00+00:00'));
        $repository->save($validated);
        $changed = new BasketLine($line->id, $basket->id, $product, 'Changed', 'bottle', 1200,
            false, null, Quantity::fromString('1'), $line->createdAt);
        $rewrite = new Basket($validated->id, $validated->householdId, $validated->householdName,
            'validated', $validated->createdBy, $validated->createdAt, [$changed],
            $validated->validatedBy, $validated->validatedAt, $validated->validatedTotalCents);
        $error = null;
        try {
            $repository->save($rewrite);
        } catch (Throwable $caught) {
            $error = $caught;
        }
        $this->assertInstanceOf(BasketNotEditable::class, $error);
        $this->assertSame('Oil', $repository->get($basket->id)->activeLineFor($product)->name);

        $collected = $repository->get($basket->id)->collected($manager, new DateTimeImmutable('2026-10-30T13:00:00+00:00'));
        $repository->save($collected);
        $this->assertSame('collected', $repository->get($basket->id)->state);
        $this->assertSame($manager, $repository->get($basket->id)->collectedBy);
    }

    #[Test]
    public function it_scopes_household_reads_without_exposing_other_household_ids(): void
    {
        [$repository, $manager, $household, $product] = $this->basketWorld();
        $basket = $this->basket($repository, $manager, $household, []);
        $repository->save($basket->withLines([$this->line($repository, $basket->id, $product)]));

        $this->assertSame($basket->id, $repository->getForHousehold($basket->id, $household)?->id);
        $this->assertNull($repository->getForHousehold($basket->id, '00000000-0000-7000-8000-000000000099'));
        $this->assertSame([$basket->id], array_map(fn (Basket $row) => $row->id, $repository->listForHousehold($household)));
        $this->assertSame([], $repository->listForHousehold('00000000-0000-7000-8000-000000000099'));
        $this->assertSame('Oil', $repository->getForHousehold($basket->id, $household)?->activeLines()[0]->name);
    }
}

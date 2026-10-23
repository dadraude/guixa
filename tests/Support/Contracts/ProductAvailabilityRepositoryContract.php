<?php

namespace Tests\Support\Contracts;

use Guixa\Ordering\Domain\ProductAvailabilityRepository;
use PHPUnit\Framework\Attributes\Test;

/** Behaviour every ProductAvailabilityRepository promises; see ProbeNoteRepositoryContract for how it is shared. */
trait ProductAvailabilityRepositoryContract
{
    abstract protected function repository(): ProductAvailabilityRepository;

    private const CYCLE_A = '00000000-0000-7000-8000-0000000000c1';

    private const CYCLE_B = '00000000-0000-7000-8000-0000000000c2';

    private const JAM = '00000000-0000-7000-8000-0000000000f1';

    private const BREAD = '00000000-0000-7000-8000-0000000000f2';

    /** Lets an adapter that enforces foreign keys create the rows the marks refer to. */
    protected function referencedRowsExist(): void {}

    #[Test]
    public function every_product_is_available_until_it_is_marked(): void
    {
        $this->referencedRowsExist();

        $this->assertSame([], $this->repository()->unavailableIn(self::CYCLE_A));
    }

    #[Test]
    public function it_keeps_the_marks_of_each_cycle_apart(): void
    {
        $this->referencedRowsExist();
        $repository = $this->repository();

        $repository->markUnavailable(self::CYCLE_A, self::JAM);

        $this->assertSame([self::JAM], $repository->unavailableIn(self::CYCLE_A));
        $this->assertSame([], $repository->unavailableIn(self::CYCLE_B));
    }

    #[Test]
    public function marking_twice_keeps_one_mark(): void
    {
        $this->referencedRowsExist();
        $repository = $this->repository();

        $repository->markUnavailable(self::CYCLE_A, self::JAM);
        $repository->markUnavailable(self::CYCLE_A, self::JAM);
        $repository->markUnavailable(self::CYCLE_A, self::BREAD);

        $this->assertEqualsCanonicalizing([self::JAM, self::BREAD], $repository->unavailableIn(self::CYCLE_A));
    }

    #[Test]
    public function marking_available_clears_only_that_mark_and_ignores_a_product_never_marked(): void
    {
        $this->referencedRowsExist();
        $repository = $this->repository();
        $repository->markUnavailable(self::CYCLE_A, self::JAM);
        $repository->markUnavailable(self::CYCLE_A, self::BREAD);

        $repository->markAvailable(self::CYCLE_A, self::JAM);
        $repository->markAvailable(self::CYCLE_B, self::JAM);

        $this->assertSame([self::BREAD], $repository->unavailableIn(self::CYCLE_A));
    }
}

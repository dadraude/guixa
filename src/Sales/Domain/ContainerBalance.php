<?php

namespace Guixa\Sales\Domain;

use Guixa\Shared\Domain\Quantity;

/** Containers a household still has to return, derived from its validated baskets and never stored (R18a). */
final class ContainerBalance
{
    /** @param iterable<Basket> $baskets every basket of one household */
    public static function of(iterable $baskets): int
    {
        $balance = 0;
        foreach ($baskets as $basket) {
            $balance += self::contribution($basket);
        }

        return $balance;
    }

    private static function contribution(Basket $basket): int
    {
        if (! $basket->state->isSettled()) {
            return 0;
        }

        $containers = 0;
        foreach ($basket->activeLines() as $line) {
            $containers += self::lineContainers($basket, $line);
        }

        return $containers;
    }

    private static function lineContainers(Basket $basket, BasketLine $line): int
    {
        // A return line's quantity is the number of containers handed back.
        if ($line->kind->isReturn()) {
            return -intdiv($line->quantity->thousandths, 1000);
        }
        if ($line->containersPerUnit === null) {
            return 0;
        }
        $effective = Quantity::ofThousandths($basket->effectiveQuantity((string) $line->productId));

        return $effective->containersAt(Quantity::fromString($line->containersPerUnit));
    }
}

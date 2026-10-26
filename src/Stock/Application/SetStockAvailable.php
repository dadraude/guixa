<?php

namespace Guixa\Stock\Application;

use Guixa\Shared\Application\Actor;
use Guixa\Shared\Domain\Quantity;
use Guixa\Stock\Domain\ValueObject\SignedQuantity;

final class SetStockAvailable
{
    public function __construct(private readonly RecordStockAdjustment $record) {}

    public function __invoke(Actor $actor, StockLevelData $level, string $available): void
    {
        $desired = Quantity::fromString($available);
        $current = SignedQuantity::fromString($level->available);
        $difference = SignedQuantity::ofThousandths($desired->thousandths - $current->thousandths);

        if ($difference->isZero()) {
            return;
        }

        ($this->record)($actor, $level->id, $difference->toString(), 'Recompte d’estoc', null);
    }
}

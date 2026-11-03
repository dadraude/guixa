<?php

namespace Guixa\Sales\Application;

use Guixa\Sales\Domain\Basket;

/** What the current actor may do with a basket, decided here so screens never inspect its state. */
final readonly class BasketActions
{
    public function __construct(
        public bool $edit,
        public bool $validate,
        public bool $cancel,
        public bool $markCollected,
        public bool $correct,
        public bool $correctCollection,
    ) {}

    /** Staff run the whole lifecycle. */
    public static function forStaff(Basket $basket): self
    {
        return new self(
            edit: $basket->isEditable(),
            validate: $basket->isEditable(),
            cancel: $basket->canBeCancelled(),
            markCollected: $basket->isPendingCollection(),
            correct: $basket->state->isSettled(),
            correctCollection: $basket->state->isCollected(),
        );
    }

    /** A household member only hands over its own validated basket. */
    public static function forMember(Basket $basket): self
    {
        return new self(false, false, false, $basket->isPendingCollection(), false, false);
    }
}

<?php

namespace Guixa\Ordering\Application;

/** What an order costs for the quantities typed so far, saved or not. */
final readonly class OrderPreviewData
{
    /** @param  array<string, int>  $amountCents  by product id */
    public function __construct(public int $totalCents, public array $amountCents) {}
}

<?php

namespace Guixa\Sales\Application;

final readonly class OpenBasketData
{
    public function __construct(public string $basketId, public string $status, public string $date) {}
}

<?php

namespace Guixa\Catalog\Application;

final readonly class ProducerCountsData
{
    public function __construct(public int $total, public int $active) {}
}

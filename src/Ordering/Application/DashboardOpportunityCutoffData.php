<?php

namespace Guixa\Ordering\Application;

/** The moment an open producer closes for members, in the cooperative's local time (`Y-m-d H:i`). */
final readonly class DashboardOpportunityCutoffData
{
    public function __construct(
        public string $producerName,
        public string $closesAt,
    ) {}
}

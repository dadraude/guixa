<?php

namespace Tests\Unit\Ordering;

use Guixa\Ordering\Domain\CooperativeSettings;
use PHPUnit\Framework\TestCase;
use Tests\Support\Contracts\CooperativeSettingsContract;
use Tests\Support\InMemory\InMemoryCooperativeSettings;

final class InMemoryCooperativeSettingsTest extends TestCase
{
    use CooperativeSettingsContract;

    protected function settings(): CooperativeSettings
    {
        return new InMemoryCooperativeSettings;
    }
}

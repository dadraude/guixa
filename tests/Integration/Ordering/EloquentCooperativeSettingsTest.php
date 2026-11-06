<?php

namespace Tests\Integration\Ordering;

use Guixa\Ordering\Domain\CooperativeSettings;
use Guixa\Ordering\Infrastructure\Persistence\EloquentCooperativeSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Contracts\CooperativeSettingsContract;
use Tests\TestCase;

final class EloquentCooperativeSettingsTest extends TestCase
{
    use CooperativeSettingsContract;
    use RefreshDatabase;

    protected function settings(): CooperativeSettings
    {
        return new EloquentCooperativeSettings;
    }
}

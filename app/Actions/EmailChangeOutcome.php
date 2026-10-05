<?php

declare(strict_types=1);

namespace App\Actions;

enum EmailChangeOutcome
{
    case Applied;
    case LinkUnusable;
    case EmailTaken;
}

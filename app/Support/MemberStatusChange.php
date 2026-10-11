<?php

declare(strict_types=1);

namespace App\Support;

enum MemberStatusChange
{
    case Changed;
    case AlreadyInThatState;
    case Forbidden;
}

<?php

declare(strict_types=1);

namespace App\Auth;

/**
 * The two ways a user can act: as a member of a household or as an
 * administrator outside any household.
 */
enum Profile: string
{
    case Member = 'member';
    case Administrator = 'administrator';
}

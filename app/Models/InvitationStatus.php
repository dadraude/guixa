<?php

declare(strict_types=1);

namespace App\Models;

enum InvitationStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Cancelled = 'cancelled';
    case Expired = 'expired';
}

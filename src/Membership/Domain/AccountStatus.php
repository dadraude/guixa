<?php

namespace Guixa\Membership\Domain;

enum AccountStatus: string
{
    case Active = 'active';

    case InvitationPending = 'invitation_pending';

    case InvitationExpired = 'invitation_expired';

    case Deactivated = 'deactivated';
}

<?php

namespace Guixa\Membership\Application;

/** Why a role holder's remove action is off. The value is the translation key; the HTTP layer translates it. */
enum RemoveBlockedReason: string
{
    case LastActiveManager = 'It is the last active manager: another account must hold the Manager role and have accepted its invitation first.';
}

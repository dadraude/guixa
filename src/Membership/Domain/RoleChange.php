<?php

namespace Guixa\Membership\Domain;

enum RoleChange: string
{
    case Granted = 'granted';

    case Removed = 'removed';
}

<?php

namespace Guixa\Shared\Domain;

enum Role: string
{
    case Manager = 'manager';

    /** Never stored: an account is a member of household H exactly when it is linked to H (R35). */
    case Member = 'member';

    case Producer = 'producer';
}

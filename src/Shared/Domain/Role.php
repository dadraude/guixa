<?php

namespace Guixa\Shared\Domain;

enum Role: string
{
    case Manager = 'manager';

    case Finance = 'finance';

    case Catalogue = 'catalogue';

    case Receiver = 'receiver';

    /** Never stored: an account is a member of household H exactly when it is linked to H (R35). */
    case Member = 'member';

    case Producer = 'producer';

    /**
     * The roles a manager grants and removes, in the order the role management screen lists them (R9).
     *
     * @return list<self>
     */
    public static function staff(): array
    {
        return [self::Manager, self::Finance, self::Catalogue, self::Receiver];
    }

    public function isStaff(): bool
    {
        return in_array($this, self::staff(), true);
    }
}

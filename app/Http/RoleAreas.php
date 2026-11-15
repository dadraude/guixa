<?php

namespace App\Http;

use Guixa\Shared\Application\Actor;

/** The places the layout switch can send an account to, one per role it holds. */
final class RoleAreas
{
    private const AREAS = [
        'member' => ['label' => 'Orders', 'href' => '/'],
        'producer' => ['label' => 'Producer', 'href' => '/producer/products'],
        'finance' => ['label' => 'Finance', 'href' => '/finance/baskets'],
        'catalogue' => ['label' => 'Catalogue', 'href' => '/catalogue/producers'],
        'receiver' => ['label' => 'Reception', 'href' => '/receptions'],
        'manager' => ['label' => 'Management', 'href' => '/manage/cycles'],
    ];

    private const SHORTCUT_ONLY = ['catalogue', 'receiver'];

    /** @return list<RoleArea> */
    public static function for(Actor $actor): array
    {
        $areas = [];

        foreach (self::AREAS as $role => $area) {
            if ($actor->hasAnyRole($role)) {
                $areas[] = new RoleArea($role, $area['label'], $area['href']);
            }
        }

        return $areas;
    }

    /**
     * Areas with no dashboard card of their own: a header link is their whole entry (R15).
     *
     * @return list<RoleArea>
     */
    public static function shortcuts(Actor $actor): array
    {
        return array_values(array_filter(
            self::for($actor),
            fn (RoleArea $area) => in_array($area->role, self::SHORTCUT_ONLY, true),
        ));
    }
}

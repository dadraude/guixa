<?php

namespace App\Http;

use Guixa\Shared\Application\Actor;

/** The places the layout switch can send an account to, one per role it holds. */
final class RoleAreas
{
    private const AREAS = [
        'member' => ['label' => 'Orders', 'href' => '/'],
        'producer' => ['label' => 'Producer', 'href' => '/producer/products'],
        'manager' => ['label' => 'Management', 'href' => '/manage/cycles'],
    ];

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
}

<?php

namespace Guixa\Ordering\Domain;

/** A weekly cycle closes once every weekly basket, base or late, is validated or has nothing to collect, whether or not it was collected (R24). */
final class WeeklyBasketStates
{
    private const FINISHED = ['validated', 'collected', 'nothing_to_collect'];

    /** @param  list<string>  $states */
    public static function areFinished(array $states): bool
    {
        foreach ($states as $state) {
            if (! in_array($state, self::FINISHED, true)) {
                return false;
            }
        }

        return true;
    }
}

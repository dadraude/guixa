<?php

namespace Guixa\Wallet\Domain;

enum MovementType: string
{
    case TopUp = 'top_up';
    case Charge = 'charge';
    case Credit = 'credit';
    case Correction = 'correction';
}

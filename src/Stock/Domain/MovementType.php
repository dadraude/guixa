<?php

namespace Guixa\Stock\Domain;

enum MovementType: string
{
    case Entry = 'entry';
    case Adjustment = 'adjustment';
    case Sale = 'sale';
}

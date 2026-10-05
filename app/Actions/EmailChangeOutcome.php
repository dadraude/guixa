<?php

namespace App\Actions;

enum EmailChangeOutcome
{
    case Applied;
    case LinkUnusable;
    case EmailTaken;
}

<?php

namespace Guixa\Shared\Domain;

enum Role: string
{
    case Manager = 'manager';
    case Member = 'member';
    case Producer = 'producer';
}

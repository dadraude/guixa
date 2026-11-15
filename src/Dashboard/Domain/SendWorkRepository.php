<?php

namespace Guixa\Dashboard\Domain;

use Guixa\Dashboard\Domain\ValueObject\SendWork;

/** Takes no actor: the caller has already authorised the manual-send scope. */
interface SendWorkRepository
{
    /** @return list<SendWork> every producer order 001 R12a lets a manager send now */
    public function pending(): array;
}

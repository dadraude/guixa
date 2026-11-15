<?php

namespace Guixa\Dashboard\Domain;

use Guixa\Dashboard\Domain\ValueObject\ReceptionWork;

/** Takes no actor: the caller has already authorised the reception scope. */
interface ReceptionWorkRepository
{
    /** @return list<ReceptionWork> */
    public function pending(): array;
}

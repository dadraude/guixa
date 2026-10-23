<?php

namespace Guixa\Membership\Domain;

/** Port to the Catalog context: which producers exist. */
interface ProducerDirectory
{
    public function exists(string $producerId): bool;
}

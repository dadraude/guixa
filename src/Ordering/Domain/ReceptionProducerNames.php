<?php

namespace Guixa\Ordering\Domain;

/** What Ordering asks Catalog for to label the producers of the reception list (R8). */
interface ReceptionProducerNames
{
    /**
     * @param  list<string>  $producerIds
     * @return array<string, string> name by producer id; unknown producers are left out
     */
    public function of(array $producerIds): array;
}

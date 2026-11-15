<?php

namespace Guixa\Ordering\Domain;

/** Where a sent producer order stands in its reception (R8). */
enum ReceptionState: string
{
    case Sent = 'sent';

    case Validated = 'validated';

    case Distributed = 'distributed';
}

<?php

namespace Guixa\Ordering\Application;

/** The one thing a sent producer order's reception still asks of staff; a distributed reception has none (R5, R6). */
enum DashboardReceptionStage: string
{
    /** Not validated yet, whether or not anyone has opened it. */
    case Check = 'check';

    /** Validated, but an allocation no longer matches what was received. */
    case ResolveAllocations = 'resolve_allocations';

    /** Validated and balanced: ready to be marked distributed. */
    case Distribute = 'distribute';
}

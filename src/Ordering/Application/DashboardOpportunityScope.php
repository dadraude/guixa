<?php

namespace Guixa\Ordering\Application;

/** The opportunities the dashboard asks Ordering for, chosen only after it has checked the actor's household or producer. */
enum DashboardOpportunityScope
{
    case OpenWindows;
    case SentSummaries;
}

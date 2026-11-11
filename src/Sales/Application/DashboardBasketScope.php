<?php

namespace Guixa\Sales\Application;

/** The kind of basket work the dashboard asks Sales for, chosen only after it has checked the actor's gate. */
enum DashboardBasketScope
{
    case Collection;
    case Drafts;
}

<?php

namespace Guixa\Sales\Application;

/** Where an open basket stands for staff: still being prepared, ready to validate, or validated and waiting to be collected. */
enum DashboardBasketStage
{
    case Prepare;
    case Validate;
    case Collect;
}

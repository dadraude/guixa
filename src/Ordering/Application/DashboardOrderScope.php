<?php

namespace Guixa\Ordering\Application;

/** The kind of work the dashboard asks Ordering for, chosen only after it has checked the actor's gate. */
enum DashboardOrderScope
{
    case Reception;
    case ManualSend;
}

<?php

namespace Guixa\Dashboard\Application;

/** The existing screen an opportunity leads to; the HTTP layer resolves it to a URL. */
enum OpportunityArea
{
    case MemberOrder;
    case ProducerCycle;
    case CatalogueProducers;
}

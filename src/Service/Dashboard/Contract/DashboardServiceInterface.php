<?php

declare(strict_types=1);

namespace App\Service\Dashboard\Contract;

use App\Dto\Dashboard\DashboardSummaryDto;
use App\Entity\Space;

/** Summarizes the financial state of a Space for display on the dashboard. */
interface DashboardServiceInterface
{
    public function summarize(Space $space): DashboardSummaryDto;
}

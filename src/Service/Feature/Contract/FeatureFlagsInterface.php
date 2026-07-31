<?php

declare(strict_types=1);

namespace App\Service\Feature\Contract;

/** Feature flags that can be enabled/disabled per environment. */
interface FeatureFlagsInterface
{
    /** Undeclared features default to enabled. */
    public function isEnabled(string $feature): bool;
}

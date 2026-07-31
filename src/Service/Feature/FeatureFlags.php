<?php

declare(strict_types=1);

namespace App\Service\Feature;

use App\Service\Feature\Contract\FeatureFlagsInterface;

/**
 * Feature flags that can be enabled/disabled per environment.
 *
 * Values are declared in the "app.features" parameter (config/services.yaml)
 * and overridden per environment (config/services_desktop.yaml). Lets any
 * controller or service cleanly skip a feature that is unavailable in a
 * given context — e.g. email verification on a standalone desktop app
 * without a mail server.
 */
final class FeatureFlags implements FeatureFlagsInterface
{
    /**
     * @param array<string, bool> $features
     */
    public function __construct(
        private readonly array $features,
    ) {}

    public function isEnabled(string $feature): bool
    {
        return $this->features[$feature] ?? true;
    }
}

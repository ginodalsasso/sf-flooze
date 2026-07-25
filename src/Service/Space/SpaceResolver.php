<?php

declare(strict_types=1);

namespace App\Service\Space;

use App\Entity\Space;
use App\Entity\User;
use Symfony\Component\HttpFoundation\RequestStack;

class SpaceResolver
{
    public function __construct(private readonly RequestStack $requestStack) {}

    /** Returns the active space for the user, defaulting to the first non-deleted one. */
    public function resolve(User $user): ?Space
    {
        $session = $this->requestStack->getSession();
        $activeSpaceId = $session->get('flooze_active_space_id');
        $fallback = null;

        foreach ($user->getSpaces() as $space) {
            if ($space->isDeleted()) {
                continue;
            }
            if ($space->getId() === $activeSpaceId) {
                return $space;
            }
            $fallback ??= $space;
        }

        if ($fallback !== null) {
            $session->set('flooze_active_space_id', $fallback->getId());
        }

        return $fallback;
    }
}

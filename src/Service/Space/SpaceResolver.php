<?php

declare(strict_types=1);

namespace App\Service\Space;

use App\Entity\Space;
use App\Entity\User;
use Symfony\Component\HttpFoundation\RequestStack;

class SpaceResolver
{
    public function __construct(private readonly RequestStack $requestStack) {}

    /** Returns the active space for the user, defaulting to the first available. Filters out soft-deleted spaces. */
    public function resolve(User $user): ?Space
    {
        $session = $this->requestStack->getSession();
        $activeSpaceId = $session->get('flooze_active_space_id');

        foreach ($user->getSpaces() as $space) {
            if ($space->isDeleted()) {
                continue;
            }
            if ($space->getId() === $activeSpaceId) {
                return $space;
            }
        }

        $first = $user->getSpaces()->first();
        if ($first instanceof Space && !$first->isDeleted()) {
            $session->set('flooze_active_space_id', $first->getId());

            return $first;
        }

        // Fallback: find first non-deleted space if the first one was deleted
        foreach ($user->getSpaces() as $space) {
            if (!$space->isDeleted()) {
                $session->set('flooze_active_space_id', $space->getId());

                return $space;
            }
        }

        return null;
    }
}

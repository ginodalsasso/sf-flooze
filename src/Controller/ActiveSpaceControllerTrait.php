<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Space;
use App\Entity\User;
use App\Service\Space\SpaceResolver;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Provides the resolve-active-space-or-redirect boilerplate shared by every
 * space-scoped controller. Requires a $spaceResolver property (typed
 * SpaceResolver) — inject it via the controller constructor.
 */
trait ActiveSpaceControllerTrait
{
    /**
     * Returns the active Space, or a redirect Response when the user has none.
     * Optionally enforces the given access attribute (VIEW|EDIT) via the voter.
     */
    protected function resolveActiveSpace(?string $access = null): Space|RedirectResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $space = $this->spaceResolver->resolve($user);

        if ($space === null) {
            return $this->redirectToRoute('app_space_new');
        }

        if ($access !== null) {
            $this->denyAccessUnlessGranted($access, $space);
        }

        return $space;
    }
}

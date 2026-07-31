<?php

declare(strict_types=1);

namespace App\Service\Space\Contract;

use App\Entity\Space;
use App\Entity\User;

/** Resolves the space the user is currently working in. */
interface SpaceResolverInterface
{
    /** Returns the active space for the user, defaulting to the first non-deleted one. */
    public function resolve(User $user): ?Space;
}

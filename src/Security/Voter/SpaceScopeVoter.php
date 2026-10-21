<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Space;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;

class SpaceScopeVoter extends Voter
{
    // user can VIEW, EDIT and RESTORE a space if they are the owner of the space
    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, ['VIEW', 'EDIT', 'RESTORE'], true) && $subject instanceof Space;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            $vote?->addReason('The user is not logged in.');
            return false;
        }

        /** @var Space $space */
        $space = $subject;

        if ($space->getUser() !== $user) {
            $vote?->addReason('The space belongs to another user.');
            return false;
        }

        // RESTORE is the only attribute that still applies to an archived space.
        if ($space->isDeleted() && $attribute !== 'RESTORE') {
            $vote?->addReason('The space has been archived.');
            return false;
        }

        return true;
    }
}

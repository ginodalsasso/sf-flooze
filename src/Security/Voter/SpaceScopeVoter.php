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
    // user can VIEW and EDIT a space if they are the owner of the space
    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, ['VIEW', 'EDIT'], true) && $subject instanceof Space;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        /** @var Space $space */
        $space = $subject;

        return $space->getUser() === $user;
    }
}

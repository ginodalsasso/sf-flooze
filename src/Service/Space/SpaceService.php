<?php

declare(strict_types=1);

namespace App\Service\Space;

use App\Dto\Space\ArchivedSpaceDto;
use App\Entity\Space;
use App\Entity\User;
use App\Service\Space\Contract\SpaceServiceInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\ClockInterface;

final class SpaceService implements SpaceServiceInterface
{
    /** How long an archived space stays restorable, as a relative date modifier. */
    private const RESTORE_WINDOW = '+30 days';

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ClockInterface $clock,
    ) {}

    public function save(Space $space): void
    {
        $this->em->persist($space);
        $this->em->flush();
    }

    public function archive(Space $space): void
    {
        $space->softDelete();
        $this->em->flush();
    }

    public function restore(Space $space): void
    {
        if (!$space->isDeleted()) {
            return;
        }

        if (!$this->isRestorable($space)) {
            throw new \InvalidArgumentException(
                'La fenêtre de récupération de cet espace est écoulée.'
            );
        }

        // The type slot may have been re-used while the space was archived; restoring
        // anyway would leave the user with two spaces of the same type.
        if ($this->findActiveOfType($space->getUser(), $space) !== null) {
            throw new \InvalidArgumentException(
                'Un espace du même type existe déjà. Supprime-le avant de restaurer celui-ci.'
            );
        }

        $space->restore();
        $this->em->flush();
    }

    public function listActive(User $user): array
    {
        return array_values(array_filter(
            $user->getSpaces()->toArray(),
            fn(Space $space) => !$space->isDeleted(),
        ));
    }

    public function listArchived(User $user): array
    {
        $archived = [];

        foreach ($user->getSpaces() as $space) {
            if (!$space->isDeleted()) {
                continue;
            }

            $archived[] = new ArchivedSpaceDto(
                space: $space,
                restorableUntil: $this->restorableUntil($space),
                isRestorable: $this->isRestorable($space),
                isTypeTaken: $this->findActiveOfType($user, $space) !== null,
            );
        }

        return $archived;
    }

    private function restorableUntil(Space $space): \DateTimeImmutable
    {
        return $space->getDeletedAt()->modify(self::RESTORE_WINDOW);
    }

    private function isRestorable(Space $space): bool
    {
        return $this->restorableUntil($space) > $this->clock->now();
    }

    /** The active space occupying the type slot of $archived, if any. */
    private function findActiveOfType(User $user, Space $archived): ?Space
    {
        foreach ($this->listActive($user) as $space) {
            if ($space->getType() === $archived->getType()) {
                return $space;
            }
        }

        return null;
    }
}

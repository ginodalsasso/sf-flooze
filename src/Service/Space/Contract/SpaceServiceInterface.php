<?php

declare(strict_types=1);

namespace App\Service\Space\Contract;

use App\Dto\Space\ArchivedSpaceDto;
use App\Entity\Space;
use App\Entity\User;

/**
 * Space lifecycle. Deleting a space is never physical: it holds the whole ledger of a
 * tenant, so it is archived and stays restorable for a bounded window.
 */
interface SpaceServiceInterface
{
    public function save(Space $space): void;

    /** Soft-deletes the space; its data stays in place and restorable until the window closes. */
    public function archive(Space $space): void;

    /**
     * @throws \InvalidArgumentException when the window has closed, or when an active space
     *                                   of the same type has been created in the meantime
     */
    public function restore(Space $space): void;

    /** @return list<Space> */
    public function listActive(User $user): array;

    /** @return list<ArchivedSpaceDto> */
    public function listArchived(User $user): array;
}

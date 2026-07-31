<?php

declare(strict_types=1);

namespace App\Repository\Contract;

use App\Entity\Account;
use App\Entity\Space;
use App\Enum\AccountTypeEnum;
use Doctrine\Persistence\ObjectRepository;

/**
 * @extends ObjectRepository<Account>
 */
interface AccountRepositoryInterface extends ObjectRepository
{
    /** @return Account[] active accounts for the space, ordered by name */
    public function findBySpace(Space $space): array;

    /** Count active accounts for the space. */
    public function countBySpace(Space $space): int;

    /** @return Account[] active accounts of the given type for the space, ordered by name */
    public function findBySpaceAndType(Space $space, AccountTypeEnum $type): array;
}

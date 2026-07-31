<?php

declare(strict_types=1);

namespace App\Repository\Contract;

use App\Entity\User;
use Doctrine\Persistence\ObjectRepository;
use Symfony\Bridge\Doctrine\Security\User\UserLoaderInterface;

/**
 * @extends ObjectRepository<User>
 */
interface UserRepositoryInterface extends ObjectRepository, UserLoaderInterface
{
    /** Active user matching the identifier, used by the security layer. */
    public function loadUserByIdentifier(string $identifier): ?User;
}

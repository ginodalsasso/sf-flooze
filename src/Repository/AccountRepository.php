<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Account;
use App\Entity\Space;
use App\Enum\AccountTypeEnum;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Account>
 */
class AccountRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Account::class);
    }

    /** @return Account[] active accounts for the space, ordered by name */
    public function findBySpace(Space $space): array
    {
        return $this->createQueryBuilder('a')
            ->where('a.space = :space')
            ->andWhere('a.deletedAt IS NULL')
            ->setParameter('space', $space)
            ->orderBy('a.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** @return Account[] active accounts of the given type for the space, ordered by name */
    public function findBySpaceAndType(Space $space, AccountTypeEnum $type): array
    {
        return $this->createQueryBuilder('a')
            ->where('a.space = :space')
            ->andWhere('a.type = :type')
            ->andWhere('a.deletedAt IS NULL')
            ->setParameter('space', $space)
            ->setParameter('type', $type)
            ->orderBy('a.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}

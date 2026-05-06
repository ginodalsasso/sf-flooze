<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Account;
use App\Entity\Space;
use App\Entity\Transaction;
use App\Enum\TransactionTypeEnum;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Transaction>
 */
class TransactionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Transaction::class);
    }

    /**
     * @return Transaction[] active transactions for the space, most recent first
     */
    public function findBySpace(Space $space, ?TransactionTypeEnum $type = null, ?Account $account = null): array
    {
        $qb = $this->createQueryBuilder('t')
            ->join('t.account', 'a')
            ->leftJoin('t.category', 'c')
            ->where('t.space = :space')
            ->andWhere('t.deletedAt IS NULL')
            ->setParameter('space', $space)
            ->orderBy('t.date', 'DESC')
            ->addOrderBy('t.createdAt', 'DESC');

        if ($type !== null) {
            $qb->andWhere('t.type = :type')->setParameter('type', $type);
        }

        if ($account !== null) {
            $qb->andWhere('t.account = :account')->setParameter('account', $account);
        }

        return $qb->getQuery()->getResult();
    }

    /** @return Transaction[] most recent N transactions for dashboard widget */
    public function findRecentBySpace(Space $space, int $limit = 10): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.space = :space')
            ->andWhere('t.deletedAt IS NULL')
            ->setParameter('space', $space)
            ->orderBy('t.date', 'DESC')
            ->addOrderBy('t.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}

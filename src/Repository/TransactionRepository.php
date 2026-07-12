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
     * @return Transaction[] active transactions for the space, most recent first.
     *         Excludes transactions whose account has been soft-deleted.
     */
    public function findBySpace(Space $space, ?TransactionTypeEnum $type = null, ?Account $account = null): array
    {
        $qb = $this->createQueryBuilder('t')
            ->join('t.account', 'a')
            ->leftJoin('t.category', 'c')
            ->leftJoin('t.destinationAccount', 'da')
            ->where('t.space = :space')
            ->andWhere('t.deletedAt IS NULL')
            ->andWhere('a.deletedAt IS NULL')
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

    /** @return Transaction[] most recent N transactions for dashboard widget.
     *          Excludes transactions whose account has been soft-deleted.
     */
    public function findRecentBySpace(Space $space, int $limit = 10): array
    {
        return $this->createQueryBuilder('t')
            ->join('t.account', 'a')
            ->where('t.space = :space')
            ->andWhere('t.deletedAt IS NULL')
            ->andWhere('a.deletedAt IS NULL')
            ->setParameter('space', $space)
            ->orderBy('t.date', 'DESC')
            ->addOrderBy('t.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /** Total amount for a transaction type within a date range. */
    public function sumBySpaceAndTypeAndDateRange(
        Space $space,
        TransactionTypeEnum $type,
        \DateTimeImmutable $start,
        \DateTimeImmutable $end,
    ): string {
        $result = $this->createQueryBuilder('t')
            ->select('SUM(t.amount)')
            ->join('t.account', 'a')
            ->where('t.space = :space')
            ->andWhere('t.type = :type')
            ->andWhere('t.date >= :start')
            ->andWhere('t.date < :end')
            ->andWhere('t.deletedAt IS NULL')
            ->andWhere('a.deletedAt IS NULL')
            ->setParameter('space', $space)
            ->setParameter('type', $type)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->getQuery()
            ->getSingleScalarResult();

        return $result ?? '0';
    }

    /** Total amount for a transaction type on a given account within a date range. */
    public function sumByAccountAndTypeAndDateRange(
        Account $account,
        TransactionTypeEnum $type,
        \DateTimeImmutable $start,
        \DateTimeImmutable $end,
    ): string {
        $result = $this->createQueryBuilder('t')
            ->select('SUM(t.amount)')
            ->where('t.account = :account')
            ->andWhere('t.type = :type')
            ->andWhere('t.date >= :start')
            ->andWhere('t.date < :end')
            ->andWhere('t.deletedAt IS NULL')
            ->setParameter('account', $account)
            ->setParameter('type', $type)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->getQuery()
            ->getSingleScalarResult();

        return $result ?? '0';
    }
}

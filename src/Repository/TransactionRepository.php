<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Account;
use App\Entity\Space;
use App\Entity\Transaction;
use App\Enum\TransactionTypeEnum;
use App\Repository\Contract\TransactionRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Transaction>
 */
final class TransactionRepository extends ServiceEntityRepository implements TransactionRepositoryInterface
{
    /**
     * A transfer belongs to two accounts: it stays visible as long as one of them is
     * still active, otherwise soft-deleting the source would hide a movement that the
     * destination account was really credited with. Requires the 'a' and 'da' joins.
     */
    private const ON_ACTIVE_ACCOUNT = "a.deletedAt IS NULL OR (t.type = 'transfer' AND t.destinationAccount IS NOT NULL AND da.deletedAt IS NULL)";

    /** The second leg of a movement, which only a transfer has. */
    private const CREDITED_ACCOUNT = "t.type = 'transfer' AND t.destinationAccount = :account";

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Transaction::class);
    }

    /**
     * @return Transaction[] active transactions for the space, most recent first.
     *         An account filter matches both legs of a transfer: the destination
     *         account is credited, so the movement belongs to its history too.
     */
    public function findBySpace(Space $space, ?TransactionTypeEnum $type = null, ?Account $account = null): array
    {
        $qb = $this->createQueryBuilder('t')
            ->join('t.account', 'a')
            ->leftJoin('t.category', 'c')
            ->leftJoin('t.destinationAccount', 'da')
            ->where('t.space = :space')
            ->andWhere('t.deletedAt IS NULL')
            ->andWhere(self::ON_ACTIVE_ACCOUNT)
            ->setParameter('space', $space)
            ->orderBy('t.date', 'DESC')
            ->addOrderBy('t.createdAt', 'DESC');

        if ($type !== null) {
            $qb->andWhere('t.type = :type')->setParameter('type', $type);
        }

        if ($account !== null) {
            $qb->andWhere('t.account = :account OR (' . self::CREDITED_ACCOUNT . ')')
                ->setParameter('account', $account);
        }

        return $qb->getQuery()->getResult();
    }

    /** @return Transaction[] most recent N transactions for dashboard widget. */
    public function findRecentBySpace(Space $space, int $limit = 10): array
    {
        return $this->createQueryBuilder('t')
            ->join('t.account', 'a')
            ->leftJoin('t.destinationAccount', 'da')
            ->where('t.space = :space')
            ->andWhere('t.deletedAt IS NULL')
            ->andWhere(self::ON_ACTIVE_ACCOUNT)
            ->setParameter('space', $space)
            ->orderBy('t.date', 'DESC')
            ->addOrderBy('t.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Total amount for a transaction type within a date range, converted to the space
     * currency: accounts of the space may hold different currencies, so raw amounts
     * cannot be added together.
     */
    public function sumBySpaceAndTypeAndDateRange(
        Space $space,
        TransactionTypeEnum $type,
        \DateTimeImmutable $start,
        \DateTimeImmutable $end,
    ): string {
        $result = $this->createQueryBuilder('t')
            ->select('SUM(t.amount * t.fxRate)')
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

    /**
     * Net movement of every active transaction touching the account, in its own currency.
     *
     * A transaction is either outgoing (the account is the source: income adds, expense and
     * transfer subtract) or incoming (the account is credited as the destination of a transfer,
     * with destination_amount when the currencies differ). Only a transfer credits a destination:
     * income and expense rows may carry a stale destination_account_id and must ignore it.
     *
     * The source leg is matched first, so the ELSE branch can only be reached by an incoming
     * transfer — including the degenerate case of a row pointing at the same account twice.
     *
     * $excludedTransaction is left out of the total although still stored: passing the row being
     * edited gives the balance as it will be once the new values are saved.
     */
    public function getBalanceDelta(Account $account, ?Transaction $excludedTransaction = null): string
    {
        $qb = $this->createQueryBuilder('t')
            ->select('SUM(
                CASE
                    WHEN t.account = :account AND t.type = :income THEN t.amount
                    WHEN t.account = :account THEN t.amount * -1
                    ELSE COALESCE(t.destinationAmount, t.amount)
                END
            )')
            ->where('t.account = :account OR (' . self::CREDITED_ACCOUNT . ')')
            ->andWhere('t.deletedAt IS NULL')
            ->setParameter('account', $account)
            ->setParameter('income', TransactionTypeEnum::INCOME);

        if ($excludedTransaction?->getId() !== null) {
            $qb->andWhere('t.id != :excluded')->setParameter('excluded', $excludedTransaction->getId());
        }

        $delta = $qb->getQuery()->getSingleScalarResult();

        // SUM returns null when no row matches: an account without movement has a zero delta.
        return bcadd((string) ($delta ?? '0'), '0', 2);
    }

    /**
     * Number of active transactions moving money on the account, incoming transfers included:
     * they credit its balance, so they make it just as computed as an outgoing one.
     */
    public function countByAccount(Account $account): int
    {
        return (int) $this->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->where('t.account = :account OR (' . self::CREDITED_ACCOUNT . ')')
            ->andWhere('t.deletedAt IS NULL')
            ->setParameter('account', $account)
            ->getQuery()
            ->getSingleScalarResult();
    }
}

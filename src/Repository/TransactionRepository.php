<?php

declare(strict_types=1);

namespace App\Repository;

use App\Dto\Finance\TransactionFilterDto;
use App\Dto\Finance\TransactionTotalsDto;
use App\Entity\Account;
use App\Entity\Space;
use App\Entity\Transaction;
use App\Enum\TransactionTypeEnum;
use App\Repository\Contract\TransactionRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
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

    /**
     * Free text over the columns the list displays. Both account names are searched
     * because a transfer shows them in the same cell. Requires the 'a', 'c' and 'da' joins.
     */
    private const TEXT_SEARCH = 'LOWER(t.description) LIKE :q OR LOWER(c.name) LIKE :q '
        . 'OR LOWER(a.name) LIKE :q OR LOWER(da.name) LIKE :q';

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Transaction::class);
    }

    /** @return Transaction[] active transactions of the space matching the filter, most recent first. */
    public function findByFilter(Space $space, TransactionFilterDto $filter): array
    {
        $qb = $this->createSpaceScopedQb($space);
        $this->applyFilter($qb, $filter);

        return $qb
            ->orderBy('t.date', 'DESC')
            ->addOrderBy('t.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Count and income/expense totals of the same result set, aggregated by the database.
     *
     * Amounts are converted to the space currency (amount × fx_rate): accounts of the
     * space may hold different currencies, so raw amounts cannot be added together.
     */
    public function sumByFilter(Space $space, TransactionFilterDto $filter): TransactionTotalsDto
    {
        $qb = $this->createSpaceScopedQb($space)
            ->select(
                'COUNT(t.id) AS nb',
                'COALESCE(SUM(CASE WHEN t.type = :incomeType THEN t.amount * t.fxRate ELSE 0 END), 0) AS income',
                'COALESCE(SUM(CASE WHEN t.type = :expenseType THEN t.amount * t.fxRate ELSE 0 END), 0) AS expense',
            )
            ->setParameter('incomeType', TransactionTypeEnum::INCOME)
            ->setParameter('expenseType', TransactionTypeEnum::EXPENSE);

        $this->applyFilter($qb, $filter);

        $row = $qb->getQuery()->getSingleResult();

        return new TransactionTotalsDto(
            count: (int) $row['nb'],
            income: bcadd((string) $row['income'], '0', 2),
            expense: bcadd((string) $row['expense'], '0', 2),
        );
    }

    /** Active transactions of the space, with the joins every filter and the list rely on. */
    private function createSpaceScopedQb(Space $space): QueryBuilder
    {
        return $this->createQueryBuilder('t')
            ->join('t.account', 'a')
            ->leftJoin('t.category', 'c')
            ->leftJoin('t.destinationAccount', 'da')
            ->where('t.space = :space')
            ->andWhere('t.deletedAt IS NULL')
            ->andWhere(self::ON_ACTIVE_ACCOUNT)
            ->setParameter('space', $space);
    }

    /**
     * Narrows the query to the criteria that are set. An account matches both legs of a
     * transfer: the destination account is credited, so the movement belongs to its
     * history too. Amount bounds compare converted amounts, as the totals do.
     */
    private function applyFilter(QueryBuilder $qb, TransactionFilterDto $filter): void
    {
        if ($filter->type !== null) {
            $qb->andWhere('t.type = :type')->setParameter('type', $filter->type);
        }

        if ($filter->account !== null) {
            $qb->andWhere('t.account = :account OR (' . self::CREDITED_ACCOUNT . ')')
                ->setParameter('account', $filter->account);
        }

        if ($filter->category !== null) {
            $qb->andWhere('t.category = :category')->setParameter('category', $filter->category);
        }

        // MEMBER OF and not a join: the same query builder feeds sumByFilter(), and joining a
        // ManyToMany would duplicate rows on multi-tag transactions, inflating the totals.
        if ($filter->tag !== null) {
            $qb->andWhere(':tag MEMBER OF t.tags')->setParameter('tag', $filter->tag);
        }

        if ($filter->dateFrom !== null) {
            $qb->andWhere('t.date >= :dateFrom')->setParameter('dateFrom', $filter->dateFrom);
        }

        if ($filter->dateTo !== null) {
            $qb->andWhere('t.date <= :dateTo')->setParameter('dateTo', $filter->dateTo);
        }

        if ($filter->amountMin !== null) {
            $qb->andWhere('t.amount * t.fxRate >= :amountMin')->setParameter('amountMin', $filter->amountMin);
        }

        if ($filter->amountMax !== null) {
            $qb->andWhere('t.amount * t.fxRate <= :amountMax')->setParameter('amountMax', $filter->amountMax);
        }

        if ($filter->query !== null && $filter->query !== '') {
            $qb->andWhere(self::TEXT_SEARCH)
                ->setParameter('q', '%' . strtolower(addcslashes($filter->query, '%_\\')) . '%');
        }
    }

    /**
     * Number of active transactions carrying each tag of the space, keyed by tag id.
     * Tags used by nothing are absent: the caller reads a missing key as zero.
     *
     * @return array<int, int>
     */
    public function countByTag(Space $space): array
    {
        $rows = $this->createQueryBuilder('t')
            ->select('tg.id AS tagId', 'COUNT(t.id) AS nb')
            ->join('t.tags', 'tg')
            ->where('t.space = :space')
            ->andWhere('t.deletedAt IS NULL')
            ->setParameter('space', $space)
            ->groupBy('tg.id')
            ->getQuery()
            ->getResult();

        $tagCounts = array_column($rows, 'nb', 'tagId');

        return array_map(intval(...), $tagCounts);
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

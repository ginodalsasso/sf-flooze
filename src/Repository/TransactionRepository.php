<?php

declare(strict_types=1);

namespace App\Repository;

use App\Dto\DateRangeDto;
use App\Dto\Finance\TransactionFilterDto;
use App\Dto\Finance\TransactionTotalsDto;
use App\Entity\Account;
use App\Entity\Space;
use App\Entity\Transaction;
use App\Enum\FiscalBucketEnum;
use App\Enum\FiscalRoleEnum;
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
        DateRangeDto $range,
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
            ->setParameter('start', $range->from)
            ->setParameter('end', $range->toExclusive)
            ->getQuery()
            ->getSingleScalarResult();

        return $result ?? '0';
    }

    /** Total amount for a transaction type on a given account within a date range. */
    public function sumByAccountAndTypeAndDateRange(
        Account $account,
        TransactionTypeEnum $type,
        DateRangeDto $range,
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
            ->setParameter('start', $range->from)
            ->setParameter('end', $range->toExclusive)
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

    /**
     * Totals per category for one fiscal role, grouped by the fiscal bucket they belong to.
     *
     * The bucket is what makes the declaration sound: a charge is only ever deducted from the
     * income of its own bucket, so totals are never added across buckets.
     *
     * @return list<array{bucket: FiscalBucketEnum, categoryId: int, categoryName: string, total: string, nb: int}>
     */
    public function sumByFiscalRole(Space $space, DateRangeDto $range, FiscalRoleEnum $role): array
    {
        $rows = $this->createTaxScopedQb($space, $range)
            ->select(
                'c.fiscalBucket AS bucket',
                'c.id AS categoryId',
                'c.name AS categoryName',
                'COALESCE(SUM(t.amount * t.fxRate), 0) AS total',
                'COUNT(t.id) AS nb',
            )
            ->andWhere('c.fiscalRole = :role')
            ->andWhere('c.fiscalBucket IS NOT NULL')
            ->setParameter('role', $role)
            ->groupBy('c.fiscalBucket')
            ->addGroupBy('c.id')
            ->addGroupBy('c.name')
            ->orderBy('total', 'DESC')
            ->getQuery()
            ->getResult();

        return array_map(static fn(array $row) => [
            // A scalar select returns the stored value, not the hydrated enum.
            'bucket'       => $row['bucket'] instanceof FiscalBucketEnum
                ? $row['bucket']
                : FiscalBucketEnum::from((string) $row['bucket']),
            'categoryId'   => (int) $row['categoryId'],
            'categoryName' => (string) $row['categoryName'],
            'total'        => bcadd((string) $row['total'], '0', 2),
            'nb'           => (int) $row['nb'],
        ], $rows);
    }

    /**
     * The transactions behind the declaration lines, so every total can be opened and checked.
     * Ordered by category to be grouped for display without re-summing.
     *
     * @return Transaction[]
     */
    public function findTaxRelevantByCategory(Space $space, DateRangeDto $range): array
    {
        return $this->createTaxScopedQb($space, $range)
            ->andWhere('c.fiscalBucket IS NOT NULL')
            ->andWhere('c.fiscalRole IS NOT NULL')
            ->orderBy('c.name', 'ASC')
            ->addOrderBy('t.date', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * What the declaration does NOT count: movements with no category, or one whose fiscal
     * scope is incomplete. Surfacing them is the point — an omission is the likeliest mistake.
     *
     * @return array{income: string, expense: string, nb: int}
     */
    public function sumOutsideTaxScope(Space $space, DateRangeDto $range): array
    {
        $row = $this->createTaxScopedQb($space, $range)
            ->select(
                'COUNT(t.id) AS nb',
                'COALESCE(SUM(CASE WHEN t.type = :incomeType THEN t.amount * t.fxRate ELSE 0 END), 0) AS income',
                'COALESCE(SUM(CASE WHEN t.type = :expenseType THEN t.amount * t.fxRate ELSE 0 END), 0) AS expense',
            )
            ->andWhere('(t.category IS NULL OR c.fiscalBucket IS NULL OR c.fiscalRole IS NULL)')
            ->setParameter('incomeType', TransactionTypeEnum::INCOME)
            ->setParameter('expenseType', TransactionTypeEnum::EXPENSE)
            ->getQuery()
            ->getSingleResult();

        return [
            'income'  => bcadd((string) $row['income'], '0', 2),
            'expense' => bcadd((string) $row['expense'], '0', 2),
            'nb'      => (int) $row['nb'],
        ];
    }

    /**
     * Years the space actually holds movements in, most recent first.
     *
     * Derived from the first and last dates rather than a YEAR() grouping, which is a
     * vendor-specific DQL function.
     *
     * @return list<int>
     */
    public function findYearsWithTransactions(Space $space): array
    {
        $row = $this->createQueryBuilder('t')
            ->select('MIN(t.date) AS firstDate', 'MAX(t.date) AS lastDate')
            ->where('t.space = :space')
            ->andWhere('t.deletedAt IS NULL')
            ->setParameter('space', $space)
            ->getQuery()
            ->getSingleResult();

        if ($row['firstDate'] === null) {
            return [];
        }

        // Parsing a stored date, not reading "now": the clock rule does not apply here.
        $first = (int) (new \DateTimeImmutable((string) $row['firstDate']))->format('Y');
        $last = (int) (new \DateTimeImmutable((string) $row['lastDate']))->format('Y');

        return range($last, $first);
    }

    /**
     * Base of every declaration figure: active movements of the period, transfers excluded.
     *
     * A transfer moves money between the owner's own accounts — counting it as income or
     * charge would inflate the declaration twice over.
     */
    private function createTaxScopedQb(Space $space, DateRangeDto $range): QueryBuilder
    {
        return $this->createQueryBuilder('t')
            ->join('t.account', 'a')
            ->leftJoin('t.category', 'c')
            ->where('t.space = :space')
            ->andWhere('t.deletedAt IS NULL')
            ->andWhere('a.deletedAt IS NULL')
            ->andWhere('t.type != :transferType')
            ->andWhere('t.date >= :start')
            ->andWhere('t.date < :end')
            ->setParameter('space', $space)
            ->setParameter('transferType', TransactionTypeEnum::TRANSFER)
            ->setParameter('start', $range->from)
            ->setParameter('end', $range->toExclusive);
    }
}

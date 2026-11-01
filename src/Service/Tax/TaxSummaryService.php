<?php

declare(strict_types=1);

namespace App\Service\Tax;

use App\Dto\DateRangeDto;
use App\Dto\Tax\TaxBucketDto;
use App\Dto\Tax\TaxCategoryLineDto;
use App\Dto\Tax\TaxSummaryDto;
use App\Entity\Space;
use App\Entity\Transaction;
use App\Enum\FiscalBucketEnum;
use App\Enum\FiscalRoleEnum;
use App\Repository\Contract\TransactionRepositoryInterface;
use App\Service\Tax\Contract\TaxSummaryServiceInterface;
use Symfony\Component\Clock\ClockInterface;

final class TaxSummaryService implements TaxSummaryServiceInterface
{
    public function __construct(
        private readonly TransactionRepositoryInterface $transactionRepository,
        private readonly ClockInterface $clock,
    ) {}

    public function summarize(Space $space, ?int $year = null): TaxSummaryDto
    {
        $availableYears = $this->transactionRepository->findYearsWithTransactions($space);
        $year = $this->resolveYear($year, $availableYears);
        $range = DateRangeDto::forYear($year);

        $transactionsByCategory = $this->groupByCategory(
            $this->transactionRepository->findTaxRelevantByCategory($space, $range),
        );

        $revenus = $this->linesByBucket($space, $range, FiscalRoleEnum::REVENU, $transactionsByCategory);
        $charges = $this->linesByBucket($space, $range, FiscalRoleEnum::CHARGE, $transactionsByCategory);
        $buckets = $this->buildBuckets($revenus, $charges);
        $outside = $this->transactionRepository->sumOutsideTaxScope($space, $range);

        return new TaxSummaryDto(
            year: $year,
            buckets: $buckets,
            revenusTotal: $this->totalOf($buckets, static fn(TaxBucketDto $b) => $b->revenusTotal),
            chargesTotal: $this->totalOf($buckets, static fn(TaxBucketDto $b) => $b->chargesTotal),
            outsideScopeIncome: $outside['income'],
            outsideScopeExpense: $outside['expense'],
            outsideScopeCount: $outside['nb'],
            currency: $space->getCurrency(),
            availableYears: $this->offerYears($availableYears),
        );
    }

    /**
     * @param array<int, Transaction[]> $transactionsByCategory
     *
     * @return array<string, TaxCategoryLineDto[]> keyed by bucket value
     */
    private function linesByBucket(
        Space $space,
        DateRangeDto $range,
        FiscalRoleEnum $role,
        array $transactionsByCategory,
    ): array {
        $grouped = [];

        foreach ($this->transactionRepository->sumByFiscalRole($space, $range, $role) as $row) {
            $grouped[$row['bucket']->value][] = new TaxCategoryLineDto(
                categoryName: $row['categoryName'],
                total: $row['total'],
                transactionCount: $row['nb'],
                transactions: $transactionsByCategory[$row['categoryId']] ?? [],
            );
        }

        return $grouped;
    }

    /**
     * One bucket per catégorie de revenus that holds anything, heaviest income first.
     *
     * A bucket with charges but no income still appears: a déficit foncier is a real outcome,
     * and hiding it would lose a deduction.
     *
     * @param array<string, TaxCategoryLineDto[]> $revenus
     * @param array<string, TaxCategoryLineDto[]> $charges
     *
     * @return TaxBucketDto[]
     */
    private function buildBuckets(array $revenus, array $charges): array
    {
        $buckets = [];

        foreach (FiscalBucketEnum::cases() as $bucket) {
            $bucketRevenus = $revenus[$bucket->value] ?? [];
            $bucketCharges = $charges[$bucket->value] ?? [];

            if ($bucketRevenus === [] && $bucketCharges === []) {
                continue;
            }

            $buckets[] = new TaxBucketDto(
                bucket: $bucket,
                revenus: $bucketRevenus,
                charges: $bucketCharges,
                revenusTotal: $this->sumLines($bucketRevenus),
                chargesTotal: $this->sumLines($bucketCharges),
            );
        }

        usort($buckets, static fn(TaxBucketDto $a, TaxBucketDto $b) => bccomp($b->revenusTotal, $a->revenusTotal, 2));

        return $buckets;
    }

    /**
     * A forged or stale year never reaches a query: only a year holding movements, or the
     * current one, is accepted. Defaults to the most recent year with data.
     *
     * @param list<int> $availableYears
     */
    private function resolveYear(?int $year, array $availableYears): int
    {
        $offered = $this->offerYears($availableYears);

        if ($year !== null && in_array($year, $offered, true)) {
            return $year;
        }

        return $availableYears[0] ?? $this->currentYear();
    }

    /**
     * Years the selector may show: those with movements, plus the current one so a year
     * just started is reachable before it holds anything.
     *
     * @param list<int> $availableYears
     *
     * @return list<int>
     */
    private function offerYears(array $availableYears): array
    {
        $years = $availableYears;

        if (!in_array($this->currentYear(), $years, true)) {
            $years[] = $this->currentYear();
        }

        rsort($years);

        return $years;
    }

    private function currentYear(): int
    {
        return (int) $this->clock->now()->format('Y');
    }

    /**
     * @param Transaction[] $transactions
     *
     * @return array<int, Transaction[]> keyed by category id
     */
    private function groupByCategory(array $transactions): array
    {
        $grouped = [];

        foreach ($transactions as $transaction) {
            $categoryId = $transaction->getCategory()?->getId();

            if ($categoryId !== null) {
                $grouped[$categoryId][] = $transaction;
            }
        }

        return $grouped;
    }

    /**
     * Adds the lines the page displays, not the raw rows: what is shown always adds up to
     * the total shown next to it.
     *
     * @param TaxCategoryLineDto[] $lines
     */
    private function sumLines(array $lines): string
    {
        $total = '0.00';

        foreach ($lines as $line) {
            $total = bcadd($total, $line->total, 2);
        }

        return $total;
    }

    /**
     * @param TaxBucketDto[]                 $buckets
     * @param callable(TaxBucketDto): string $part
     */
    private function totalOf(array $buckets, callable $part): string
    {
        $total = '0.00';

        foreach ($buckets as $bucket) {
            $total = bcadd($total, $part($bucket), 2);
        }

        return $total;
    }
}

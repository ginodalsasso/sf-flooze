<?php

declare(strict_types=1);

namespace App\Repository;

use App\Dto\Finance\AssetEntryFilterDto;
use App\Entity\Account;
use App\Entity\Asset;
use App\Entity\AssetEntry;
use App\Enum\AssetEntryKindEnum;
use App\Repository\Contract\AssetEntryRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AssetEntry>
 */
final class AssetEntryRepository extends ServiceEntityRepository implements AssetEntryRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AssetEntry::class);
    }

    /**
     * @return AssetEntry[] active entries for the asset matching the filter, most recent first
     */
    public function findByAsset(Asset $asset, ?AssetEntryFilterDto $filter = null): array
    {
        $qb = $this->createQueryBuilder('e')
            ->where('e.asset = :asset')
            ->andWhere('e.deletedAt IS NULL')
            ->setParameter('asset', $asset)
            ->orderBy('e.date', 'DESC')
            ->addOrderBy('e.createdAt', 'DESC');

        if ($filter?->kind !== null) {
            $qb->andWhere('e.kind = :kind')->setParameter('kind', $filter->kind);
        }

        if ($filter?->dateFrom !== null) {
            $qb->andWhere('e.date >= :dateFrom')->setParameter('dateFrom', $filter->dateFrom);
        }

        if ($filter?->dateTo !== null) {
            $qb->andWhere('e.date <= :dateTo')->setParameter('dateTo', $filter->dateTo);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * @return AssetEntry[] active buy entries for the asset, ordered by date (FIFO)
     */
    public function findBuysByAsset(Asset $asset): array
    {
        return $this->createQueryBuilder('e')
            ->where('e.asset = :asset')
            ->andWhere('e.kind = :kind')
            ->andWhere('e.deletedAt IS NULL')
            ->setParameter('asset', $asset)
            ->setParameter('kind', AssetEntryKindEnum::BUY)
            ->orderBy('e.date', 'ASC')
            ->addOrderBy('e.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** Dividends carry no unit price, so they never speak for the value of the asset. */
    public function findLatestTrade(Asset $asset): ?AssetEntry
    {
        return $this->createQueryBuilder('e')
            ->where('e.asset = :asset')
            ->andWhere('e.kind IN (:kinds)')
            ->andWhere('e.deletedAt IS NULL')
            ->setParameter('asset', $asset)
            ->setParameter('kinds', [AssetEntryKindEnum::BUY, AssetEntryKindEnum::SELL])
            ->orderBy('e.date', 'DESC')
            ->addOrderBy('e.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /** Net quantity held (buy - sell), excluding soft-deleted entries. */
    public function getTotalQuantity(Asset $asset): string
    {
        $result = $this->createQueryBuilder('e')
            ->select('SUM(e.quantity * CASE WHEN e.kind = :buy THEN 1 WHEN e.kind = :sell THEN -1 ELSE 0 END)')
            ->where('e.asset = :asset')
            ->andWhere('e.deletedAt IS NULL')
            ->setParameter('asset', $asset)
            ->setParameter('buy', AssetEntryKindEnum::BUY)
            ->setParameter('sell', AssetEntryKindEnum::SELL)
            ->getQuery()
            ->getSingleScalarResult();

        return $result ?? '0';
    }

    /** Sum of dividends received for the asset, excluding soft-deleted entries. */
    public function getTotalDividends(Asset $asset): string
    {
        $result = $this->createQueryBuilder('e')
            ->select('SUM(e.quantity * e.unitPrice * e.fxRate)')
            ->where('e.asset = :asset')
            ->andWhere('e.kind = :kind')
            ->andWhere('e.deletedAt IS NULL')
            ->setParameter('asset', $asset)
            ->setParameter('kind', AssetEntryKindEnum::DIVIDEND)
            ->getQuery()
            ->getSingleScalarResult();

        return $result ?? '0';
    }

    /** Sum of fees paid across all active entries for the asset. */
    public function getTotalFees(Asset $asset): string
    {
        $result = $this->createQueryBuilder('e')
            ->select('SUM(e.fees)')
            ->where('e.asset = :asset')
            ->andWhere('e.deletedAt IS NULL')
            ->setParameter('asset', $asset)
            ->getQuery()
            ->getSingleScalarResult();

        return $result ?? '0';
    }

    /** Total cost basis in asset currency, excluding soft-deleted entries. */
    public function getTotalCost(Asset $asset): string
    {
        $result = $this->createQueryBuilder('e')
            ->select('SUM(e.quantity * e.unitPrice)')
            ->where('e.asset = :asset')
            ->andWhere('e.kind = :kind')
            ->andWhere('e.deletedAt IS NULL')
            ->setParameter('asset', $asset)
            ->setParameter('kind', AssetEntryKindEnum::BUY)
            ->getQuery()
            ->getSingleScalarResult();

        return $result ?? '0';
    }

    /** Total amount currently invested in assets held by this account (BUY - SELL in space currency). */
    public function getInvestedBalance(Account $account): string
    {
        $result = $this->createQueryBuilder('e')
            ->select('SUM(e.quantity * e.unitPrice * e.fxRate * CASE WHEN e.kind = :buy THEN 1 WHEN e.kind = :sell THEN -1 ELSE 0 END)')
            ->where('e.account = :account')
            ->andWhere('e.deletedAt IS NULL')
            ->setParameter('account', $account)
            ->setParameter('buy', AssetEntryKindEnum::BUY)
            ->setParameter('sell', AssetEntryKindEnum::SELL)
            ->getQuery()
            ->getSingleScalarResult();

        return $result ?? '0';
    }

    /** Total cost basis in space currency (with historical FX), excluding soft-deleted entries. */
    public function getTotalCostInSpaceCurrency(Asset $asset): string
    {
        $result = $this->createQueryBuilder('e')
            ->select('SUM(e.quantity * e.unitPrice * e.fxRate)')
            ->where('e.asset = :asset')
            ->andWhere('e.kind = :kind')
            ->andWhere('e.deletedAt IS NULL')
            ->setParameter('asset', $asset)
            ->setParameter('kind', AssetEntryKindEnum::BUY)
            ->getQuery()
            ->getSingleScalarResult();

        return $result ?? '0';
    }

    /** Weighted average purchase price in asset currency, excluding soft-deleted entries. */
    public function getAveragePrice(Asset $asset): ?string
    {
        $result = $this->createQueryBuilder('e')
            ->select('SUM(e.quantity * e.unitPrice) AS totalCost', 'SUM(e.quantity) AS totalQty')
            ->where('e.asset = :asset')
            ->andWhere('e.kind = :kind')
            ->andWhere('e.deletedAt IS NULL')
            ->setParameter('asset', $asset)
            ->setParameter('kind', AssetEntryKindEnum::BUY)
            ->getQuery()
            ->getOneOrNullResult();

        $totalQty = $result['totalQty'] ?? '0';
        $totalCost = $result['totalCost'] ?? '0';

        if (bccomp($totalQty, '0', 8) <= 0) {
            return null;
        }

        // bcdiv: divide numeric strings, scale 4 for average price precision.
        return bcdiv($totalCost, $totalQty, 4);
    }

    /** Weighted average purchase price in space currency (with historical FX), excluding soft-deleted entries. */
    public function getAveragePriceInSpaceCurrency(Asset $asset): ?string
    {
        $result = $this->createQueryBuilder('e')
            ->select('SUM(e.quantity * e.unitPrice * e.fxRate) AS totalCost', 'SUM(e.quantity) AS totalQty')
            ->where('e.asset = :asset')
            ->andWhere('e.kind = :kind')
            ->andWhere('e.deletedAt IS NULL')
            ->setParameter('asset', $asset)
            ->setParameter('kind', AssetEntryKindEnum::BUY)
            ->getQuery()
            ->getOneOrNullResult();

        $totalQty = $result['totalQty'] ?? '0';
        $totalCost = $result['totalCost'] ?? '0';

        if (bccomp($totalQty, '0', 8) <= 0) {
            return null;
        }

        // bcdiv: divide numeric strings, scale 4 for average price precision.
        return bcdiv($totalCost, $totalQty, 4);
    }
}

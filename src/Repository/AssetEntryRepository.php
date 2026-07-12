<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Asset;
use App\Entity\AssetEntry;
use App\Entity\Space;
use App\Enum\AssetEntryKindEnum;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AssetEntry>
 */
class AssetEntryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AssetEntry::class);
    }

    /**
     * @return AssetEntry[] entries for the asset, most recent first
     */
    public function findByAsset(Asset $asset): array
    {
        return $this->createQueryBuilder('e')
            ->where('e.asset = :asset')
            ->setParameter('asset', $asset)
            ->orderBy('e.date', 'DESC')
            ->addOrderBy('e.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return AssetEntry[] buy entries for the asset, ordered by date (FIFO)
     */
    public function findBuysByAsset(Asset $asset): array
    {
        return $this->createQueryBuilder('e')
            ->where('e.asset = :asset')
            ->andWhere('e.kind = :kind')
            ->setParameter('asset', $asset)
            ->setParameter('kind', AssetEntryKindEnum::BUY)
            ->orderBy('e.date', 'ASC')
            ->addOrderBy('e.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** Sum of quantities for entries that affect quantity (buy + sell) */
    public function getTotalQuantity(Asset $asset): string
    {
        $result = $this->createQueryBuilder('e')
            ->select('SUM(e.quantity * CASE WHEN e.kind = :buy THEN 1 WHEN e.kind = :sell THEN -1 ELSE 0 END)')
            ->where('e.asset = :asset')
            ->setParameter('asset', $asset)
            ->setParameter('buy', AssetEntryKindEnum::BUY)
            ->setParameter('sell', AssetEntryKindEnum::SELL)
            ->getQuery()
            ->getSingleScalarResult();

        return $result ?? '0';
    }

    /** Sum of dividends received for the asset */
    public function getTotalDividends(Asset $asset): string
    {
        $result = $this->createQueryBuilder('e')
            ->select('SUM(e.quantity * e.unitPrice * e.fxRate)')
            ->where('e.asset = :asset')
            ->andWhere('e.kind = :kind')
            ->setParameter('asset', $asset)
            ->setParameter('kind', AssetEntryKindEnum::DIVIDEND)
            ->getQuery()
            ->getSingleScalarResult();

        return $result ?? '0';
    }

    /** Sum of fees paid across all entries for the asset */
    public function getTotalFees(Asset $asset): string
    {
        $result = $this->createQueryBuilder('e')
            ->select('SUM(e.fees)')
            ->where('e.asset = :asset')
            ->setParameter('asset', $asset)
            ->getQuery()
            ->getSingleScalarResult();

        return $result ?? '0';
    }

    /** Total cost basis in asset currency */
    public function getTotalCost(Asset $asset): string
    {
        $result = $this->createQueryBuilder('e')
            ->select('SUM(e.quantity * e.unitPrice)')
            ->where('e.asset = :asset')
            ->andWhere('e.kind = :kind')
            ->setParameter('asset', $asset)
            ->setParameter('kind', AssetEntryKindEnum::BUY)
            ->getQuery()
            ->getSingleScalarResult();

        return $result ?? '0';
    }

    /** Total cost basis in space currency (with historical FX) */
    public function getTotalCostInSpaceCurrency(Asset $asset): string
    {
        $result = $this->createQueryBuilder('e')
            ->select('SUM(e.quantity * e.unitPrice * e.fxRate)')
            ->where('e.asset = :asset')
            ->andWhere('e.kind = :kind')
            ->setParameter('asset', $asset)
            ->setParameter('kind', AssetEntryKindEnum::BUY)
            ->getQuery()
            ->getSingleScalarResult();

        return $result ?? '0';
    }

    /** Weighted average purchase price in asset currency */
    public function getAveragePrice(Asset $asset): ?string
    {
        $result = $this->createQueryBuilder('e')
            ->select('SUM(e.quantity * e.unitPrice) AS totalCost', 'SUM(e.quantity) AS totalQty')
            ->where('e.asset = :asset')
            ->andWhere('e.kind = :kind')
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

    /** Weighted average purchase price in space currency (with historical FX) */
    public function getAveragePriceInSpaceCurrency(Asset $asset): ?string
    {
        $result = $this->createQueryBuilder('e')
            ->select('SUM(e.quantity * e.unitPrice * e.fxRate) AS totalCost', 'SUM(e.quantity) AS totalQty')
            ->where('e.asset = :asset')
            ->andWhere('e.kind = :kind')
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

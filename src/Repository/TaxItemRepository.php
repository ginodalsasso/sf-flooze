<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\TaxItem;
use App\Entity\TaxYear;
use App\Enum\FiscalBucketEnum;
use App\Enum\FiscalRoleEnum;
use App\Repository\Contract\TaxItemRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TaxItem>
 */
final class TaxItemRepository extends ServiceEntityRepository implements TaxItemRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TaxItem::class);
    }

    public function findOne(TaxYear $taxYear, FiscalBucketEnum $bucket, FiscalRoleEnum $role): ?TaxItem
    {
        return $this->createQueryBuilder('ti')
            ->where('ti.taxYear = :taxYear')
            ->andWhere('ti.fiscalBucket = :bucket')
            ->andWhere('ti.fiscalRole = :role')
            ->setParameter('taxYear', $taxYear)
            ->setParameter('bucket', $bucket)
            ->setParameter('role', $role)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findByTaxYear(TaxYear $taxYear): array
    {
        return $this->createQueryBuilder('ti')
            ->where('ti.taxYear = :taxYear')
            ->setParameter('taxYear', $taxYear)
            ->getQuery()
            ->getResult();
    }
}

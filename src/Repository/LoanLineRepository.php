<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\LoanLine;
use App\Repository\Contract\LoanLineRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<LoanLine>
 */
final class LoanLineRepository extends ServiceEntityRepository implements LoanLineRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LoanLine::class);
    }
}

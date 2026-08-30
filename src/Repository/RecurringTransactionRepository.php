<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\RecurringTransaction;
use App\Entity\Space;
use App\Repository\Contract\RecurringTransactionRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<RecurringTransaction>
 */
final class RecurringTransactionRepository extends ServiceEntityRepository implements RecurringTransactionRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RecurringTransaction::class);
    }

    /** @return RecurringTransaction[] not deleted, ordered by label */
    public function findBySpace(Space $space): array
    {
        return $this->scopedQb($space)
            ->orderBy('rt.label', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * $date is a parameter, never a now(): a repository runs queries, the clock belongs
     * to the calling service.
     *
     * @return RecurringTransaction[] active ones, oldest cursors first
     */
    public function findDue(Space $space, \DateTimeImmutable $date): array
    {
        return $this->scopedQb($space)
            ->andWhere('rt.isActive = :active')
            ->andWhere('rt.nextOccurrenceDate <= :date')
            ->setParameter('active', true)
            ->setParameter('date', $date)
            ->orderBy('rt.nextOccurrenceDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** Shared base: space + not deleted. No ordering here, each finder sets its own. */
    private function scopedQb(Space $space): QueryBuilder
    {
        return $this->createQueryBuilder('rt')
            ->where('rt.space = :space')
            ->andWhere('rt.deletedAt IS NULL')
            ->setParameter('space', $space);
    }
}

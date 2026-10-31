<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Lease;
use App\Entity\RentPayment;
use App\Entity\Space;
use App\Repository\Contract\RentPaymentRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<RentPayment>
 */
final class RentPaymentRepository extends ServiceEntityRepository implements RentPaymentRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RentPayment::class);
    }

    /** @return RentPayment[] rent payments of the space, most recent due date first */
    public function findBySpace(Space $space): array
    {
        return $this->createQueryBuilder('rp')
            ->where('rp.space = :space')
            ->setParameter('space', $space)
            ->orderBy('rp.dueDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /** @return RentPayment[] rent payments of the lease, most recent due date first */
    public function findByLease(Lease $lease): array
    {
        return $this->createQueryBuilder('rp')
            ->where('rp.lease = :lease')
            ->andWhere('rp.space = :space')
            ->setParameter('lease', $lease)
            ->setParameter('space', $lease->getSpace())
            ->orderBy('rp.dueDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /** The rent payment of the lease for this due date, if one was already recorded. */
    public function findByLeaseAndDueDate(Lease $lease, \DateTimeImmutable $dueDate): ?RentPayment
    {
        return $this->createQueryBuilder('rp')
            ->where('rp.lease = :lease')
            ->andWhere('rp.space = :space')
            ->andWhere('rp.dueDate = :dueDate')
            ->setParameter('lease', $lease)
            ->setParameter('space', $lease->getSpace())
            ->setParameter('dueDate', $dueDate)
            ->getQuery()
            ->getOneOrNullResult();
    }
}

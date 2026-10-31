<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Loan;
use App\Entity\LoanPayment;
use App\Entity\Space;
use App\Repository\Contract\LoanPaymentRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<LoanPayment>
 */
final class LoanPaymentRepository extends ServiceEntityRepository implements LoanPaymentRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LoanPayment::class);
    }

    /** @return LoanPayment[] loan payments of the space, most recent due date first */
    public function findBySpace(Space $space): array
    {
        return $this->createQueryBuilder('lp')
            ->where('lp.space = :space')
            ->setParameter('space', $space)
            ->orderBy('lp.dueDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /** @return LoanPayment[] loan payments of the loan, in instalment order */
    public function findByLoan(Loan $loan): array
    {
        return $this->createQueryBuilder('lp')
            ->where('lp.loan = :loan')
            ->andWhere('lp.space = :space')
            ->setParameter('loan', $loan)
            ->setParameter('space', $loan->getSpace())
            ->orderBy('lp.monthNumber', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** The highest instalment number already recorded for the loan, if any. */
    public function findMaxMonthNumber(Loan $loan): ?int
    {
        $max = $this->createQueryBuilder('lp')
            ->select('MAX(lp.monthNumber)')
            ->where('lp.loan = :loan')
            ->andWhere('lp.space = :space')
            ->setParameter('loan', $loan)
            ->setParameter('space', $loan->getSpace())
            ->getQuery()
            ->getSingleScalarResult();

        return null === $max ? null : (int) $max;
    }

    /** The loan payment recorded for this instalment number, if one already exists. */
    public function findByLoanAndMonth(Loan $loan, int $monthNumber): ?LoanPayment
    {
        return $this->createQueryBuilder('lp')
            ->where('lp.loan = :loan')
            ->andWhere('lp.space = :space')
            ->andWhere('lp.monthNumber = :monthNumber')
            ->setParameter('loan', $loan)
            ->setParameter('space', $loan->getSpace())
            ->setParameter('monthNumber', $monthNumber)
            ->getQuery()
            ->getOneOrNullResult();
    }
}

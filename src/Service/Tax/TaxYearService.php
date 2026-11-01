<?php

declare(strict_types=1);

namespace App\Service\Tax;

use App\Entity\Space;
use App\Entity\TaxYear;
use App\Enum\FiscalBucketEnum;
use App\Enum\TaxYearStatusEnum;
use App\Repository\Contract\TaxYearRepositoryInterface;
use App\Service\Tax\Contract\TaxYearServiceInterface;
use Doctrine\ORM\EntityManagerInterface;

final class TaxYearService implements TaxYearServiceInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly TaxYearRepositoryInterface $taxYearRepository,
    ) {}

    public function getOrCreate(Space $space, int $year): TaxYear
    {
        $taxYear = $this->taxYearRepository->findBySpaceAndYear($space, $year);

        if ($taxYear !== null) {
            return $taxYear;
        }

        $taxYear = (new TaxYear())->setSpace($space)->setYear($year);
        $this->em->persist($taxYear);
        $this->em->flush();

        return $taxYear;
    }

    public function transition(TaxYear $taxYear, TaxYearStatusEnum $to): void
    {
        $order = [TaxYearStatusEnum::DRAFT, TaxYearStatusEnum::FILED, TaxYearStatusEnum::PAID];
        $from = array_search($taxYear->getStatus(), $order, true);
        $toIndex = array_search($to, $order, true);

        if (abs($from - $toIndex) !== 1) {
            throw new \InvalidArgumentException('Cette transition de statut n\'est pas autorisée.');
        }

        $taxYear->setStatus($to);
        $this->em->flush();
    }

    public function setRegimeChoice(TaxYear $taxYear, FiscalBucketEnum $bucket, ?string $regime): void
    {
        if ($regime !== null && !in_array($regime, ['reel', 'micro'], true)) {
            throw new \InvalidArgumentException('Valeur de régime invalide.');
        }

        if ($regime === 'micro' && !$bucket->allowsMicro()) {
            throw new \InvalidArgumentException('Cette catégorie n\'admet pas de régime micro.');
        }

        $choices = $taxYear->getRegimeChoices() ?? [];

        if ($regime === null) {
            unset($choices[$bucket->value]);
        } else {
            $choices[$bucket->value] = $regime;
        }

        $taxYear->setRegimeChoices($choices === [] ? null : $choices);
        $this->em->flush();
    }

    public function setNote(TaxYear $taxYear, ?string $note): void
    {
        $taxYear->setNote($note === '' ? null : $note);
        $this->em->flush();
    }
}

<?php

declare(strict_types=1);

namespace App\Service\Tax;

use App\Entity\TaxItem;
use App\Entity\TaxYear;
use App\Enum\FiscalBucketEnum;
use App\Enum\FiscalRoleEnum;
use App\Repository\Contract\TaxItemRepositoryInterface;
use App\Service\Tax\Contract\TaxItemServiceInterface;
use Doctrine\ORM\EntityManagerInterface;

final class TaxItemService implements TaxItemServiceInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly TaxItemRepositoryInterface $taxItemRepository,
    ) {}

    public function toggle(TaxYear $taxYear, FiscalBucketEnum $bucket, FiscalRoleEnum $role): TaxItem
    {
        if ($role === FiscalRoleEnum::CHARGE && !$bucket->acceptsCharges()) {
            throw new \InvalidArgumentException('Cette catégorie n\'admet pas de charges déductibles.');
        }

        $item = $this->taxItemRepository->findOne($taxYear, $bucket, $role);

        if ($item === null) {
            $item = (new TaxItem())
                ->setSpace($taxYear->getSpace())
                ->setTaxYear($taxYear)
                ->setFiscalBucket($bucket)
                ->setFiscalRole($role)
                ->setDone(true);
            $this->em->persist($item);
        } else {
            $item->setDone(!$item->isDone());
        }

        $this->em->flush();

        return $item;
    }
}

<?php

declare(strict_types=1);

namespace App\Form\Finance;

use App\Entity\Asset;
use App\Entity\Space;
use App\Repository\Contract\AssetEntryRepositoryInterface;
use Symfony\Component\Form\FormBuilderInterface;

class AssetSellFormType extends AbstractAssetOperationFormType
{
    public function __construct(
        private readonly AssetEntryRepositoryInterface $entryRepository,
    ) {}

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var Space $space */
        $space = $options['space'];
        /** @var Asset $asset */
        $asset = $options['asset'];

        $this->addSharedFields($builder, $space, $asset);
        $this->addTradeFields($builder, maxQuantity: $this->entryRepository->getTotalQuantity($asset));
    }
}

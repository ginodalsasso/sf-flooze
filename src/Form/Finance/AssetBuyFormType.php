<?php

declare(strict_types=1);

namespace App\Form\Finance;

use App\Entity\Asset;
use App\Entity\Space;
use Symfony\Component\Form\FormBuilderInterface;

class AssetBuyFormType extends AbstractAssetOperationFormType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var Space $space */
        $space = $options['space'];
        /** @var Asset $asset */
        $asset = $options['asset'];

        $this->addSharedFields($builder, $space, $asset, fundingPlaceholder: 'Choisir un compte de paiement');
        $this->addTradeFields($builder);
    }
}

<?php

declare(strict_types=1);

namespace App\Form\Finance;

use App\Entity\Asset;
use App\Entity\Space;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints as Assert;

class AssetDividendFormType extends AbstractAssetOperationFormType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var Space $space */
        $space = $options['space'];
        /** @var Asset $asset */
        $asset = $options['asset'];

        $this->addSharedFields($builder, $space, $asset);

        $builder->add('amount', NumberType::class, [
            'scale' => 2,
            'html5' => false,
            'attr' => ['placeholder' => '0,00'],
            'constraints' => [
                new Assert\NotNull(message: 'Le montant ne peut pas être vide.'),
                new Assert\GreaterThan(value: 0, message: 'Le montant doit être supérieur à 0.'),
            ],
        ]);
    }
}

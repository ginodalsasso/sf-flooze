<?php

declare(strict_types=1);

namespace App\Form\Finance;

use App\Entity\Asset;
use App\Entity\Space;
use App\Repository\AssetEntryRepository;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints as Assert;

class AssetSellFormType extends AbstractAssetOperationFormType
{
    public function __construct(
        private readonly AssetEntryRepository $entryRepository,
    ) {}

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var Space $space */
        $space = $options['space'];
        /** @var Asset $asset */
        $asset = $options['asset'];

        $this->addSharedFields($builder, $space, $asset);

        $builder
            ->add('quantity', NumberType::class, [
                'scale' => 8,
                'html5' => false,
                'attr' => [
                    'placeholder' => '0,00000000',
                    'max' => (float) $this->entryRepository->getTotalQuantity($asset),
                ],
                'constraints' => [
                    new Assert\NotNull(message: 'La quantité ne peut pas être vide.'),
                    new Assert\GreaterThan(value: 0, message: 'La quantité doit être supérieure à 0.'),
                ],
            ])
            ->add('unitPrice', NumberType::class, [
                'scale' => 4,
                'html5' => false,
                'attr' => ['placeholder' => '0,0000'],
                'constraints' => [
                    new Assert\NotNull(message: 'Le prix unitaire ne peut pas être vide.'),
                    new Assert\GreaterThan(value: 0, message: 'Le prix doit être supérieur à 0.'),
                ],
            ]);
    }
}

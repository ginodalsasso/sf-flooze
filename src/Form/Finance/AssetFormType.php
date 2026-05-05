<?php

declare(strict_types=1);

namespace App\Form\Finance;

use App\Entity\Asset;
use App\Enum\AssetTypeEnum;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class AssetFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('ticker', TextType::class, [
                'attr' => ['placeholder' => 'Ex. : AAPL, BTC, MSSP.PA…'],
                'constraints' => [
                    new Assert\NotBlank(message: 'Le ticker ne peut pas être vide.'),
                    new Assert\Length(max: 20, maxMessage: 'Maximum {{ limit }} caractères.'),
                ],
            ])
            ->add('name', TextType::class, [
                'attr' => ['placeholder' => 'Ex. : Apple Inc., Bitcoin…'],
                'constraints' => [
                    new Assert\NotBlank(message: 'Le nom ne peut pas être vide.'),
                    new Assert\Length(max: 100, maxMessage: 'Maximum {{ limit }} caractères.'),
                ],
            ])
            ->add('type', EnumType::class, [
                'class' => AssetTypeEnum::class,
                'choice_label' => fn(AssetTypeEnum $t) => $t->label(),
            ])
            ->add('quantity', NumberType::class, [
                'scale' => 8,
                'html5' => false,
                'attr' => ['placeholder' => '0,00000000'],
                'constraints' => [
                    new Assert\NotNull(message: 'La quantité ne peut pas être vide.'),
                    new Assert\GreaterThan(value: 0, message: 'La quantité doit être supérieure à 0.'),
                ],
            ])
            ->add('avgPrice', NumberType::class, [
                'scale' => 4,
                'html5' => false,
                'attr' => ['placeholder' => '0,0000'],
                'constraints' => [
                    new Assert\NotNull(message: 'Le prix moyen ne peut pas être vide.'),
                    new Assert\GreaterThanOrEqual(value: 0, message: 'Le prix ne peut pas être négatif.'),
                ],
            ])
            ->add('currency', ChoiceType::class, [
                'choices' => ['EUR €' => 'EUR', 'USD $' => 'USD', 'GBP £' => 'GBP', 'CHF' => 'CHF'],
                'constraints' => [new Assert\NotBlank()],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Asset::class]);
    }
}

<?php

declare(strict_types=1);

namespace App\Form\RealEstate;

use App\Entity\Property;
use App\Enum\PropertyTypeEnum;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class PropertyFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'attr' => ['placeholder' => 'Ex. : T2 Rue Pasteur, Maison Bordeaux…'],
                'constraints' => [
                    new Assert\NotBlank(message: 'Le nom ne peut pas être vide.'),
                    new Assert\Length(max: 100, maxMessage: 'Maximum {{ limit }} caractères.'),
                ],
            ])
            ->add('address', TextType::class, [
                'attr' => ['placeholder' => '12 rue Pasteur, 33000 Bordeaux'],
                'constraints' => [
                    new Assert\NotBlank(message: 'L\'adresse ne peut pas être vide.'),
                    new Assert\Length(max: 255, maxMessage: 'Maximum {{ limit }} caractères.'),
                ],
            ])
            ->add('type', EnumType::class, [
                'class' => PropertyTypeEnum::class,
                'choice_label' => fn(PropertyTypeEnum $type) => $type->label(),
                'help' => 'Une résidence principale ne peut pas porter de bail.',
            ])
            ->add('purchasePrice', NumberType::class, [
                'scale' => 2,
                'html5' => false,
                'attr' => ['placeholder' => '0,00'],
                'help' => 'Prix d\'acquisition, hors frais de notaire.',
                'constraints' => [
                    new Assert\NotNull(message: 'Le prix d\'achat est obligatoire.'),
                    new Assert\PositiveOrZero(message: 'Le prix d\'achat ne peut pas être négatif.'),
                ],
            ])
            ->add('purchaseDate', DateType::class, [
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'constraints' => [new Assert\NotNull(message: 'La date d\'achat est obligatoire.')],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Property::class]);
    }
}

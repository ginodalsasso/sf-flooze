<?php

declare(strict_types=1);

namespace App\Form\RealEstate;

use App\Entity\Tenant;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class TenantFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('firstName', TextType::class, [
                'constraints' => [
                    new Assert\NotBlank(message: 'Le prénom ne peut pas être vide.'),
                    new Assert\Length(max: 100, maxMessage: 'Maximum {{ limit }} caractères.'),
                ],
            ])
            ->add('lastName', TextType::class, [
                'constraints' => [
                    new Assert\NotBlank(message: 'Le nom ne peut pas être vide.'),
                    new Assert\Length(max: 100, maxMessage: 'Maximum {{ limit }} caractères.'),
                ],
            ])
            ->add('email', EmailType::class, [
                'constraints' => [
                    new Assert\NotBlank(message: 'L\'email ne peut pas être vide.'),
                    new Assert\Email(message: 'Cet email n\'est pas valide.'),
                    new Assert\Length(max: 180, maxMessage: 'Maximum {{ limit }} caractères.'),
                ],
            ])
            ->add('phone', TextType::class, [
                'required' => false,
                'attr' => ['placeholder' => '06 12 34 56 78'],
                'constraints' => [new Assert\Length(max: 30, maxMessage: 'Maximum {{ limit }} caractères.')],
            ])
            ->add('monthlyIncome', NumberType::class, [
                'scale' => 2,
                'html5' => false,
                'attr' => ['placeholder' => '0,00'],
                'help' => 'Revenu net mensuel déclaré, pièces justificatives à l\'appui.',
                'constraints' => [
                    new Assert\NotNull(message: 'Le revenu mensuel est obligatoire.'),
                    new Assert\PositiveOrZero(message: 'Le revenu mensuel ne peut pas être négatif.'),
                ],
            ])
            ->add('guarantorName', TextType::class, [
                'required' => false,
                'attr' => ['placeholder' => 'Nom du garant, le cas échéant'],
                'constraints' => [new Assert\Length(max: 200, maxMessage: 'Maximum {{ limit }} caractères.')],
            ])
            ->add('guarantorIncome', NumberType::class, [
                'scale' => 2,
                'html5' => false,
                'required' => false,
                'attr' => ['placeholder' => '0,00'],
                'constraints' => [
                    new Assert\PositiveOrZero(message: 'Le revenu du garant ne peut pas être négatif.'),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Tenant::class]);
    }
}

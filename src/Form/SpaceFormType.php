<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Space;
use App\Enum\SpaceTypeEnum;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class SpaceFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // Only allow changing the type when creating a new space, not when editing
        if (!$options['is_edit']) {
            $builder->add('type', EnumType::class, [
                'class' => SpaceTypeEnum::class,
                'expanded' => true,
                'multiple' => false,
            ]);
        }

        $builder->add('name', TextType::class, [
                'attr' => [
                    'placeholder' => 'Ex. : Mes finances, Auto-entrepreneur…',
                    'autocomplete' => 'off',
                ],
                'constraints' => [
                    new Assert\NotBlank(message: 'Le nom ne peut pas être vide.'),
                    new Assert\Length(
                        min: 2,
                        max: 100,
                        minMessage: 'Minimum {{ limit }} caractères.',
                        maxMessage: 'Maximum {{ limit }} caractères.',
                    ),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Space::class,
            'is_edit' => false,
        ]);
    }
}

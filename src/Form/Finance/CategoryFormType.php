<?php

declare(strict_types=1);

namespace App\Form\Finance;

use App\Entity\Category;
use App\Entity\Space;
use App\Repository\CategoryRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class CategoryFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var Space $space */
        $space = $options['space'];

        /** @var Category|null $edited */
        $edited = $options['data'] ?? null;

        $builder
            ->add('name', TextType::class, [
                'attr' => ['placeholder' => 'Ex. : Alimentation, Loyer, Salaire…'],
                'constraints' => [
                    new Assert\NotBlank(message: 'Le nom ne peut pas être vide.'),
                    new Assert\Length(max: 100, maxMessage: 'Maximum {{ limit }} caractères.'),
                ],
            ])
            ->add('parent', EntityType::class, [
                'class' => Category::class,
                'required' => false,
                'placeholder' => 'Aucune (catégorie racine)',
                'query_builder' => function (CategoryRepository $repo) use ($space, $edited) {
                    $qb = $repo->createSpaceScopedQb($space);
                    // Exclude the current category from the parent selection
                    if ($edited?->getId() !== null) {
                        $qb->andWhere('c.id != :self')->setParameter('self', $edited->getId());
                    }

                    return $qb;
                },
                'choice_label' => 'name',
            ])
            ->add('isDeductible', CheckboxType::class, ['required' => false])
            ->add('isDeclarable', CheckboxType::class, ['required' => false]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Category::class]);
        $resolver->setRequired('space'); // form options require a 'space' option to be passed
        $resolver->setAllowedTypes('space', Space::class); // ensure 'space' option is of type Space
    }
}

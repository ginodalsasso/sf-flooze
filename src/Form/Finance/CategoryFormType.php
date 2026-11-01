<?php

declare(strict_types=1);

namespace App\Form\Finance;

use App\Entity\Category;
use App\Entity\Space;
use App\Enum\FiscalBucketEnum;
use App\Enum\FiscalRoleEnum;
use App\Enum\TransactionTypeEnum;
use App\Repository\CategoryRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

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
            ->add('applicableTypes', EnumType::class, [
                'class' => TransactionTypeEnum::class,
                'multiple' => true,
                'expanded' => true,
                'choice_label' => fn(TransactionTypeEnum $type) => $type->label(),
                'constraints' => [
                    new Assert\Count(min: 1, minMessage: 'Sélectionne au moins un type de transaction.'),
                ],
            ])
            // Les deux ensemble, ou aucun : un rôle sans catégorie fiscale ne se déclare pas.
            ->add('fiscalBucket', EnumType::class, [
                'class' => FiscalBucketEnum::class,
                'required' => false,
                'placeholder' => 'Aucune portée fiscale',
                'choice_label' => fn(FiscalBucketEnum $bucket) => $bucket->label(),
                'help' => 'Une charge ne se déduit que des revenus de sa propre catégorie fiscale.',
            ])
            ->add('fiscalRole', EnumType::class, [
                'class' => FiscalRoleEnum::class,
                'required' => false,
                'placeholder' => 'Sans objet',
                'choice_label' => fn(FiscalRoleEnum $role) => $role->label(),
            ]);
    }

    /** Un rôle sans catégorie fiscale, ou l'inverse, ne serait compté nulle part. */
    public function validateFiscalScope(?Category $category, ExecutionContextInterface $context): void
    {
        if ($category === null) {
            return;
        }

        if ($category->getFiscalBucket() !== null && $category->getFiscalRole() === null) {
            $context->buildViolation('Précise si cette catégorie apporte un revenu ou une charge.')
                ->atPath('fiscalRole')
                ->addViolation();
        }

        if ($category->getFiscalRole() !== null && $category->getFiscalBucket() === null) {
            $context->buildViolation('Précise la catégorie fiscale de rattachement.')
                ->atPath('fiscalBucket')
                ->addViolation();
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Category::class,
            'constraints' => [new Assert\Callback([$this, 'validateFiscalScope'])],
        ]);
        $resolver->setRequired('space'); // form options require a 'space' option to be passed
        $resolver->setAllowedTypes('space', Space::class); // ensure 'space' option is of type Space
    }
}

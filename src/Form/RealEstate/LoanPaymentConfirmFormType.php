<?php

declare(strict_types=1);

namespace App\Form\RealEstate;

use App\Entity\Account;
use App\Entity\Category;
use App\Entity\Space;
use App\Enum\AccountTypeEnum;
use App\Repository\AccountRepository;
use App\Repository\CategoryRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Not bound to an entity: the controller reads account/category off the form
 * data and passes them to LoanPaymentServiceInterface::confirm(). No date
 * field: the service resolves the due month itself via nextDueRow().
 */
class LoanPaymentConfirmFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var Space $space */
        $space = $options['space'];

        $builder
            ->add('account', EntityType::class, [
                'class' => Account::class,
                'choice_label' => fn (Account $a) => $a->getName() . ' (' . $a->getCurrency()->display() . ')',
                'query_builder' => fn (AccountRepository $repo) => $repo->createQueryBuilder('a')
                    ->where('a.space = :space')
                    ->andWhere('a.deletedAt IS NULL')
                    ->andWhere('a.type NOT IN (:assetTypes)')
                    ->setParameter('space', $space)
                    ->setParameter('assetTypes', [AccountTypeEnum::CRYPTO, AccountTypeEnum::STOCK])
                    ->orderBy('a.name', 'ASC'),
                'placeholder' => 'Choisir un compte…',
                'constraints' => [new Assert\NotNull(message: 'Le compte est obligatoire.')],
            ])
            ->add('category', EntityType::class, [
                'class' => Category::class,
                'required' => false,
                'label' => 'Catégorie',
                'placeholder' => 'Sans catégorie',
                'query_builder' => fn (CategoryRepository $repo) => $repo->createSpaceScopedQb($space),
                'choice_label' => 'name',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setRequired('space');
        $resolver->setAllowedTypes('space', Space::class);
    }
}

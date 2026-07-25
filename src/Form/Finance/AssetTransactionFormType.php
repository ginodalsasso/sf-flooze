<?php

declare(strict_types=1);

namespace App\Form\Finance;

use App\Entity\Account;
use App\Entity\Category;
use App\Entity\Space;
use App\Enum\AccountTypeEnum;
use App\Enum\TransactionTypeEnum;
use App\Repository\AccountRepository;
use App\Repository\CategoryRepository;
use App\Service\Finance\AccountBalanceService;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Form for transactions on asset-holding accounts (crypto, stock).
 */
class AssetTransactionFormType extends AbstractTransactionFormType
{
    public function __construct(
        private readonly AccountBalanceService $accountBalanceService,
    ) {}

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var Space $space */
        $space = $options['space'];

        $builder
            ->add('type', EnumType::class, [
                'class' => TransactionTypeEnum::class,
                'choices' => [TransactionTypeEnum::EXPENSE, TransactionTypeEnum::TRANSFER],
                'choice_label' => fn(TransactionTypeEnum $t) => $t->assetLabel(),
                'label' => 'Type d\'opération',
                'data' => TransactionTypeEnum::EXPENSE,
            ])
            ->add('account', EntityType::class, [
                'class' => Account::class,
                'choice_label' => fn(Account $a) => $a->getName() . ' (' . $a->getCurrency()->display() . ')',
                'query_builder' => fn(AccountRepository $repo) => $repo->createQueryBuilder('a')
                    ->where('a.space = :space')
                    ->andWhere('a.deletedAt IS NULL')
                    ->andWhere('a.type IN (:assetTypes)')
                    ->setParameter('space', $space)
                    ->setParameter('assetTypes', [AccountTypeEnum::CRYPTO, AccountTypeEnum::STOCK])
                    ->orderBy('a.name', 'ASC'),
                'placeholder' => 'Choisir un compte crypto/actif…',
                'constraints' => [new Assert\NotNull(message: 'Le compte est obligatoire.')],
                'label' => 'Compte crypto/actif',
                'choice_attr' => fn(Account $a) => [
                    'data-available-balance' => $this->accountBalanceService->getAvailableBalance($a),
                    'data-currency' => $a->getCurrency()->value,
                ],
            ])
            ->add('amount', NumberType::class, [
                'scale' => 2,
                'html5' => false,
                'attr' => ['placeholder' => '0,00'],
                'constraints' => [
                    new Assert\NotNull(message: 'Le montant est obligatoire.'),
                    new Assert\GreaterThan(value: 0, message: 'Le montant doit être supérieur à 0.'),
                ],
            ])
            ->add('date', DateType::class, [
                'widget' => 'single_text',
                'input'  => 'datetime_immutable',
                'constraints' => [new Assert\NotNull(message: 'La date est obligatoire.')],
            ])
            ->add('description', TextType::class, [
                'required' => false,
                'attr' => ['placeholder' => 'Ex. : Dépôt, retrait…'],
                'constraints' => [
                    new Assert\Length(max: 255, maxMessage: 'Maximum {{ limit }} caractères.'),
                ],
            ])
            ->add('category', EntityType::class, [
                'class' => Category::class,
                'required' => false,
                'placeholder' => 'Sans catégorie',
                'query_builder' => fn(CategoryRepository $repo) => $repo->createSpaceScopedQb($space),
                'choice_label' => 'name',
            ])
            ->add('destinationAccount', EntityType::class, [
                'class' => Account::class,
                'required' => false,
                'placeholder' => 'Choisir un compte destinataire…',
                'label' => 'Compte destinataire (virements uniquement)',
                'query_builder' => fn(AccountRepository $repo) => $repo->createQueryBuilder('a')
                    ->where('a.space = :space')
                    ->andWhere('a.deletedAt IS NULL')
                    ->setParameter('space', $space)
                    ->orderBy('a.name', 'ASC'),
                'choice_label' => fn(Account $a) => $a->getName() . ' (' . $a->getCurrency()->display() . ')',
            ]);
    }

}

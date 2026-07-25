<?php

declare(strict_types=1);

namespace App\Form\Finance;

use App\Entity\Account;
use App\Entity\Space;
use App\Enum\AccountTypeEnum;
use App\Enum\TransactionTypeEnum;
use App\Repository\AccountRepository;
use App\Service\Finance\AccountBalanceService;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints as Assert;

class AssetTransactionFormType extends AbstractTransactionFormType
{
    public function __construct(
        private readonly AccountBalanceService $accountBalanceService,
    ) {}

    protected function descriptionPlaceholder(): string
    {
        return 'Ex. : Dépôt, retrait…';
    }

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
            ]);

        $this->addSharedFields($builder, $space);
    }
}

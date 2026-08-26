<?php

declare(strict_types=1);

namespace App\Form\Finance;

use App\Dto\Finance\AssetPriceDto;
use App\Entity\Account;
use App\Entity\Asset;
use App\Entity\Space;
use App\Repository\AccountRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

use function Symfony\Component\Clock\now;

abstract class AbstractAssetOperationFormType extends AbstractType
{
    /** A buy debits the funding account while a sell credits it: only the wording differs. */
    protected function addSharedFields(
        FormBuilderInterface $builder,
        Space $space,
        Asset $asset,
        string $fundingPlaceholder = 'Choisir un compte de destination',
    ): void {
        $requiredAccountType = $asset->getType()->requiredAccountType();

        $builder
            ->add('date', DateType::class, [
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'data' => now('today'),
                'constraints' => [new Assert\NotNull(message: 'La date est obligatoire.')],
            ])
            ->add('fxRate', NumberType::class, [
                'scale' => 6,
                'html5' => false,
                'attr' => ['placeholder' => '1,000000'],
                'data' => '1',
                'constraints' => [
                    new Assert\NotNull(message: 'Le taux de change ne peut pas être vide.'),
                    new Assert\GreaterThan(value: 0, message: 'Le taux doit être supérieur à 0.'),
                ],
            ])
            ->add('fees', NumberType::class, [
                'scale' => 2,
                'html5' => false,
                'attr' => ['placeholder' => '0,00'],
                'data' => '0',
                'constraints' => [
                    new Assert\NotNull(message: 'Les frais ne peuvent pas être vides.'),
                    new Assert\GreaterThanOrEqual(value: 0, message: 'Les frais ne peuvent pas être négatifs.'),
                ],
            ])
            ->add('account', EntityType::class, [
                'class' => Account::class,
                'required' => true,
                'placeholder' => 'Choisir un compte de détention',
                'query_builder' => fn(AccountRepository $repo) => $repo->createQueryBuilder('a')
                    ->where('a.space = :space')
                    ->andWhere('a.type = :type')
                    ->andWhere('a.deletedAt IS NULL')
                    ->setParameter('space', $space)
                    ->setParameter('type', $requiredAccountType)
                    ->orderBy('a.name', 'ASC'),
                'choice_label' => fn(Account $a) => $a->getName() . ' (' . $a->getCurrency()->value . ')',
                'constraints' => [new Assert\NotNull(message: 'Un compte de détention est obligatoire.')],
            ])
            ->add('fundingAccount', EntityType::class, [
                'class' => Account::class,
                'required' => true,
                'placeholder' => $fundingPlaceholder,
                'query_builder' => fn(AccountRepository $repo) => $repo->createQueryBuilder('a')
                    ->where('a.space = :space')
                    ->andWhere('a.deletedAt IS NULL')
                    ->setParameter('space', $space)
                    ->orderBy('a.name', 'ASC'),
                'choice_label' => fn(Account $a) => $a->getName() . ' (' . $a->getCurrency()->value . ')',
                'constraints' => [new Assert\NotNull(message: 'Un compte de destination est obligatoire.')],
            ])
            ->add('note', TextType::class, [
                'required' => false,
                'attr' => ['placeholder' => 'Note optionnelle…'],
                'constraints' => [new Assert\Length(max: 255, maxMessage: 'Maximum {{ limit }} caractères.')],
            ]);
    }

    /**
     * Quantity and unit price of a trade. The price is prefilled only from a live market quote,
     * which is the price of the day; anything older is displayed by the caller and retyped.
     */
    protected function addTradeFields(FormBuilderInterface $builder, ?string $maxQuantity = null, ?string $suggestedUnitPrice = null): void
    {
        $quantityAttr = ['placeholder' => '0,00000000'];

        if ($maxQuantity !== null) {
            $quantityAttr['max'] = (float) $maxQuantity;
        }

        $builder
            ->add('quantity', NumberType::class, [
                'scale' => 8,
                'html5' => false,
                'attr' => $quantityAttr,
                'constraints' => [
                    new Assert\NotNull(message: 'La quantité ne peut pas être vide.'),
                    new Assert\GreaterThan(value: 0, message: 'La quantité doit être supérieure à 0.'),
                ],
            ])
            ->add('unitPrice', NumberType::class, [
                'scale' => 4,
                'html5' => false,
                'attr' => ['placeholder' => '0,0000'],
                'data' => $suggestedUnitPrice,
                'constraints' => [
                    new Assert\NotNull(message: 'Le prix unitaire ne peut pas être vide.'),
                    new Assert\GreaterThan(value: 0, message: 'Le prix doit être supérieur à 0.'),
                ],
            ]);
    }

    /** Only a live quote may be suggested: a stale price prefilled would get submitted unnoticed. */
    protected function suggestedUnitPrice(array $options): ?string
    {
        $price = $options['current_price'];

        return $price?->source->isLive() ? $price->unitPrice : null;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setRequired(['space', 'asset']);
        $resolver->setAllowedTypes('space', Space::class);
        $resolver->setAllowedTypes('asset', Asset::class);
        $resolver->setDefault('current_price', null);
        $resolver->setAllowedTypes('current_price', ['null', AssetPriceDto::class]);
    }
}

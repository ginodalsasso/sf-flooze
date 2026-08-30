<?php

declare(strict_types=1);

namespace App\Form\Finance;

use App\Entity\Account;
use App\Entity\Category;
use App\Entity\RecurringTransaction;
use App\Entity\Space;
use App\Entity\Tag;
use App\Enum\AccountTypeEnum;
use App\Enum\RecurrenceFrequencyEnum;
use App\Enum\TransactionTypeEnum;
use App\Repository\AccountRepository;
use App\Repository\CategoryRepository;
use App\Repository\TagRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Binds the entity directly, unlike transaction forms: nothing is derived at input time here,
 * fx_rate and destination_amount only exist once an occurrence is materialised.
 *
 * isActive and nextOccurrenceDate stay out of the form — the service drives them.
 */
class RecurringTransactionFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var Space $space */
        $space = $options['space'];

        $builder
            ->add('label', TextType::class, [
                'label' => 'Libellé',
                'attr' => ['placeholder' => 'Ex. : Netflix, Loyer, Salaire…'],
                'constraints' => [
                    new Assert\NotBlank(message: 'Le libellé ne peut pas être vide.'),
                    new Assert\Length(max: 255, maxMessage: 'Maximum {{ limit }} caractères.'),
                ],
            ])
            ->add('type', EnumType::class, [
                'class' => TransactionTypeEnum::class,
                'choice_label' => fn (TransactionTypeEnum $type) => $type->label(),
            ])
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
            ->add('destinationAccount', EntityType::class, [
                'class' => Account::class,
                'required' => false,
                'placeholder' => 'Aucun (non applicable)',
                'label' => 'Compte destinataire (virements uniquement)',
                'query_builder' => fn (AccountRepository $repo) => $repo->createQueryBuilder('a')
                    ->where('a.space = :space')
                    ->andWhere('a.deletedAt IS NULL')
                    ->andWhere('a.type NOT IN (:assetTypes)')
                    ->setParameter('space', $space)
                    ->setParameter('assetTypes', [AccountTypeEnum::CRYPTO, AccountTypeEnum::STOCK])
                    ->orderBy('a.name', 'ASC'),
                'choice_label' => fn (Account $a) => $a->getName() . ' (' . $a->getCurrency()->display() . ')',
            ])
            ->add('amount', NumberType::class, [
                'scale' => 2,
                'html5' => false,
                'label' => 'Montant',
                'attr' => ['placeholder' => '0,00'],
                'constraints' => [
                    new Assert\NotNull(message: 'Le montant est obligatoire.'),
                    new Assert\GreaterThan(value: 0, message: 'Le montant doit être supérieur à 0.'),
                ],
            ])
            ->add('category', EntityType::class, [
                'class' => Category::class,
                'required' => false,
                'label' => 'Catégorie',
                'placeholder' => 'Sans catégorie',
                'query_builder' => fn (CategoryRepository $repo) => $repo->createSpaceScopedQb($space),
                'choice_label' => 'name',
                'group_by' => $this->categoryGroup(...),
            ])
            ->add('tags', EntityType::class, [
                'class' => Tag::class,
                'required' => false,
                'multiple' => true,
                'query_builder' => fn (TagRepository $repo) => $repo->createSpaceScopedQb($space),
                'choice_label' => 'name',
            ])
            ->add('frequency', EnumType::class, [
                'class' => RecurrenceFrequencyEnum::class,
                'label' => 'Fréquence',
                'choice_label' => fn (RecurrenceFrequencyEnum $f) => $f->label(),
            ])
            ->add('intervalCount', IntegerType::class, [
                'label' => 'Répéter tous les',
                'help' => 'Laisser 1 pour la fréquence normale. 2 = une fois sur deux (quinzomadaire, bimestriel…).',
                'constraints' => [
                    new Assert\NotNull(message: 'L\'intervalle est obligatoire.'),
                    new Assert\Range(min: 1, max: 52, notInRangeMessage: 'L\'intervalle doit être compris entre {{ min }} et {{ max }}.'),
                ],
            ])
            ->add('startDate', DateType::class, [
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'label' => 'Première échéance',
                'help' => 'Ancre toute la série : un loyer au 31 retombera au 28 en février puis au 31 en mars.',
                'constraints' => [new Assert\NotNull(message: 'La date de première échéance est obligatoire.')],
            ])
            ->add('endDate', DateType::class, [
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'required' => false,
                'label' => 'Dernière échéance (optionnel)',
            ]);
    }

    /** Optgroup label: the types the category applies to, or "Tous types" if unrestricted. */
    private function categoryGroup(Category $category): string
    {
        $types = $category->getApplicableTypes();

        return $types === []
            ? 'Tous types'
            : implode(' · ', array_map(fn (TransactionTypeEnum $type) => $type->label(), $types));
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => RecurringTransaction::class]);
        $resolver->setRequired('space');
        $resolver->setAllowedTypes('space', Space::class);
    }
}

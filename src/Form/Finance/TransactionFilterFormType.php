<?php

declare(strict_types=1);

namespace App\Form\Finance;

use App\Dto\Finance\TransactionFilterDto;
use App\Entity\Account;
use App\Entity\Category;
use App\Entity\Space;
use App\Entity\Tag;
use App\Repository\AccountRepository;
use App\Repository\CategoryRepository;
use App\Repository\TagRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\SearchType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Search criteria of the transaction list.
 *
 * Submitted in GET with no block prefix so a filtered view is a plain, shareable
 * URL (?q=garage&date_from=2026-03-01) rather than an opaque payload.
 *
 * The type is deliberately absent: it is carried by the submit buttons of the
 * segmented control, which filter in one click instead of requiring a second one.
 */
class TransactionFilterFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var Space $space */
        $space = $options['space'];

        $builder
            ->add('q', SearchType::class, [
                'property_path' => 'query',
                'required' => false,
                'empty_data' => null,
                'attr' => ['placeholder' => 'Rechercher une description, une catégorie, un compte…'],
            ])
            ->add('category', EntityType::class, [
                'class' => Category::class,
                'required' => false,
                'placeholder' => 'Toutes les catégories',
                'query_builder' => fn(CategoryRepository $repo) => $repo->createSpaceScopedQb($space),
                'choice_label' => 'name',
            ])
            ->add('tag', EntityType::class, [
                'class' => Tag::class,
                'required' => false,
                'placeholder' => 'Tous les tags',
                'query_builder' => fn(TagRepository $repo) => $repo->createSpaceScopedQb($space),
                'choice_label' => 'name',
            ])
            ->add('date_from', DateType::class, [
                'property_path' => 'dateFrom',
                'required' => false,
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
            ])
            ->add('date_to', DateType::class, [
                'property_path' => 'dateTo',
                'required' => false,
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
            ])
            ->add('amount_min', NumberType::class, [
                'property_path' => 'amountMin',
                'required' => false,
                'input' => 'string',
                'scale' => 2,
                'html5' => false,
                'empty_data' => null,
                'attr' => ['placeholder' => '0,00'],
            ])
            ->add('amount_max', NumberType::class, [
                'property_path' => 'amountMax',
                'required' => false,
                'input' => 'string',
                'scale' => 2,
                'html5' => false,
                'empty_data' => null,
                'attr' => ['placeholder' => '0,00'],
            ]);

        // The account detail page is already scoped to one account: offering the field
        // there would let the form widen the list beyond its subject.
        if ($options['with_account']) {
            $builder->add('account', EntityType::class, [
                'class' => Account::class,
                'required' => false,
                'placeholder' => 'Tous les comptes',
                'query_builder' => fn(AccountRepository $repo) => $repo->createQueryBuilder('a')
                    ->where('a.space = :space')
                    ->andWhere('a.deletedAt IS NULL')
                    ->setParameter('space', $space)
                    ->orderBy('a.name', 'ASC'),
                'choice_label' => fn(Account $account) => $account->getName() . ' (' . $account->getCurrency()->display() . ')',
            ]);
        }
    }

    public function getBlockPrefix(): string
    {
        return '';
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => TransactionFilterDto::class,
            'method' => 'GET',
            'csrf_protection' => false,
            'required' => false,
            // An unprefixed GET form reads the whole query string: a stray param must not break the page.
            'allow_extra_fields' => true,
            'with_account' => true,
        ]);
        $resolver->setRequired('space');
        $resolver->setAllowedTypes('space', Space::class);
        $resolver->setAllowedTypes('with_account', 'bool');
    }
}

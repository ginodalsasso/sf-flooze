<?php

declare(strict_types=1);

namespace App\Form\Finance;

use App\Dto\Finance\TransactionInputDto;
use App\Entity\Account;
use App\Entity\Category;
use App\Entity\Space;
use App\Entity\Tag;
use App\Enum\TransactionTypeEnum;
use App\Repository\AccountRepository;
use App\Repository\CategoryRepository;
use App\Repository\TagRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

abstract class AbstractTransactionFormType extends AbstractType
{
    /** Placeholder shown on the description field; subclasses may override. */
    protected function descriptionPlaceholder(): string
    {
        return 'Ex. : Courses Lidl, Salaire mars…';
    }

    /** Adds amount/date/description/category/destinationAccount common to all transaction forms. */
    protected function addSharedFields(FormBuilderInterface $builder, Space $space): void
    {
        $builder
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
                'attr' => ['placeholder' => $this->descriptionPlaceholder()],
                'constraints' => [new Assert\Length(max: 255, maxMessage: 'Maximum {{ limit }} caractères.')],
            ])
            ->add('category', EntityType::class, [
                'class' => Category::class,
                'required' => false,
                'placeholder' => 'Sans catégorie',
                'query_builder' => fn(CategoryRepository $repo) => $repo->createSpaceScopedQb($space),
                'choice_label' => 'name',
                'group_by' => $this->categoryGroup(...),
            ])
            ->add('tags', EntityType::class, [
                'class' => Tag::class,
                'required' => false,
                'multiple' => true,
                'query_builder' => fn(TagRepository $repo) => $repo->createSpaceScopedQb($space),
                'choice_label' => 'name',
            ])
            ->add('destinationAccount', EntityType::class, [
                'class' => Account::class,
                'required' => false,
                'placeholder' => 'Aucun (non applicable)',
                'label' => 'Compte destinataire (virements uniquement)',
                'query_builder' => fn(AccountRepository $repo) => $repo->createQueryBuilder('a')
                    ->where('a.space = :space')
                    ->andWhere('a.deletedAt IS NULL')
                    ->setParameter('space', $space)
                    ->orderBy('a.name', 'ASC'),
                'choice_label' => fn(Account $a) => $a->getName() . ' (' . $a->getCurrency()->display() . ')',
            ])
            ->add('destinationAmount', NumberType::class, [
                'scale' => 2,
                'html5' => false,
                'required' => false,
                'label' => 'Montant reçu',
                'attr' => ['placeholder' => 'Calculé au taux du jour'],
                'constraints' => [
                    new Assert\GreaterThan(value: 0, message: 'Le montant reçu doit être supérieur à 0.'),
                ],
            ]);
    }

    /** Optgroup label: the types the category applies to, or "Tous types" if unrestricted. */
    private function categoryGroup(Category $category): string
    {
        $types = $category->getApplicableTypes();

        return $types === []
            ? 'Tous types'
            : implode(' · ', array_map(fn(TransactionTypeEnum $type) => $type->label(), $types));
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => TransactionInputDto::class,
            'constraints' => [
                new Assert\Callback([$this, 'validateTransferDestination']),
                new Assert\Callback([$this, 'validateCategoryType']),
            ],
        ]);
        $resolver->setRequired('space');
        $resolver->setAllowedTypes('space', Space::class);
    }

    public function validateTransferDestination(TransactionInputDto $input, ExecutionContextInterface $context): void
    {
        if ($input->type !== TransactionTypeEnum::TRANSFER) {
            return;
        }

        if ($input->destinationAccount === null) {
            $context->buildViolation('Le compte destinataire est obligatoire pour un virement.')
                ->atPath('destinationAccount')
                ->addViolation();

            return;
        }

        if ($input->destinationAccount->getId() === $input->account->getId()) {
            $context->buildViolation('Le compte destinataire doit être différent du compte source.')
                ->atPath('destinationAccount')
                ->addViolation();
        }
    }

    /** Guards against a category being posted for a type it does not apply to. ex: a "Salaire" category on an expense transaction. **/
    public function validateCategoryType(TransactionInputDto $input, ExecutionContextInterface $context): void
    {
        if ($input->category === null || !isset($input->type) || $input->category->appliesTo($input->type)) {
            return;
        }

        $context->buildViolation('La catégorie "{{ category }}" ne s\'applique pas aux transactions de type « {{ type }} ».')
            ->setParameter('{{ category }}', $input->category->getName())
            ->setParameter('{{ type }}', $input->type->label())
            ->atPath('category')
            ->addViolation();
    }
}

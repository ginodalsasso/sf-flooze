<?php

declare(strict_types=1);

namespace App\Form\Finance;

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

/**
 * Form for recording a dividend entry on an existing asset.
 */
class AssetDividendFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var Space $space */
        $space = $options['space'];
        /** @var Asset $asset */
        $asset = $options['asset'];
        $requiredAccountType = $asset->getType()->requiredAccountType();

        $builder
            ->add('date', DateType::class, [
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'data' => new \DateTimeImmutable(),
                'constraints' => [new Assert\NotNull(message: 'La date est obligatoire.')],
            ])
            ->add('amount', NumberType::class, [
                'scale' => 2,
                'html5' => false,
                'attr' => ['placeholder' => '0,00'],
                'constraints' => [
                    new Assert\NotNull(message: 'Le montant ne peut pas être vide.'),
                    new Assert\GreaterThan(value: 0, message: 'Le montant doit être supérieur à 0.'),
                ],
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
                'class' => \App\Entity\Account::class,
                'required' => true,
                'placeholder' => 'Choisir un compte de détention',
                'query_builder' => fn(AccountRepository $repo) => $repo->createQueryBuilder('a')
                    ->where('a.space = :space')
                    ->andWhere('a.type = :type')
                    ->andWhere('a.deletedAt IS NULL')
                    ->setParameter('space', $space)
                    ->setParameter('type', $requiredAccountType)
                    ->orderBy('a.name', 'ASC'),
                'choice_label' => fn(\App\Entity\Account $a) => $a->getName() . ' (' . $a->getCurrency()->value . ')',
                'constraints' => [
                    new Assert\NotNull(message: 'Un compte de détention est obligatoire.'),
                ],
            ])
            ->add('fundingAccount', EntityType::class, [
                'class' => \App\Entity\Account::class,
                'required' => true,
                'placeholder' => 'Choisir un compte de destination',
                'query_builder' => fn(AccountRepository $repo) => $repo->createQueryBuilder('a')
                    ->where('a.space = :space')
                    ->andWhere('a.deletedAt IS NULL')
                    ->setParameter('space', $space)
                    ->orderBy('a.name', 'ASC'),
                'choice_label' => fn(\App\Entity\Account $a) => $a->getName() . ' (' . $a->getCurrency()->value . ')',
                'constraints' => [
                    new Assert\NotNull(message: 'Un compte de destination est obligatoire.'),
                ],
            ])
            ->add('note', TextType::class, [
                'required' => false,
                'attr' => ['placeholder' => 'Note optionnelle…'],
                'constraints' => [
                    new Assert\Length(max: 255, maxMessage: 'Maximum {{ limit }} caractères.'),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'constraints' => [
                new Assert\Callback([$this, 'validateAccounts']),
            ],
        ]);
        $resolver->setRequired(['space', 'asset']);
        $resolver->setAllowedTypes('space', Space::class);
        $resolver->setAllowedTypes('asset', Asset::class);
    }

    public function validateAccounts(mixed $data, \Symfony\Component\Validator\Context\ExecutionContextInterface $context): void
    {
        $form = $context->getRoot();
        if (!$form instanceof \Symfony\Component\Form\FormInterface) {
            return;
        }

        $account = $form->get('account')->getData();
        $fundingAccount = $form->get('fundingAccount')->getData();

        if ($account instanceof Account && $fundingAccount instanceof Account && $account->getId() === $fundingAccount->getId()) {
            $context->buildViolation('Le compte de détention et le compte de destination doivent être différents.')
                ->atPath('fundingAccount')
                ->addViolation();
        }
    }
}

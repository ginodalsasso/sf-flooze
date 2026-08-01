<?php

declare(strict_types=1);

namespace App\Form\Finance;

use App\Entity\Account;
use App\Enum\AccountTypeEnum;
use App\Enum\CurrencyEnum;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class AccountFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $currencyLocked = $options['currency_locked'];

        $builder
            ->add('name', TextType::class, [
                'attr' => ['placeholder' => 'Ex. : Compte courant, Livret A, Binance…'],
                'constraints' => [
                    new Assert\NotBlank(message: 'Le nom ne peut pas être vide.'),
                    new Assert\Length(max: 100, maxMessage: 'Maximum {{ limit }} caractères.'),
                ],
            ])
            ->add('type', EnumType::class, [
                'class' => AccountTypeEnum::class,
                'choice_label' => fn(AccountTypeEnum $t) => $t->label(),
            ])
            // Always editable: it is only a starting point, correcting it rewrites no transaction.
            ->add('openingBalance', NumberType::class, [
                'scale' => 2,
                'html5' => false,
                'attr' => ['placeholder' => '0,00'],
                'help' => 'Solde du compte avant son suivi dans Flooze. Les transactions s\'y ajoutent pour former le solde actuel.',
                'constraints' => [
                    new Assert\NotNull(message: 'Le solde initial ne peut pas être vide.'),
                ],
            ])
            ->add('currency', EnumType::class, [
                'class' => CurrencyEnum::class,
                'choice_label' => fn(CurrencyEnum $c) => $c->display(),
                // Changing it would reinterpret every amount already recorded on the account.
                'disabled' => $currencyLocked,
                'help' => $currencyLocked ? 'La devise ne peut plus être modifiée car des transactions existent sur ce compte.' : null,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Account::class,
            'currency_locked' => false,
        ]);
        $resolver->setAllowedTypes('currency_locked', 'bool');
    }
}

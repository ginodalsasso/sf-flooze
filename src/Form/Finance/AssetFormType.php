<?php

declare(strict_types=1);

namespace App\Form\Finance;

use App\Entity\Account;
use App\Entity\Asset;
use App\Entity\Space;
use App\Enum\AssetTypeEnum;
use App\Enum\CurrencyEnum;
use App\Repository\AccountRepository;
use App\Service\Finance\Contract\CryptoPriceApiClientInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Event\PreSubmitEvent;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Form for creating a new Asset with its initial buy entry.
 * The quantity, avgPrice, date, fxRate and fees fields are not mapped to the
 * Asset and are used by AssetEntryService to create the first buy.
 */
class AssetFormType extends AbstractType
{
    public function __construct(
        private readonly CryptoPriceApiClientInterface $cryptoPriceApiClient,
    ) {}

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var Space $space */
        $space = $options['space'];

        $builder
            // Asset fields
            ->add('ticker', TextType::class, [
                'attr' => ['placeholder' => 'Ex. : AAPL, BTC, MSSP.PA…'],
                'constraints' => [
                    new Assert\NotBlank(message: 'Le ticker ne peut pas être vide.'),
                    new Assert\Length(max: 20, maxMessage: 'Maximum {{ limit }} caractères.'),
                ],
            ])
            ->add('name', TextType::class, [
                'attr' => ['placeholder' => 'Ex. : Apple Inc., Bitcoin…'],
                'constraints' => [
                    new Assert\NotBlank(message: 'Le nom ne peut pas être vide.'),
                    new Assert\Length(max: 100, maxMessage: 'Maximum {{ limit }} caractères.'),
                ],
            ])
            ->add('type', EnumType::class, [
                'class' => AssetTypeEnum::class,
                'choice_label' => fn(AssetTypeEnum $t) => $t->label(),
            ])
            ->add('currency', EnumType::class, [
                'class' => CurrencyEnum::class,
                'choice_label' => fn(CurrencyEnum $c) => $c->display(),
            ])
            // Account that will hold the asset
            ->add('account', EntityType::class, [
                'class' => Account::class,
                'mapped' => false,
                'required' => true,
                'placeholder' => 'Choisir un compte',
                'query_builder' => fn(AccountRepository $repo) => $repo->createQueryBuilder('a')
                    ->where('a.space = :space')
                    ->andWhere('a.deletedAt IS NULL')
                    ->setParameter('space', $space)
                    ->orderBy('a.name', 'ASC'),
                'choice_label' => fn(Account $a) => $a->getName() . ' (' . $a->getType()->label() . ', ' . $a->getCurrency()->value . ')',
                'constraints' => [
                    new Assert\NotNull(message: 'Un compte de détention est obligatoire.'),
                ],
            ])
            // Account used to pay for the asset
            ->add('fundingAccount', EntityType::class, [
                'class' => Account::class,
                'mapped' => false,
                'required' => true,
                'placeholder' => 'Choisir un compte de paiement',
                'query_builder' => fn(AccountRepository $repo) => $repo->createQueryBuilder('a')
                    ->where('a.space = :space')
                    ->andWhere('a.deletedAt IS NULL')
                    ->setParameter('space', $space)
                    ->orderBy('a.name', 'ASC'),
                'choice_label' => fn(Account $a) => $a->getName() . ' (' . $a->getType()->label() . ', ' . $a->getCurrency()->value . ')',
                'constraints' => [
                    new Assert\NotNull(message: 'Un compte de paiement est obligatoire.'),
                ],
            ]);

        if ($options['with_initial_entry']) {
            $this->addInitialEntryFields($builder);
        }
    }

    /** Not mapped to Asset: AssetEntryService turns them into the first buy. */
    private function addInitialEntryFields(FormBuilderInterface $builder): void
    {
        $builder
            ->add('entryDate', DateType::class, [
                'mapped' => false,
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'data' => new \DateTimeImmutable(),
                'constraints' => [new Assert\NotNull(message: 'La date est obligatoire.')],
            ])
            ->add('entryQuantity', NumberType::class, [
                'mapped' => false,
                'scale' => 8,
                'html5' => false,
                'attr' => ['placeholder' => '0,00000000'],
                'constraints' => [
                    new Assert\NotNull(message: 'La quantité ne peut pas être vide.'),
                    new Assert\GreaterThan(value: 0, message: 'La quantité doit être supérieure à 0.'),
                ],
            ])
            ->add('entryUnitPrice', NumberType::class, [
                'mapped' => false,
                'scale' => 4,
                'html5' => false,
                'attr' => ['placeholder' => '0,0000'],
                'help' => 'Pour une crypto, le cours du marché s\'applique automatiquement : la valeur saisie ici est ignorée.',
                'constraints' => [
                    new Assert\NotNull(message: 'Le prix unitaire ne peut pas être vide.'),
                    new Assert\GreaterThan(value: 0, message: 'Le prix doit être supérieur à 0.'),
                ],
            ])
            ->add('entryFxRate', NumberType::class, [
                'mapped' => false,
                'scale' => 6,
                'html5' => false,
                'attr' => ['placeholder' => '1,000000'],
                'data' => '1',
                'constraints' => [
                    new Assert\NotNull(message: 'Le taux de change ne peut pas être vide.'),
                    new Assert\GreaterThan(value: 0, message: 'Le taux doit être supérieur à 0.'),
                ],
            ])
            ->add('entryFees', NumberType::class, [
                'mapped' => false,
                'scale' => 2,
                'html5' => false,
                'attr' => ['placeholder' => '0,00'],
                'data' => '0',
                'constraints' => [
                    new Assert\NotNull(message: 'Les frais ne peuvent pas être vides.'),
                    new Assert\GreaterThanOrEqual(value: 0, message: 'Les frais ne peuvent pas être négatifs.'),
                ],
            ])
            ->addEventListener(FormEvents::PRE_SUBMIT, $this->imposeCryptoPrice(...));
    }

    /**
     * The initial buy price of a crypto is the market price, not a figure the user types: it is
     * overwritten before validation, so what the browser posts in that field never reaches the
     * ledger. Without a quote — offline and never fetched — the typed price stands, otherwise
     * no crypto could be recorded at all.
     */
    private function imposeCryptoPrice(PreSubmitEvent $event): void
    {
        $data = $event->getData();

        if (!is_array($data) || ($data['type'] ?? null) !== AssetTypeEnum::CRYPTO->value) {
            return;
        }

        $currency = CurrencyEnum::tryFrom((string) ($data['currency'] ?? ''));
        $ticker = trim((string) ($data['ticker'] ?? ''));

        if ($currency === null || $ticker === '') {
            return;
        }

        $price = $this->cryptoPriceApiClient->fetchPrice($ticker, $currency);

        if ($price !== null) {
            $data['entryUnitPrice'] = $price->unitPrice;
            $event->setData($data);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Asset::class,
            'constraints' => [
                new Assert\Callback([$this, 'validateAccountType']),
            ],
        ]);
        $resolver->setRequired('space');
        $resolver->setAllowedTypes('space', Space::class);
        $resolver->setDefault('with_initial_entry', true);
        $resolver->setAllowedTypes('with_initial_entry', 'bool');
    }

    public function validateAccountType(Asset $asset, ExecutionContextInterface $context): void
    {
        $form = $context->getRoot();
        if (!$form instanceof \Symfony\Component\Form\FormInterface) {
            return;
        }

        $account = $form->get('account')->getData();

        if (!$account instanceof Account) {
            return;
        }

        $requiredType = $asset->getType()->requiredAccountType();
        if ($account->getType() !== $requiredType) {
            $context->buildViolation(
                'Ce compte est de type {{ accountType }}. Un compte de type {{ requiredType }} est requis pour un actif de type {{ assetType }}.',
                [
                    '{{ accountType }}' => $account->getType()->label(),
                    '{{ requiredType }}' => $requiredType->label(),
                    '{{ assetType }}' => $asset->getType()->label(),
                ]
            )
                ->atPath('account')
                ->addViolation();
        }
    }
}

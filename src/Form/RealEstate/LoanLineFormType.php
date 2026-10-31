<?php

declare(strict_types=1);

namespace App\Form\RealEstate;

use App\Entity\LoanLine;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class LoanLineFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('label', TextType::class, [
                'attr' => ['placeholder' => 'Ex. : Prêt principal, PTZ, Prêt employeur…'],
                'constraints' => [
                    new Assert\NotBlank(message: 'Le libellé ne peut pas être vide.'),
                    new Assert\Length(max: 100, maxMessage: 'Maximum {{ limit }} caractères.'),
                ],
            ])
            ->add('amount', NumberType::class, [
                'scale' => 2,
                'html5' => false,
                'attr' => ['placeholder' => '0,00'],
                'constraints' => [
                    new Assert\NotNull(message: 'Le capital de la ligne est obligatoire.'),
                    new Assert\GreaterThan(value: 0, message: 'Le capital doit être supérieur à 0.'),
                ],
            ])
            ->add('rate', NumberType::class, [
                'scale' => 4,
                'html5' => false,
                'attr' => ['placeholder' => '1,1000'],
                'help' => 'Taux nominal annuel en %. 0 pour un PTZ.',
                'constraints' => [
                    new Assert\NotNull(message: 'Le taux est obligatoire (0 pour un PTZ).'),
                    new Assert\Range(
                        notInRangeMessage: 'Un taux annuel se situe entre {{ min }} et {{ max }} %.',
                        min: 0,
                        max: 25,
                    ),
                ],
            ])
            ->add('insuranceRate', NumberType::class, [
                'scale' => 4,
                'html5' => false,
                'attr' => ['placeholder' => '0,3600'],
                'help' => 'Taux annuel d\'assurance, appliqué au capital de cette ligne.',
                'constraints' => [
                    new Assert\NotNull(message: 'Le taux d\'assurance est obligatoire (0 si aucune).'),
                    new Assert\Range(
                        notInRangeMessage: 'Un taux d\'assurance se situe entre {{ min }} et {{ max }} %.',
                        min: 0,
                        max: 5,
                    ),
                ],
            ])
            ->add('durationMonths', IntegerType::class, [
                'attr' => ['placeholder' => '240'],
                'help' => 'Durée en mois : 240 pour 20 ans, 300 pour 25 ans.',
                'constraints' => [
                    new Assert\NotNull(message: 'La durée est obligatoire.'),
                    new Assert\Range(
                        notInRangeMessage: 'La durée doit se situer entre {{ min }} et {{ max }} mois.',
                        min: 1,
                        max: 600,
                    ),
                ],
            ])
            ->add('negotiatedMonthlyPayment', NumberType::class, [
                'scale' => 2,
                'html5' => false,
                'required' => false,
                'label' => 'Mensualité négociée (si lissée par la banque)',
                'attr' => ['placeholder' => 'Laisser vide pour calculer depuis capital/taux/durée'],
                'help' => 'Capital + intérêts uniquement, hors assurance. À renseigner seulement si la banque a lissé cette ligne avec les autres : la mensualité réelle ne correspond alors plus à la formule d\'annuité.',
                'constraints' => [
                    new Assert\PositiveOrZero(message: 'La mensualité négociée doit être positive ou nulle.'),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => LoanLine::class]);
    }
}

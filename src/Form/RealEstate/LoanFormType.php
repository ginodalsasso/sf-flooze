<?php

declare(strict_types=1);

namespace App\Form\RealEstate;

use App\Entity\Loan;
use App\Entity\Property;
use App\Entity\Space;
use App\Repository\PropertyRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/** The loan file. Capital, rates and durations are entered line by line, on its detail page. */
class LoanFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var Space $space */
        $space = $options['space'];

        $builder
            // Any property can be funded: only the deductibility of the interest differs.
            ->add('property', EntityType::class, [
                'class' => Property::class,
                'query_builder' => fn(PropertyRepository $repo) => $repo->createQueryBuilder('p')
                    ->where('p.space = :space')
                    ->andWhere('p.deletedAt IS NULL')
                    ->setParameter('space', $space)
                    ->orderBy('p.name', 'ASC'),
                'choice_label' => 'name',
                'constraints' => [new Assert\NotNull(message: 'Le bien financé est obligatoire.')],
            ])
            ->add('bankName', TextType::class, [
                'attr' => ['placeholder' => 'Ex. : Crédit Agricole, BNP…'],
                'constraints' => [
                    new Assert\NotBlank(message: 'Le nom de la banque ne peut pas être vide.'),
                    new Assert\Length(max: 100, maxMessage: 'Maximum {{ limit }} caractères.'),
                ],
            ])
            ->add('startDate', DateType::class, [
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'help' => 'Déblocage des fonds. Toutes les lignes en partent, et la première échéance tombe un mois après.',
                'constraints' => [new Assert\NotNull(message: 'La date de départ est obligatoire.')],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Loan::class]);
        $resolver->setRequired('space');
        $resolver->setAllowedTypes('space', Space::class);
    }
}

<?php

declare(strict_types=1);

namespace App\Form\RealEstate;

use App\Entity\Lease;
use App\Entity\Property;
use App\Entity\Space;
use App\Entity\Tenant;
use App\Enum\LeaseTypeEnum;
use App\Enum\PropertyTypeEnum;
use App\Repository\PropertyRepository;
use App\Repository\TenantRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class LeaseFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var Space $space */
        $space = $options['space'];

        $builder
            // A primary residence is excluded here and re-checked in LeaseService.
            ->add('property', EntityType::class, [
                'class' => Property::class,
                'query_builder' => fn(PropertyRepository $repo) => $repo->createQueryBuilder('p')
                    ->where('p.space = :space')
                    ->andWhere('p.deletedAt IS NULL')
                    ->andWhere('p.type != :primary')
                    ->setParameter('space', $space)
                    ->setParameter('primary', PropertyTypeEnum::PRIMARY->value)
                    ->orderBy('p.name', 'ASC'),
                'choice_label' => 'name',
                'constraints' => [new Assert\NotNull(message: 'Le bien loué est obligatoire.')],
            ])
            ->add('tenants', EntityType::class, [
                'class' => Tenant::class,
                'multiple' => true,
                'query_builder' => fn(TenantRepository $repo) => $repo->createQueryBuilder('t')
                    ->where('t.space = :space')
                    ->setParameter('space', $space)
                    ->orderBy('t.lastName', 'ASC')
                    ->addOrderBy('t.firstName', 'ASC'),
                'choice_label' => fn(Tenant $tenant) => $tenant->getFullName(),
                'help' => 'Plusieurs locataires pour une colocation.',
                'constraints' => [new Assert\Count(min: 1, minMessage: 'Sélectionne au moins un locataire.')],
            ])
            ->add('type', EnumType::class, [
                'class' => LeaseTypeEnum::class,
                'choice_label' => fn(LeaseTypeEnum $type) => $type->label(),
            ])
            ->add('rent', NumberType::class, [
                'scale' => 2,
                'html5' => false,
                'attr' => ['placeholder' => '0,00'],
                'help' => 'Loyer hors charges.',
                'constraints' => [
                    new Assert\NotNull(message: 'Le loyer est obligatoire.'),
                    new Assert\GreaterThan(value: 0, message: 'Le loyer doit être supérieur à 0.'),
                ],
            ])
            ->add('charges', NumberType::class, [
                'scale' => 2,
                'html5' => false,
                'attr' => ['placeholder' => '0,00'],
                'help' => 'Provision mensuelle pour charges.',
                'constraints' => [
                    new Assert\NotNull(message: 'Les charges sont obligatoires (0 si aucune).'),
                    new Assert\PositiveOrZero(message: 'Les charges ne peuvent pas être négatives.'),
                ],
            ])
            ->add('securityDeposit', NumberType::class, [
                'scale' => 2,
                'html5' => false,
                'attr' => ['placeholder' => '0,00'],
                'constraints' => [
                    new Assert\NotNull(message: 'Le dépôt de garantie est obligatoire (0 si aucun).'),
                    new Assert\PositiveOrZero(message: 'Le dépôt de garantie ne peut pas être négatif.'),
                ],
            ])
            ->add('startDate', DateType::class, [
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'constraints' => [new Assert\NotNull(message: 'La date de début est obligatoire.')],
            ])
            ->add('endDate', DateType::class, [
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'help' => 'Terme signé. Passé cette date, le bail est reconduit tacitement par tranches d\'un an tant qu\'il n\'est pas clôturé.',
                'constraints' => [
                    new Assert\NotNull(message: 'La date de fin est obligatoire.'),
                    new Assert\GreaterThanOrEqual(
                        propertyPath: 'parent.all[startDate].data',
                        message: 'La date de fin ne peut pas précéder la date de début.',
                    ),
                ],
            ])
            ->add('isActive', CheckboxType::class, [
                'required' => false,
                'help' => 'Clôturer le bail arrête sa reconduction tacite et ses échéances de loyer.',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Lease::class]);
        $resolver->setRequired('space');
        $resolver->setAllowedTypes('space', Space::class);
    }
}

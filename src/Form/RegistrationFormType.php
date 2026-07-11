<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\IsTrue;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\PasswordStrength;
use Symfony\Component\Validator\Constraints\Regex;

class RegistrationFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('firstName', TextType::class, [
                'constraints' => [
                    new NotBlank(message: 'Veuillez saisir votre prénom.'),
                    new Length(min: 2, max: 100),
                    new Regex(
                        pattern: '/^[\p{L}\p{M}\s\'\-\.]+$/u',
                        message: 'Le prénom ne peut contenir que des lettres, espaces, tirets, apostrophes ou points.',
                    ),
                ],
            ])
            ->add('lastName', TextType::class, [
                'constraints' => [
                    new NotBlank(message: 'Veuillez saisir votre nom.'),
                    new Length(min: 2, max: 100),
                    new Regex(
                        pattern: '/^[\p{L}\p{M}\s\'\-\.]+$/u',
                        message: 'Le nom ne peut contenir que des lettres, espaces, tirets, apostrophes ou points.',
                    ),
                ],
            ])
            ->add('email', EmailType::class, [
                'constraints' => [
                    new NotBlank(message: 'Veuillez saisir votre adresse email.'),
                    new Email(mode: Email::VALIDATION_MODE_STRICT),
                ],
            ])
            ->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'mapped' => false,
                'invalid_message' => 'Les deux mots de passe doivent correspondre.',
                'options' => ['attr' => ['autocomplete' => 'new-password']],
                'first_options' => ['label' => 'Mot de passe'],
                'second_options' => ['label' => 'Confirmer le mot de passe'],
                'constraints' => [
                    new NotBlank(message: 'Veuillez saisir un mot de passe.'),
                    new Length(
                        min: 12,
                        max: 4096,
                        minMessage: 'Votre mot de passe doit contenir au moins {{ limit }} caractères.',
                        maxMessage: 'Votre mot de passe ne peut pas dépasser {{ limit }} caractères.',
                    ),
                    new Regex(
                        pattern: '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^a-zA-Z\d\s])/',
                        message: 'Le mot de passe doit contenir au moins une majuscule, une minuscule, un chiffre et un caractère spécial.',
                    ),
                    new PasswordStrength(minScore: PasswordStrength::STRENGTH_MEDIUM),
                ],
            ])
            ->add('agreeTerms', CheckboxType::class, [
                'mapped' => false,
                'constraints' => [
                    new IsTrue(message: 'Vous devez accepter les conditions d\'utilisation.'),
                ],
            ])
        ;

        $builder->get('email')->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event): void {
            $value = $event->getData();
            if (is_string($value)) {
                $event->setData(mb_strtolower($value));
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}

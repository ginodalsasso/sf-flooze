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
                    new NotBlank(message: 'Please enter your first name.'),
                    new Length(min: 2, max: 100),
                    new Regex(
                        pattern: '/^[\p{L}\p{M}\s\'\-\.]+$/u',
                        message: 'First name may only contain letters, spaces, hyphens, apostrophes or dots.',
                    ),
                ],
            ])
            ->add('lastName', TextType::class, [
                'constraints' => [
                    new NotBlank(message: 'Please enter your last name.'),
                    new Length(min: 2, max: 100),
                    new Regex(
                        pattern: '/^[\p{L}\p{M}\s\'\-\.]+$/u',
                        message: 'Last name may only contain letters, spaces, hyphens, apostrophes or dots.',
                    ),
                ],
            ])
            ->add('email', EmailType::class, [
                'constraints' => [
                    new NotBlank(message: 'Please enter your email.'),
                    new Email(mode: Email::VALIDATION_MODE_STRICT),
                ],
            ])
            ->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'mapped' => false,
                'invalid_message' => 'The password fields must match.',
                'options' => ['attr' => ['autocomplete' => 'new-password']],
                'first_options' => ['label' => 'Password'],
                'second_options' => ['label' => 'Repeat password'],
                'constraints' => [
                    new NotBlank(message: 'Please enter a password.'),
                    new Length(
                        min: 12,
                        max: 4096,
                        minMessage: 'Your password must be at least {{ limit }} characters.',
                        maxMessage: 'Your password cannot be longer than {{ limit }} characters.',
                    ),
                    new Regex(
                        pattern: '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^a-zA-Z\d\s])/',
                        message: 'Password must contain at least one uppercase letter, one lowercase letter, one digit, and one special character.',
                    ),
                    new PasswordStrength(minScore: PasswordStrength::STRENGTH_MEDIUM),
                ],
            ])
            ->add('agreeTerms', CheckboxType::class, [
                'mapped' => false,
                'constraints' => [
                    new IsTrue(message: 'You must agree to the terms of service.'),
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

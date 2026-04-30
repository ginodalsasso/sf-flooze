<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Form\RegistrationFormType;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class RegistrationController extends AbstractController
{
    #[Route('/register', name: 'app_register')]
    public function register(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $em,
        MailerInterface $mailer,
        RateLimiterFactoryInterface $registrationLimiter,
    ): Response {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_home');
        }

        $limiter = $registrationLimiter->create($request->getClientIp());
        if (false === $limiter->consume(1)->isAccepted()) {
            throw new TooManyRequestsHttpException();
        }

        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user->setEmail(mb_strtolower(trim($user->getEmail())));
            $user->setPassword(
                $passwordHasher->hashPassword($user, $form->get('plainPassword')->getData())
            );

            $token = bin2hex(random_bytes(32));
            $user->setVerificationToken($token);
            $user->setVerificationTokenExpiresAt(new \DateTimeImmutable('+24 hours'));
            $user->setIsVerified(false);

            $em->persist($user);
            $em->flush();
            
            $verifyUrl = $this->verifyUrl($user);

            $this->generateEmail($user, $verifyUrl, $mailer);

            $this->addFlash('success', 'Account created. Please check your email to verify your account.');

            return $this->redirectToRoute('app_login');
        }

        return $this->render('registration/register.html.twig', [
            'registrationForm' => $form,
        ]);
    }

    private function verifyUrl(User $user): string
    {
        return $this->generateUrl('app_verify_email', [
            'id' => $user->getId(),
            'token' => $user->getVerificationToken(),
        ], UrlGeneratorInterface::ABSOLUTE_URL);
    }

    private function generateEmail(
        User $user,
        string $verifyUrl,
        MailerInterface $mailer,
    ): void
    {
        $email = (new Email())
            ->from('')
            ->to($user->getEmail())
            ->subject('Verify your Flooze account')
            ->html($this->renderView('emails/verify_email.html.twig', [
                'user' => $user,
                'verifyUrl' => $verifyUrl,
            ]));

        $mailer->send($email);
    }

    #[Route('/verify-email/{id}/{token}', name: 'app_verify_email')]
    public function verifyEmail(
        int $id,
        string $token,
        UserRepository $userRepository,
        EntityManagerInterface $em,
    ): Response {
        $user = $userRepository->find($id);

        if (!$user || !$user->getVerificationToken() || !hash_equals($user->getVerificationToken(), $token)) {
            $this->addFlash('error', 'Invalid or expired verification link.');

            return $this->redirectToRoute('app_login');
        }

        if ($user->getVerificationTokenExpiresAt() < new \DateTimeImmutable()) {
            $this->addFlash('error', 'Verification link has expired. Please register again.');

            return $this->redirectToRoute('app_login');
        }

        if ($user->isVerified()) {
            $this->addFlash('info', 'Your account is already verified.');

            return $this->redirectToRoute('app_login');
        }

        $user->setIsVerified(true);
        $user->setVerificationToken(null);
        $user->setVerificationTokenExpiresAt(null);
        $em->flush();

        $this->addFlash('success', 'Email verified! You can now sign in.');

        return $this->redirectToRoute('app_login');
    }
}

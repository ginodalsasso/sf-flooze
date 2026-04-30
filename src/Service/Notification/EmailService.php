<?php

declare(strict_types=1);

namespace App\Service\Notification;

use App\Entity\User;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

class EmailService
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly Environment $twig,
        private readonly string $emailSender, // init in services.yaml
    ) {}

    public function sendVerificationEmail(User $user): void
    {
        $verifyUrl = $this->urlGenerator->generate('app_verify_email', [
            'id' => $user->getId(),
            'token' => $user->getVerificationToken(),
        ], UrlGeneratorInterface::ABSOLUTE_URL);

        $html = $this->twig->render('emails/verify_email.html.twig', [
            'user' => $user,
            'verifyUrl' => $verifyUrl,
        ]);

        $email = (new Email())
            ->from($this->emailSender)
            ->to($user->getEmail())
            ->subject('Verify your Flooze account')
            ->html($html);

        $this->mailer->send($email);
    }
}

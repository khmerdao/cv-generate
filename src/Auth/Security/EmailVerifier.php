<?php

declare(strict_types=1);

namespace App\Auth\Security;

use App\Auth\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Contracts\Translation\TranslatorInterface;
use SymfonyCasts\Bundle\VerifyEmail\Exception\VerifyEmailExceptionInterface;
use SymfonyCasts\Bundle\VerifyEmail\VerifyEmailHelperInterface;

final class EmailVerifier
{
    public function __construct(
        private readonly VerifyEmailHelperInterface $helper,
        private readonly MailerInterface $mailer,
        private readonly EntityManagerInterface $em,
        private readonly TranslatorInterface $translator,
        private readonly string $mailerFrom,
    ) {
    }

    public function sendConfirmation(User $user): void
    {
        $signature = $this->helper->generateSignature(
            'app_verify_email',
            (string) $user->getId(),
            $user->getEmail(),
            ['id' => (string) $user->getId(), '_locale' => $user->getLocale()],
        );

        $email = new TemplatedEmail()
            ->from(Address::create($this->mailerFrom))
            ->to($user->getEmail())
            ->subject($this->translator->trans('email.verify.subject', locale: $user->getLocale()))
            ->htmlTemplate('email/verify.html.twig')
            ->locale($user->getLocale())
            ->context(['signedUrl' => $signature->getSignedUrl(), 'expiresAt' => $signature->getExpiresAt()]);

        $this->mailer->send($email);
    }

    /** @throws VerifyEmailExceptionInterface */
    public function handleConfirmation(Request $request, User $user): void
    {
        $this->helper->validateEmailConfirmationFromRequest($request, (string) $user->getId(), $user->getEmail());
        $user->markVerified();
        $this->em->flush();
    }
}

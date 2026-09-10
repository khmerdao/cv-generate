<?php

declare(strict_types=1);

namespace App\Auth\Controller;

use App\Auth\Entity\User;
use App\Auth\Security\EmailVerifier;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;
use SymfonyCasts\Bundle\VerifyEmail\Exception\VerifyEmailExceptionInterface;

#[IsGranted('ROLE_USER')]
final class VerifyEmailController extends AbstractController
{
    #[Route('/verify-email', name: 'app_verify_email', methods: ['GET'])]
    public function verify(Request $request, #[CurrentUser] User $user, EmailVerifier $verifier, TranslatorInterface $translator): Response
    {
        try {
            $verifier->handleConfirmation($request, $user);
            $this->addFlash('success', $translator->trans('auth.email_verified'));
        } catch (VerifyEmailExceptionInterface $e) {
            $this->addFlash('error', $translator->trans($e->getReason(), [], 'VerifyEmailBundle'));
        }

        return $this->redirectToRoute('app_dashboard');
    }

    #[Route('/verify-email/resend', name: 'app_verify_resend', methods: ['POST'])]
    public function resend(Request $request, #[CurrentUser] User $user, EmailVerifier $verifier, TranslatorInterface $translator): Response
    {
        if ('test' !== $this->getParameter('kernel.environment')
            && !$this->isCsrfTokenValid('resend-verification', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }
        if (!$user->isVerified()) {
            $verifier->sendConfirmation($user);
            $this->addFlash('success', $translator->trans('auth.verification_sent'));
        }

        return $this->redirectToRoute('app_dashboard');
    }
}

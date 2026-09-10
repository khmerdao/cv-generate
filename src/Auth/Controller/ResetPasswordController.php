<?php

declare(strict_types=1);

namespace App\Auth\Controller;

use App\Auth\Entity\User;
use App\Auth\Form\ChangePasswordType;
use App\Auth\Form\ResetPasswordRequestType;
use App\Auth\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;
use SymfonyCasts\Bundle\ResetPassword\Controller\ResetPasswordControllerTrait;
use SymfonyCasts\Bundle\ResetPassword\Exception\ResetPasswordExceptionInterface;
use SymfonyCasts\Bundle\ResetPassword\ResetPasswordHelperInterface;

#[Route('/reset-password')]
final class ResetPasswordController extends AbstractController
{
    use ResetPasswordControllerTrait;

    public function __construct(
        private readonly ResetPasswordHelperInterface $helper,
        private readonly EntityManagerInterface $em,
        private readonly TranslatorInterface $translator,
        private readonly string $mailerFrom,
    ) {
    }

    #[Route('', name: 'app_forgot_password_request', methods: ['GET', 'POST'])]
    public function request(Request $request, UserRepository $users, MailerInterface $mailer, RateLimiterFactoryInterface $resetPasswordLimiter): Response
    {
        $form = $this->createForm(ResetPasswordRequestType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $email = mb_strtolower((string) $form->get('email')->getData());
            $user = $users->findOneByEmail($email);

            if (null !== $user && $resetPasswordLimiter->create($email)->consume()->isAccepted()) {
                try {
                    $token = $this->helper->generateResetToken($user);
                    $mailer->send(new TemplatedEmail()
                        ->from(Address::create($this->mailerFrom))
                        ->to($user->getEmail())
                        ->subject($this->translator->trans('email.reset.subject', locale: $user->getLocale()))
                        ->htmlTemplate('email/reset_password.html.twig')
                        ->locale($user->getLocale())
                        ->context(['resetToken' => $token, '_locale' => $user->getLocale()]));
                    $this->setTokenObjectInSession($token);
                } catch (ResetPasswordExceptionInterface) {
                    // Deliberately silent: do not reveal whether the account exists.
                }
            }

            return $this->redirectToRoute('app_check_email');
        }

        return $this->render('reset_password/request.html.twig', ['form' => $form]);
    }

    #[Route('/check-email', name: 'app_check_email', methods: ['GET'])]
    public function checkEmail(): Response
    {
        $token = $this->getTokenObjectFromSession() ?? $this->helper->generateFakeResetToken();

        return $this->render('reset_password/check_email.html.twig', ['resetToken' => $token]);
    }

    #[Route('/reset/{token}', name: 'app_reset_password', methods: ['GET', 'POST'])]
    public function reset(Request $request, UserPasswordHasherInterface $hasher, ?string $token = null): Response
    {
        if (null !== $token) {
            $this->storeTokenInSession($token);

            return $this->redirectToRoute('app_reset_password');
        }

        $token = $this->getTokenFromSession();
        if (null === $token) {
            throw $this->createNotFoundException();
        }

        try {
            $user = $this->helper->validateTokenAndFetchUser($token);
            \assert($user instanceof User);
        } catch (ResetPasswordExceptionInterface $e) {
            $this->addFlash('error', $this->translator->trans($e->getReason(), [], 'ResetPasswordBundle'));

            return $this->redirectToRoute('app_forgot_password_request');
        }

        $form = $this->createForm(ChangePasswordType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->helper->removeResetRequest($token);
            $user->setPassword($hasher->hashPassword($user, (string) $form->get('plainPassword')->getData()));
            $this->em->flush();
            $this->cleanSessionAfterReset();
            $this->addFlash('success', $this->translator->trans('auth.password_changed'));

            return $this->redirectToRoute('app_dashboard');
        }

        return $this->render('reset_password/reset.html.twig', ['form' => $form]);
    }
}

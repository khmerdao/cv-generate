<?php

declare(strict_types=1);

namespace App\Auth\Controller;

use App\Auth\Entity\User;
use App\Auth\Form\RegistrationType;
use App\Auth\Repository\UserRepository;
use App\Auth\Security\EmailVerifier;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

final class RegistrationController extends AbstractController
{
    #[Route('/register', name: 'app_register', methods: ['GET', 'POST'])]
    public function register(
        Request $request,
        UserPasswordHasherInterface $hasher,
        EntityManagerInterface $em,
        UserRepository $users,
        Security $security,
        EmailVerifier $emailVerifier,
        RateLimiterFactoryInterface $registerLimiter,
        TranslatorInterface $translator,
    ): Response {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_dashboard');
        }

        $form = $this->createForm(RegistrationType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if (!$registerLimiter->create($request->getClientIp() ?? 'unknown')->consume()->isAccepted()) {
                $form->addError(new FormError($translator->trans('auth.too_many_attempts')));
            } elseif (null !== $users->findOneByEmail((string) $form->get('email')->getData())) {
                $form->get('email')->addError(new FormError($translator->trans('auth.email_taken')));
            } else {
                $user = new User(mb_strtolower((string) $form->get('email')->getData()), $request->getLocale());
                $user->setPassword($hasher->hashPassword($user, (string) $form->get('plainPassword')->getData()));
                $em->persist($user);
                $em->flush();

                $emailVerifier->sendConfirmation($user);
                $security->login($user, 'form_login', 'main');

                return $this->redirectToRoute('app_dashboard');
            }
        }

        return $this->render('registration/register.html.twig', ['form' => $form], new Response(
            status: $form->isSubmitted() && !$form->isValid() ? 422 : 200,
        ));
    }
}

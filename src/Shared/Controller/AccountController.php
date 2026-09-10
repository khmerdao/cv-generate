<?php

declare(strict_types=1);

namespace App\Shared\Controller;

use App\Auth\Entity\User;
use App\Auth\Form\ChangePasswordType;
use App\Shared\Form\DeleteAccountType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/app/account')]
#[IsGranted('ROLE_USER')]
final class AccountController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[Route('', name: 'app_account', methods: ['GET'])]
    public function index(#[CurrentUser] User $user): Response
    {
        return $this->renderPage($user, $this->createChangePasswordForm(), $this->createDeleteForm());
    }

    #[Route('/password', name: 'app_account_password', methods: ['POST'])]
    public function changePassword(Request $request, #[CurrentUser] User $user, UserPasswordHasherInterface $hasher): Response
    {
        $form = $this->createChangePasswordForm();
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $user->setPassword($hasher->hashPassword($user, (string) $form->get('plainPassword')->getData()));
            $this->em->flush();
            $this->addFlash('success', $this->translator->trans('auth.password_changed'));

            return $this->redirectToRoute('app_account');
        }

        return $this->renderPage($user, $form, $this->createDeleteForm(), 422);
    }

    #[Route('/delete', name: 'app_account_delete', methods: ['POST'])]
    public function delete(Request $request, #[CurrentUser] User $user, Security $security): Response
    {
        $form = $this->createDeleteForm();
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $security->logout(false);
            $this->purgeUserData($user->getId());
            $this->em->remove($user);
            $this->em->flush();
            $this->addFlash('success', $this->translator->trans('account.deleted'));

            return $this->redirectToRoute('app_home');
        }

        return $this->renderPage($user, $this->createChangePasswordForm(), $form, 422);
    }

    /**
     * Explicitly purge every row owned by the user. The DB has ON DELETE CASCADE,
     * but the account-deletion guarantee should not depend on the platform (SQLite
     * needs a per-connection PRAGMA the test transport does not run). Subscription
     * is removed by ORM cascade on User. Shared JobOffer rows are kept.
     */
    private function purgeUserData(Uuid $userId): void
    {
        $dql = [
            'App\Tailoring\Entity\Tailoring',
            'App\Resume\Entity\Resume',
            'App\Billing\Entity\UsageCounter',
            'App\Auth\Entity\ResetPasswordRequest',
        ];
        foreach ($dql as $entity) {
            $this->em->createQuery(sprintf('DELETE FROM %s e WHERE e.user = :user', $entity))
                ->setParameter('user', $userId, 'uuid')
                ->execute();
        }
    }

    /** @return FormInterface<mixed> */
    private function createChangePasswordForm(): FormInterface
    {
        return $this->createForm(ChangePasswordType::class, null, ['action' => $this->generateUrl('app_account_password')]);
    }

    /** @return FormInterface<mixed> */
    private function createDeleteForm(): FormInterface
    {
        return $this->createForm(DeleteAccountType::class, null, [
            'action' => $this->generateUrl('app_account_delete'),
            'word' => $this->translator->trans('account.delete_word'),
        ]);
    }

    /**
     * @param FormInterface<mixed> $passwordForm
     * @param FormInterface<mixed> $deleteForm
     */
    private function renderPage(User $user, FormInterface $passwordForm, FormInterface $deleteForm, int $status = 200): Response
    {
        return $this->render('account/index.html.twig', [
            'user' => $user,
            'passwordForm' => $passwordForm,
            'deleteForm' => $deleteForm,
        ], new Response(status: $status));
    }
}

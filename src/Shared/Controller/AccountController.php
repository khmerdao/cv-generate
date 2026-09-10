<?php

declare(strict_types=1);

namespace App\Shared\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class AccountController extends AbstractController
{
    #[Route('/account', name: 'app_account', methods: ['GET'])]
    public function index(): Response
    {
        // Fleshed out in Task 11 (change password, locale, delete account).
        return $this->render('account/index.html.twig');
    }
}

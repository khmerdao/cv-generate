<?php

declare(strict_types=1);

namespace App\Shared\Controller;

use App\Auth\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class LocaleController extends AbstractController
{
    /** Never reached: LocaleSubscriber redirects first. Exists so the router does not 404 unprefixed paths. */
    #[Route('/{path}', name: 'app_locale_fallback', requirements: ['path' => '(?!fr/|en/|fr$|en$|_|webhooks/).*'], defaults: ['_locale' => 'fr', 'path' => ''], priority: -100)]
    public function fallback(): Response
    {
        throw $this->createNotFoundException();
    }

    #[Route('/{_locale}/locale/{new}', name: 'app_locale_switch', requirements: ['_locale' => 'fr|en', 'new' => 'fr|en'], methods: ['POST'])]
    public function switch(string $new, Request $request, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        if ($user instanceof User) {
            $user->setLocale($new);
            $em->flush();
        }
        $redirect = (string) $request->request->get('_redirect', '/'.$new.'/app');
        $redirect = preg_replace('#^/(fr|en)(?=/|$)#', '/'.$new, $redirect) ?? '/'.$new;
        if (!str_starts_with($redirect, '/') || str_starts_with($redirect, '//')) {
            $redirect = '/'.$new;
        }

        return $this->redirect($redirect);
    }
}

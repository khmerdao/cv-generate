<?php

declare(strict_types=1);

namespace App\Shared\Controller;

use App\Auth\Entity\User;
use App\Shared\Mercure\MercureTopicAuthorizer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

#[IsGranted('ROLE_USER')]
#[Route('/app/mercure-check')]
final class MercureCheckController extends AbstractController
{
    #[Route('', name: 'app_mercure_check', methods: ['GET'])]
    public function index(#[CurrentUser] User $user, MercureTopicAuthorizer $authorizer, HubInterface $hub): Response
    {
        $this->assertDev();

        $id = Uuid::v7();
        $topic = $authorizer->topicFor($id);
        $authorizer->subscribeCookieFor($user, [$topic]);

        return $this->render('mercure_check/index.html.twig', ['topic' => $topic, 'hubUrl' => $hub->getPublicUrl()]);
    }

    #[Route('/publish', name: 'app_mercure_check_publish', methods: ['POST'])]
    public function publish(HubInterface $hub, Request $request): Response
    {
        $this->assertDev();

        if (!$this->isCsrfTokenValid('mercure-check', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $topic = (string) $request->request->get('topic');
        $hub->publish(new Update(
            $topic,
            json_encode(['text' => 'Mercure OK at '.date('H:i:s')], \JSON_THROW_ON_ERROR),
            private: true,
        ));

        return new Response('', 204);
    }

    private function assertDev(): void
    {
        if ('dev' !== $this->getParameter('kernel.environment')) {
            throw $this->createNotFoundException();
        }
    }
}

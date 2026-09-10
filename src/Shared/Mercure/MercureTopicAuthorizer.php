<?php

declare(strict_types=1);

namespace App\Shared\Mercure;

use App\Auth\Entity\User;
use App\Tailoring\Entity\Tailoring;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Mercure\Authorization;
use Symfony\Component\Uid\Uuid;

final readonly class MercureTopicAuthorizer
{
    public function __construct(
        private Authorization $authorization,
        private RequestStack $requestStack,
    ) {
    }

    public function topicFor(Tailoring|Uuid $tailoring): string
    {
        $id = $tailoring instanceof Tailoring ? $tailoring->getId() : $tailoring;

        return '/tailorings/'.$id;
    }

    /** @param list<string> $topics */
    public function subscribeCookieFor(User $user, array $topics): void
    {
        $request = $this->requestStack->getCurrentRequest();
        if (null === $request) {
            return;
        }
        $this->authorization->setCookie($request, $topics, null, ['sub' => (string) $user->getId()]);
    }
}

<?php

declare(strict_types=1);

namespace App\Shared\Locale;

use App\Auth\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class LocaleSubscriber implements EventSubscriberInterface
{
    /** @var list<string> */
    private const array LOCALES = ['fr', 'en'];

    public function __construct(private readonly Security $security, private readonly string $defaultLocale)
    {
    }

    public static function getSubscribedEvents(): array
    {
        // Priority 7: after the firewall (8) so Security::getUser() is populated.
        // The catch-all route in LocaleController keeps the router (priority 32) from 404ing first.
        return [KernelEvents::REQUEST => ['onKernelRequest', 7]];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }
        $request = $event->getRequest();
        $path = $request->getPathInfo();

        if (preg_match('#^/(fr|en)(/|$)#', $path) || str_starts_with($path, '/_') || str_starts_with($path, '/webhooks')) {
            return;
        }

        $user = $this->security->getUser();
        $locale = $user instanceof User
            ? $user->getLocale()
            : ($request->getPreferredLanguage(self::LOCALES) ?? $this->defaultLocale);

        $target = '/'.$locale.('/' === $path ? '' : $path);
        $qs = $request->getQueryString();
        $event->setResponse(new RedirectResponse($target.(null === $qs ? '' : '?'.$qs), 302));
    }
}

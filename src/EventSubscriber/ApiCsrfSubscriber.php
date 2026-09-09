<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

final class ApiCsrfSubscriber implements EventSubscriberInterface
{
    public function __construct(private CsrfTokenManagerInterface $tokens) {}

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::REQUEST => ['validate', 0]];
    }

    public function validate(RequestEvent $event): void
    {
        $request = $event->getRequest();
        if (!$event->isMainRequest() || $request->isMethodSafe()) {
            return;
        }
        if (!str_starts_with($request->getPathInfo(), '/api/') && '/preferences' !== $request->getPathInfo()) {
            return;
        }
        $token = new CsrfToken('infotrak', $request->headers->get('X-CSRF-Token'));
        if (!$this->tokens->isTokenValid($token)) {
            $event->setResponse(new JsonResponse(['error' => 'Session expirée. Rechargez la page puis réessayez.'], 403));
        }
    }
}

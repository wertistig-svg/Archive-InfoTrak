<?php

namespace App\Service;

use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;

/** Un visiteur conserve ses choix dans sa session, sans partager ceux d'un autre. */
final class ReaderIdentity
{
    public function __construct(private Security $security, private RequestStack $requests) {}

    public function ownerKey(): string
    {
        if (null !== $user = $this->security->getUser()) {
            return $user->getUserIdentifier();
        }

        $session = $this->requests->getSession();
        if (!$session->has('infotrak_reader')) {
            $session->set('infotrak_reader', 'guest-'.bin2hex(random_bytes(24)));
        }

        return $session->get('infotrak_reader');
    }
}

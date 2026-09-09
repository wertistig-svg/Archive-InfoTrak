<?php

namespace App\Security;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use KnpU\OAuth2ClientBundle\Security\Authenticator\OAuth2Authenticator;
use League\OAuth2\Client\Provider\GoogleUser;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

/** Connexion via Google (obligatoire) : crée le compte local au premier passage. */
class GoogleAuthenticator extends OAuth2Authenticator implements AuthenticationEntryPointInterface
{
    public function __construct(
        private readonly ClientRegistry $clients,
        private readonly EntityManagerInterface $em,
        private readonly RouterInterface $router,
    ) {}

    public function supports(Request $request): ?bool
    {
        return 'connect_google_check' === $request->attributes->get('_route');
    }

    public function authenticate(Request $request): SelfValidatingPassport
    {
        $accessToken = $this->fetchAccessToken($this->getClient());
        $googleUser = $this->getClient()->fetchUserFromToken($accessToken);
        \assert($googleUser instanceof GoogleUser);

        return new SelfValidatingPassport(
            new UserBadge((string) $googleUser->getId(), function () use ($googleUser): User {
                $user = $this->em->getRepository(User::class)->findOneBy(['googleId' => (string) $googleUser->getId()]);
                if (null !== $user) {
                    return $user;
                }

                $email = (string) ($googleUser->getEmail() ?? '');
                $user = (new User())
                    ->setGoogleId((string) $googleUser->getId())
                    ->setEmail('' !== $email ? $email : $googleUser->getId().'@google.local')
                    ->setDisplayName((string) ($googleUser->getName() ?? $email))
                    ->setAvatarUrl($googleUser->getAvatar());

                $this->em->persist($user);
                $this->em->flush();

                return $user;
            }),
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return new RedirectResponse($this->router->generate('app_home'));
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        $request->getSession()->getFlashBag()->add('error', 'Connexion Google impossible (accès refusé ou identifiants invalides).');

        return new RedirectResponse($this->router->generate('app_login'));
    }

    /** Visiteur non connecté : redirection vers la page de connexion. */
    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        return new RedirectResponse($this->router->generate('app_login'));
    }

    private function getClient(): \KnpU\OAuth2ClientBundle\Client\OAuth2ClientInterface
    {
        return $this->clients->getClient('google');
    }
}

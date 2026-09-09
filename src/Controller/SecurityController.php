<?php

namespace App\Controller;

use App\Repository\ArticleRepository;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SecurityController extends AbstractController
{
    #[Route('/login', name: 'app_login')]
    public function login(AuthenticationUtils $authUtils, ArticleRepository $articles): Response
    {
        if ($this->isGranted('ROLE_USER')) {
            return $this->redirectToRoute('app_home');
        }

        return $this->render('security/login.html.twig', [
            'lastError' => $authUtils->getLastAuthenticationError(),
            'googleConfigured' => '' !== trim((string) ($_ENV['GOOGLE_CLIENT_ID'] ?? $_SERVER['GOOGLE_CLIENT_ID'] ?? '')),
            // Aperçu public du fil (flouté et non cliquable dans le template).
            'previewArticles' => $articles->findRecent(6),
        ]);
    }

    #[Route('/connect/google', name: 'connect_google')]
    public function connect(ClientRegistry $clients): Response
    {
        try {
            return $clients->getClient('google')->redirect();
        } catch (\Throwable) {
            $this->addFlash('error', 'Google n’est pas configuré : renseignez GOOGLE_CLIENT_ID et GOOGLE_CLIENT_SECRET.');

            return $this->redirectToRoute('app_login');
        }
    }

    /** Retour de Google : intercepté par le firewall (GoogleAuthenticator). */
    #[Route('/connect/google/check', name: 'connect_google_check')]
    public function check(): Response
    {
        throw new \LogicException('Cette route est gérée par le firewall.');
    }

    /** Intercepté par le firewall (déconnexion). */
    #[Route('/logout', name: 'app_logout')]
    public function logout(): void
    {
        throw new \LogicException('Cette route est gérée par le firewall.');
    }
}

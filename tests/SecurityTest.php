<?php

namespace App\Tests;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class SecurityTest extends WebTestCase
{
    use TestAuthTrait;

    public function testAnonymousHomeIsPublic(): void
    {
        $client = static::createClient();
        $client->request('GET', '/');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('a.login-link');
    }

    public function testAnonymousNotificationsRequireLogin(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/notifications');

        $this->assertResponseRedirects('/login');
    }

    public function testLoginPageIsPublicWithGoogleButton(): void
    {
        $client = static::createClient();
        $client->request('GET', '/login');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('a.google-btn[href="/connect/google"]');
        // Aperçu flouté et non cliquable du site derrière la carte.
        $this->assertSelectorExists('#loginPreview[inert]');
    }

    public function testConnectRedirectsToGoogle(): void
    {
        $client = static::createClient();
        $client->request('GET', '/connect/google');

        $this->assertResponseRedirects();
        $location = $client->getResponse()->headers->get('Location');
        $this->assertStringStartsWith('https://accounts.google.com/o/oauth2/', (string) $location);
        $this->assertStringContainsString('client_id=test-client-id', (string) $location);
    }

    public function testAuthenticatedUserReachesHome(): void
    {
        $client = $this->createAuthenticatedClient('Marie L.');
        $client->request('GET', '/');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Marie');
    }

    public function testLogoutRedirectsToLogin(): void
    {
        $client = $this->createAuthenticatedClient();
        $client->request('GET', '/logout');

        $this->assertResponseRedirects('/login');
        $client->followRedirect();
        $this->assertSelectorExists('a.google-btn');
    }
}

<?php

namespace App\Tests;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/** Crée un compte Google de test et retourne un client déjà connecté (connexion obligatoire). */
trait TestAuthTrait
{
    protected function createNotificationFixture(): void
    {
        $em = static::getContainer()->get('doctrine')->getManager();
        $article = $em->getRepository(\App\Entity\Article::class)->findOneBy(['slug' => 'notification-flow-fixture']) ?? new \App\Entity\Article();
        $article->setSlug('notification-flow-fixture')->setTitle('Actualité pour tester les notifications')
            ->setExcerpt('Une actualité de test, uniquement dans la base de tests.')
            ->setSource($em->getRepository(\App\Entity\Source::class)->findOneBy(['slug' => 'linfo-re']))
            ->setCategory('La Réunion')->setPlace('La Réunion')->setImportant(true)->setDemo(false)
            ->setSourceUrl('https://example.com/notification')->setPublishedAt(new \DateTimeImmutable());
        $em->persist($article); $em->flush();
    }

    protected function removeNotificationFixture(): void
    {
        $em = static::getContainer()->get('doctrine')->getManager();
        $article = $em->getRepository(\App\Entity\Article::class)->findOneBy(['slug' => 'notification-flow-fixture']);
        if ($article) {
            foreach ($em->getRepository(\App\Entity\Notification::class)->findBy(['article' => $article]) as $notification) { $em->remove($notification); }
            $em->remove($article); $em->flush();
        }
    }

    protected function createAuthenticatedClient(string $displayName = 'Test User'): KernelBrowser
    {
        return $this->createAuthenticatedClientWithUser($displayName)[0];
    }

    /** @return array{0: KernelBrowser, 1: User} */
    protected function createAuthenticatedClientWithUser(string $displayName = 'Test User'): array
    {
        $client = static::createClient();
        $em = $client->getContainer()->get('doctrine')->getManager();
        $user = (new User())
            ->setGoogleId('test-'.bin2hex(random_bytes(8)))
            ->setEmail(sprintf('test-%s@example.com', bin2hex(random_bytes(8))))
            ->setDisplayName($displayName);
        $em->persist($user);
        $em->flush();
        $client->loginUser($user);
        $page = $client->request('GET', '/');
        $client->setServerParameter('HTTP_X_CSRF_TOKEN', $page->filter('meta[name="csrf-token"]')->attr('content'));

        return [$client, $user];
    }
}

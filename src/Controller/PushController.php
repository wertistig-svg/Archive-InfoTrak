<?php

namespace App\Controller;

use App\Service\PushService;
use App\Service\ReaderIdentity;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PushController extends AbstractController
{
    #[Route('/notifications-telephone', name: 'app_push', methods: ['GET'])]
    public function page(): Response { return $this->render('account/push.html.twig'); }

    #[Route('/api/push/key', methods: ['GET'])]
    public function key(PushService $push): JsonResponse { return $this->json(['publicKey' => $push->keys()['publicKey']]); }

    #[Route('/api/push/subscribe', methods: ['POST'])]
    public function subscribe(Request $request, PushService $push, ReaderIdentity $reader): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (!is_array($data) || strlen($request->getContent()) > 4096) { return $this->json(['error' => 'Abonnement invalide.'], 422); }
        try { $push->subscribe($reader->ownerKey(), $data); }
        catch (\InvalidArgumentException $e) { return $this->json(['error' => $e->getMessage()], 422); }
        return $this->json(['ok' => true]);
    }

    #[Route('/api/push/unsubscribe', methods: ['POST'])]
    public function unsubscribe(Request $request, PushService $push, ReaderIdentity $reader): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (!is_string($data['endpoint'] ?? null)) { return $this->json(['error' => 'Abonnement invalide.'], 422); }
        $push->unsubscribe($reader->ownerKey(), $data['endpoint']);
        return $this->json(['ok' => true]);
    }

    #[Route('/api/push/test', methods: ['POST'])]
    public function test(Request $request, PushService $push, ReaderIdentity $reader, Connection $db): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (!is_string($data['endpoint'] ?? null)) { return $this->json(['error' => 'Abonnement invalide.'], 422); }
        $row = $db->fetchAssociative('SELECT * FROM push_subscription WHERE owner_key = ? AND endpoint_hash = ?', [$reader->ownerKey(), hash('sha256', $data['endpoint'])]);
        if (!$row) { return $this->json(['error' => 'Activez les notifications sur cet appareil.'], 404); }
        // Limite aussi les essais, sans permettre de spammer le fournisseur push.
        if ($request->getSession()->get('push_test_at', 0) > time() - 60) { return $this->json(['error' => 'Attendez une minute avant un nouvel essai.'], 429); }
        $request->getSession()->set('push_test_at', time());
        $result = $push->send(json_decode($row['subscription'], true), ['title' => 'InfoTrak est prêt', 'body' => 'Les nouvelles actualités de vos sujets pourront arriver sur cet appareil.', 'url' => '/', 'tag' => 'infotrak-test']);
        if ($result === 'expired') { $db->delete('push_subscription', ['id' => $row['id']]); }
        return $this->json($result === 'sent' ? ['ok' => true] : ['error' => 'Envoi refusé. Désactivez puis réactivez les notifications.'], $result === 'sent' ? 200 : 502);
    }
}

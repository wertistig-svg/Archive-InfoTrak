<?php

namespace App\Controller;

use App\Repository\NotificationRepository;
use App\Service\NotificationService;
use App\Service\PreferenceService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/notifications')]
class NotificationController extends AbstractController
{
    #[Route('', name: 'api_notifications_list', methods: ['GET'])]
    public function list(NotificationRepository $notifications, NotificationService $generator, PreferenceService $preferences): JsonResponse
    {
        $owner = $this->getUser()->getUserIdentifier();
        $generator->generateForRecent($preferences->getOrCreate($owner));
        $items = array_map(
            fn ($n) => [
                'id' => $n->getId(),
                'title' => $n->getTitle(),
                'message' => $n->getMessage(),
                'category' => $n->getCategory(),
                'source' => $n->getArticle()?->getSource()?->getName(),
                'level' => $n->getLevel(),
                'isRead' => $n->isRead(),
                'articleId' => $n->getArticle()?->getId(),
                'link' => null !== $n->getArticle()
                    ? $this->generateUrl('app_article_show', ['slug' => $n->getArticle()->getSlug()])
                    : null,
                'createdAt' => $n->getCreatedAt()->format(\DateTimeInterface::ATOM),
            ],
            $notifications->findLatest($owner, 20),
        );

        return $this->json(['items' => $items, 'unread' => $notifications->countUnread($owner)]);
    }

    #[Route('/{id}/read', name: 'api_notifications_read', methods: ['POST'])]
    public function markRead(int $id, NotificationRepository $notifications, EntityManagerInterface $em): JsonResponse
    {
        $owner = $this->getUser()->getUserIdentifier();
        $notification = $notifications->findOneBy(['id' => $id, 'ownerKey' => $owner]);
        if (null === $notification) {
            return $this->json(['error' => 'Notification introuvable.'], 404);
        }

        $notification->markAsRead();
        $em->flush();

        return $this->json(['ok' => true, 'unread' => $notifications->countUnread($owner)]);
    }

    #[Route('/read-all', name: 'api_notifications_read_all', methods: ['POST'])]
    public function markAllRead(NotificationRepository $notifications, EntityManagerInterface $em): JsonResponse
    {
        foreach ($notifications->findBy(['isRead' => false, 'ownerKey' => $this->getUser()->getUserIdentifier()]) as $notification) {
            $notification->markAsRead();
        }
        $em->flush();

        return $this->json(['ok' => true, 'unread' => 0]);
    }
}

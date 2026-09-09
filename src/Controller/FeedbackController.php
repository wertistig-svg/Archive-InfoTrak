<?php

namespace App\Controller;

use App\Entity\ArticleFeedback;
use App\Entity\Notification;
use App\Repository\ArticleFeedbackRepository;
use App\Repository\ArticleRepository;
use App\Service\ReaderIdentity;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/feedback')]
class FeedbackController extends AbstractController
{
    #[Route('', name: 'api_feedback_save', methods: ['POST'])]
    public function save(
        Request $request,
        ArticleRepository $articles,
        ArticleFeedbackRepository $feedbacks,
        EntityManagerInterface $em,
        ReaderIdentity $reader,
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        if (!is_array($data) || !is_int($data['articleId'] ?? null) || !is_bool($data['interested'] ?? null)) {
            return $this->json(['error' => 'Un identifiant et un choix oui/non sont requis.'], 422);
        }
        $articleId = (int) ($data['articleId'] ?? 0);
        $interested = (bool) ($data['interested'] ?? true);

        $article = $articles->find($articleId);
        if (null === $article) {
            return $this->json(['error' => 'Article introuvable.'], 404);
        }

        $owner = $reader->ownerKey();
        $feedback = $feedbacks->findOneBy(['article' => $article, 'ownerKey' => $owner])
            ?? (new ArticleFeedback())->setArticle($article)->setOwnerKey($owner);
        $feedback->setInterested($interested);

        if (!$interested) {
            foreach ($em->getRepository(Notification::class)->findBy(['article' => $article, 'ownerKey' => $owner]) as $notification) {
                $notification->markAsRead();
            }
        }

        $em->persist($feedback);
        $em->flush();

        return $this->json(['ok' => true, 'articleId' => $articleId, 'interested' => $interested]);
    }
}

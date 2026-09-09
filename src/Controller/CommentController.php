<?php

namespace App\Controller;

use App\Entity\Comment;
use App\Repository\ArticleRepository;
use App\Repository\CommentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/articles/{id}/comments')]
class CommentController extends AbstractController
{
    #[Route('', name: 'api_comments_list', methods: ['GET'])]
    public function list(int $id, ArticleRepository $articles, CommentRepository $comments): JsonResponse
    {
        $article = $articles->find($id);
        if (null === $article) {
            return $this->json(['error' => 'Article introuvable.'], 404);
        }

        $owner = $this->getUser()?->getUserIdentifier();
        $items = array_map(
            static fn (Comment $c) => [
                'id' => $c->getId(),
                'displayName' => $c->getDisplayName(),
                'initials' => $c->getInitials(),
                'content' => $c->getContent(),
                'createdAt' => $c->getCreatedAt()->format(\DateTimeInterface::ATOM),
                'mine' => $c->getOwnerKey() === $owner,
            ],
            $comments->findForArticle($article),
        );

        return $this->json(['items' => $items, 'total' => \count($items)]);
    }

    #[Route('', name: 'api_comments_post', methods: ['POST'])]
    public function post(
        int $id,
        Request $request,
        ArticleRepository $articles,
        EntityManagerInterface $em,
    ): JsonResponse {
        $article = $articles->find($id);
        if (null === $article) {
            return $this->json(['error' => 'Article introuvable.'], 404);
        }

        $data = json_decode($request->getContent(), true);
        if (!is_array($data) || !is_string($data['content'] ?? null)) {
            return $this->json(['error' => 'Le commentaire doit être un texte.'], 422);
        }
        $content = trim((string) ($data['content'] ?? ''));
        if ('' === $content) {
            return $this->json(['error' => 'Le commentaire est vide.'], 422);
        }
        if (mb_strlen($content) > 500) {
            return $this->json(['error' => '500 lettres maximum.'], 422);
        }

        $user = $this->getUser();
        $comment = (new Comment())
            ->setArticle($article)
            ->setOwnerKey($user->getUserIdentifier())
            ->setDisplayName(method_exists($user, 'getDisplayName') ? (string) $user->getDisplayName() : 'Anonyme')
            ->setContent($content);
        $em->persist($comment);
        $em->flush();

        return $this->json([
            'ok' => true,
            'item' => [
                'id' => $comment->getId(),
                'displayName' => $comment->getDisplayName(),
                'initials' => $comment->getInitials(),
                'content' => $comment->getContent(),
                'createdAt' => $comment->getCreatedAt()->format(\DateTimeInterface::ATOM),
                'mine' => true,
            ],
        ], 201);
    }

    #[Route('/{commentId}', name: 'api_comments_delete', methods: ['DELETE'])]
    public function delete(int $id, int $commentId, ArticleRepository $articles, CommentRepository $comments, EntityManagerInterface $em): JsonResponse
    {
        $comment = $comments->find($commentId);
        if (null === $comment || $comment->getArticle()?->getId() !== $id) {
            return $this->json(['error' => 'Commentaire introuvable.'], 404);
        }
        if ($comment->getOwnerKey() !== $this->getUser()->getUserIdentifier()) {
            return $this->json(['error' => 'Vous ne pouvez supprimer que vos commentaires.'], 403);
        }

        $em->remove($comment);
        $em->flush();

        return $this->json(['ok' => true]);
    }
}

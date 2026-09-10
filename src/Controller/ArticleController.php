<?php

namespace App\Controller;

use App\Repository\ArticleFeedbackRepository;
use App\Repository\ArticleRepository;
use App\InfoTrak\Catalog;
use App\Service\ReaderIdentity;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ArticleController extends AbstractController
{
    #[Route('/article/{slug}', name: 'app_article_show')]
    public function show(
        string $slug,
        ArticleRepository $articles,
        ArticleFeedbackRepository $feedbacks,
        EntityManagerInterface $em,
        ReaderIdentity $reader,
    ): Response {
        $article = $articles->findOneBy(['slug' => $slug]);
        if (null === $article) {
            throw $this->createNotFoundException('Article introuvable.');
        }
        if (!in_array($article->getPlace(), Catalog::ZONES, true)) {
            throw $this->createNotFoundException('Cette actualité ne fait pas partie de la couverture InfoTrak.');
        }

        $article->incrementViews();
        $em->flush();

        $owner = $reader->ownerKey();
        $vote = $feedbacks->findOneBy(['article' => $article, 'ownerKey' => $owner]);

        return $this->render('article/show.html.twig', [
            'article' => $article,
            'related' => $articles->findRelated($article->getCategory(), $article->getId(), 3),
            'likesCount' => $feedbacks->countInterested($article->getId()),
            'userLiked' => null !== $vote && $vote->isInterested(),
        ]);
    }
}

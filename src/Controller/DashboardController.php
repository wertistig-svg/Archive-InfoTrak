<?php

namespace App\Controller;

use App\Repository\ArticleRepository;
use App\Repository\NotificationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/** Onglet « InfoTrak » : les indicateurs sortis du fil d'accueil. */
class DashboardController extends AbstractController
{
    #[Route('/infotrak', name: 'app_infotrak')]
    public function index(ArticleRepository $articles, NotificationRepository $notifications): Response
    {
        $total = $articles->countCovered();
        $now = new \DateTimeImmutable();

        return $this->render('infotrak/dashboard.html.twig', [
            'total' => $total,
            'byCategory' => $articles->countByCategory(false),
            'byPlace' => $articles->countByPlace(false),
            'stats' => $articles->collectionStats($now->modify('-7 days'), $now),
            'latestArticle' => $articles->findLatestCovered(),
            'unreadCount' => $this->getUser() ? $notifications->countUnread($this->getUser()->getUserIdentifier()) : 0,
            'demoCount' => $articles->countCovered(true) - $total,
        ]);
    }
}

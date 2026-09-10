<?php

namespace App\Controller;

use App\InfoTrak\Catalog;
use App\Repository\ArticleFeedbackRepository;
use App\Repository\ArticleRepository;
use App\Repository\NotificationRepository;
use App\Repository\SourceRepository;
use App\Service\PreferenceService;
use App\Service\ReaderIdentity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home', methods: ['GET'])]
    public function index(
        ArticleRepository $articles,
        ArticleFeedbackRepository $feedbacks,
        NotificationRepository $notifications,
        PreferenceService $preferences,
        ReaderIdentity $reader,
        Request $request,
        SourceRepository $sources,
    ): Response {
        // Données personnelles : chaque compte Google a ses préférences et ses votes.
        $owner = $reader->ownerKey();
        $preference = $preferences->getOrCreate($owner);
        $feedbackMap = $feedbacks->findMapForOwner($owner);

        $topic = $request->query->getString('topic');
        $zone = $request->query->getString('zone');
        $topic = in_array($topic, Catalog::TOPICS, true) ? $topic : '';
        $zone = in_array($zone, Catalog::ZONES, true) ? $zone : '';
        $query = mb_substr(trim($request->query->getString('q')), 0, 120);
        $explore = 'all' === $request->query->getString('view');
        $demo = $request->query->getBoolean('demo');
        $result = $articles->feedPage(
            $topic ? [$topic] : ($explore ? [] : $preference->getTopics()),
            $zone ? [$zone] : ($explore ? [] : $preference->getZones()),
            array_keys(array_filter($feedbackMap, static fn ($value) => false === $value)),
            $query, $request->query->getInt('page', 1), $demo,
        );
        $date = (new \IntlDateFormatter('fr_FR', \IntlDateFormatter::FULL, \IntlDateFormatter::NONE,
            'Indian/Reunion', null, 'EEEE d MMMM y'))->format(new \DateTimeImmutable());

        return $this->render('home/index.html.twig', [
            'preference' => $preference,
            'topics' => Catalog::TOPICS,
            'sidebarTopics' => [] !== $preference->getTopics() ? array_slice($preference->getTopics(), 0, 5) : array_slice(Catalog::TOPICS, 0, 5),
            'networks' => \App\InfoTrak\CommunityCatalog::NETWORKS,
            'zones' => Catalog::ZONES,
            'featured' => $result['items'][0] ?? null,
            'sideArticles' => array_slice($result['items'], 1, 3),
            'feedArticles' => array_slice($result['items'], 4),
            'feedbackMap' => $feedbackMap,
            'notifications' => $this->getUser() ? $notifications->findLatest($owner, 10) : [],
            'unreadCount' => $this->getUser() ? $notifications->countUnread($owner) : 0,
            'result' => $result, 'activeTopic' => $topic, 'activeZone' => $zone, 'query' => $query,
            'explore' => $explore, 'includeDemo' => $demo, 'todayLabel' => $date,
            'sourceList' => $sources->findWithWebsite(),
            'latestArticle' => $articles->findLatestCovered(),
            'demoCount' => $articles->countCovered(true) - $articles->countCovered(),
            'filters' => array_filter(['topic' => $topic, 'zone' => $zone, 'q' => $query, 'view' => $explore ? 'all' : '', 'demo' => $demo ? 1 : '']),
        ]);
    }
}

<?php

namespace App\Controller;

use App\InfoTrak\CommunityCatalog;
use App\Repository\ArticleRepository;
use App\Repository\ArticleFeedbackRepository;
use App\Service\CommunityService;
use App\Service\ReaderIdentity;
use App\Service\SocialImportService;
use App\Service\PreferenceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class CommunityController extends AbstractController
{
    #[Route('/communautes', name: 'app_communities', methods: ['GET'])]
    public function index(Request $request, ArticleRepository $articles, ArticleFeedbackRepository $feedback, ReaderIdentity $reader, CommunityService $service, SocialImportService $socialImport, PreferenceService $preferences): Response
    {
        $rawNetworks = $request->query->all()['networks'] ?? [];
        if (!is_array($rawNetworks) || count(array_filter($rawNetworks, 'is_string')) !== count($rawNetworks)) { throw $this->createNotFoundException('Sélection de réseaux invalide.'); }
        $selected = array_values(array_intersect(array_keys(CommunityCatalog::NETWORKS), $rawNetworks));
        $community = $request->query->getString('community');
        $community = isset(CommunityCatalog::COMMUNITIES[$community]) ? $community : '';
        $query = mb_substr(trim($request->query->getString('q')), 0, 80);
        $until = new \DateTimeImmutable();
        $since = $until->modify('-7 days');
        $ownerKey = $reader->ownerKey();
        $feedbackMap = $feedback->findMapForOwner($ownerKey);
        $excluded = array_keys(array_filter($feedbackMap, static fn ($value) => false === $value));
        $sample = $articles->socialWindow($since, $until, $excluded);
        $data = $service->summarize($sample, $selected, $community, $query, $since, $until);
        $pages = max(1, (int) ceil($data['total'] / 12));
        $page = max(1, min($pages, $request->query->getInt('page', 1)));
        $data['posts'] = array_slice($data['posts'], ($page - 1) * 12, 12);
        $search = $query ?: (CommunityCatalog::COMMUNITIES[$community]['label'] ?? 'actualité');
        $encoded = rawurlencode($search);
        $links = [
            'x' => 'https://x.com/search?q='.$encoded.'&f=live',
            'instagram' => 'https://www.instagram.com/explore/search/',
            'facebook' => 'https://www.facebook.com/search/posts/?q='.$encoded,
            'reddit' => 'https://www.reddit.com/search/?q='.$encoded.'&sort=new',
            'threads' => 'https://www.threads.com/search?q='.$encoded,
            'tiktok' => 'https://www.tiktok.com/search?q='.$encoded,
        ];
        return $this->render('communities/index.html.twig', $data + [
            'networks' => CommunityCatalog::NETWORKS, 'communities' => CommunityCatalog::COMMUNITIES,
            'selectedNetworks' => $selected, 'activeCommunity' => $community, 'query' => $query,
            'filters' => array_filter(['networks' => $selected, 'community' => $community, 'q' => $query]),
            'page' => $page, 'pages' => $pages, 'since' => $since, 'until' => $until,
            'sampleSize' => count($sample), 'externalLinks' => $links, 'searchTerm' => $search,
            'connectorStatuses' => $socialImport->statuses(),
            'suggestedTopic' => CommunityCatalog::suggestionFor($preferences->getOrCreate($ownerKey)->getTopics(), $ownerKey),
        ]);
    }
}

<?php

namespace App\Controller;

use App\Search\LiveSearchService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class SearchController extends AbstractController
{
    #[Route('/api/search', name: 'api_search', methods: ['GET'])]
    public function search(Request $request, LiveSearchService $live): JsonResponse
    {
        $query = trim((string) $request->query->get('q', ''));
        if (mb_strlen($query) < 3) {
            return $this->json(['error' => 'Tapez au moins 3 lettres pour lancer la recherche en direct.'], 422);
        }
        if (mb_strlen($query) > 120) {
            return $this->json(['error' => 'Recherche trop longue (120 lettres max).'], 422);
        }

        return $this->json(['query' => $query, 'results' => $live->search($query)]);
    }
}

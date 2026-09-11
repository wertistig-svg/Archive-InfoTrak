<?php
namespace App\Controller;

use App\News\TrafficInfo;
use App\Repository\ArticleRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class TrafficController extends AbstractController
{
    #[Route('/circulation', name: 'app_traffic', methods: ['GET'])]
    public function index(ArticleRepository $articles, Request $request): Response
    {
        $items = array_map(TrafficInfo::details(...), $articles->findTraffic());
        $kind = $request->query->getString('type');
        if (!in_array($kind, ['Accident','Embouteillages','Travaux','Circulation'], true)) { $kind = ''; }
        if ($kind) { $items = array_values(array_filter($items, static fn ($i) => $i['kind'] === $kind)); }
        $pages = max(1, (int) ceil(count($items) / 12)); $page = max(1, min($pages, $request->query->getInt('page', 1)));
        return $this->render('traffic/index.html.twig', ['reports'=>array_slice($items, ($page-1)*12, 12), 'total'=>count($items), 'kind'=>$kind,'page'=>$page,'pages'=>$pages]);
    }
}

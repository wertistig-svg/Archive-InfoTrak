<?php
namespace App\Controller;

use App\InfoTrak\PublisherCatalog;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class SourcesController extends AbstractController
{
    #[Route('/sources', name: 'app_sources', methods: ['GET'])]
    public function index(Connection $db): Response
    {
        $counts = [];
        foreach ($db->fetchAllAssociative("SELECT s.name, COUNT(a.id) AS total FROM source s JOIN article a ON a.source_id=s.id WHERE a.is_demo=false AND a.place IN ('La Réunion','France') GROUP BY s.name") as $row) {
            $key = PublisherCatalog::websiteFor($row['name']) ?? ''; $counts[$key] = ($counts[$key] ?? 0) + (int) $row['total'];
        }
        return $this->render('sources/index.html.twig', ['groups'=>PublisherCatalog::GROUPS,'sources'=>PublisherCatalog::SOURCES,'counts'=>$counts]);
    }
}

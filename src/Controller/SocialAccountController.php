<?php

namespace App\Controller;

use App\InfoTrak\CommunityCatalog;
use App\Service\SocialImportService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class SocialAccountController extends AbstractController
{
    #[Route('/compte/reseaux', name: 'app_account_social', methods: ['GET'])]
    public function index(SocialImportService $socialImport): Response
    {
        return $this->render('account/social.html.twig', [
            'networks' => CommunityCatalog::NETWORKS,
            'connectorStatuses' => $socialImport->statuses(),
        ]);
    }
}

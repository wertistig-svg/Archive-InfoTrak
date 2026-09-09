<?php

namespace App\Controller;

use App\Entity\Preference;
use App\Service\NotificationService;
use App\Service\PreferenceService;
use App\Service\ReaderIdentity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/preferences')]
class PreferenceController extends AbstractController
{
    #[Route('', name: 'app_preferences_show', methods: ['GET'])]
    public function show(PreferenceService $preferences, ReaderIdentity $reader): JsonResponse
    {
        $preference = $preferences->getOrCreate($reader->ownerKey());

        return $this->json([
            'topics' => $preference->getTopics(),
            'zones' => $preference->getZones(),
            'frequency' => $preference->getFrequency(),
        ]);
    }

    #[Route('', name: 'app_preferences_update', methods: ['POST'])]
    public function update(
        Request $request,
        PreferenceService $preferences,
        NotificationService $notifications,
        ReaderIdentity $reader,
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        if (!\is_array($data)) {
            return $this->json(['error' => 'JSON invalide.'], 400);
        }

        $topics = $data['topics'] ?? [];
        $zones = $data['zones'] ?? [];
        $frequency = $data['frequency'] ?? Preference::FREQUENCY_IMPORTANT;
        if (!is_array($topics) || !is_array($zones) || !is_string($frequency)
            || count(array_filter($topics, 'is_string')) !== count($topics)
            || count(array_filter($zones, 'is_string')) !== count($zones)) {
            return $this->json(['error' => 'Sujets et zones doivent être des listes de textes.'], 422);
        }

        if (!isset(Preference::FREQUENCIES[$frequency])) {
            return $this->json(['error' => 'Fréquence inconnue.'], 422);
        }
        if (Preference::FREQUENCY_DAILY === $frequency) {
            return $this->json(['error' => 'Le résumé quotidien est en préparation. Choisissez un autre rythme.'], 422);
        }

        $preference = $preferences->getOrCreate($reader->ownerKey());
        $preferences->update($preference, $topics, $zones, $frequency);

        $generated = $this->getUser() ? $notifications->generateForRecent($preference) : 0;

        return $this->json([
            'ok' => true,
            'topics' => $preference->getTopics(),
            'zones' => $preference->getZones(),
            'frequency' => $preference->getFrequency(),
            'notificationsGenerated' => $generated,
        ]);
    }
}

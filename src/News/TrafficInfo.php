<?php
namespace App\News;

use App\Entity\Article;

final class TrafficInfo
{
    public static function isTraffic(string $title): bool
    {
        $s = FeedClassifier::normalize($title);
        if (preg_match('/\b(embouteillages?|bouchons?|deviations?|inforoute|info route|circulation|route du littoral|route des tamarins)\b/', $s)) { return true; }
        return (bool) (preg_match('/\b(accidents?|collisions?|choc frontal|fermee?|fermeture|travaux|eboulis|basculee?|coupure|ralentissement|blesse|morts?|tues?)\b/', $s)
            && preg_match('/\b(routes?|routiers?|rn\s?\d+|rd\s?\d+|motos?|voitures?|vehicules?|camions?|motards?|pietons?|cyclistes?|deux roues)\b/', $s));
    }

    public static function details(Article $article): array
    {
        $title = $article->getDisplayTitle(); $text = $title.' '.$article->getReadingSummary();
        preg_match_all('/\b(?:R[NDT]\s?\d+|Saint-(?:Denis|Paul|Pierre|Benoît|André|Louis|Joseph|Philippe|Leu)|Sainte-(?:Marie|Suzanne|Rose)|Tampon|Cilaos|Salazie|La Possession|Le Port|Étang-Salé|route du Littoral|route des Tamarins|route de la Montagne)\b/iu', $text, $places);
        $normalized = FeedClassifier::normalize($title);
        $kind = preg_match('/accidents?|collisions?|choc frontal/', $normalized) ? 'Accident' : (preg_match('/embouteillage|bouchon|ralentissement/', $normalized) ? 'Embouteillages' : (str_contains($normalized, 'travaux') ? 'Travaux' : 'Circulation'));
        preg_match('/[^.!?]*(?:en raison de|à cause de|à la suite de|pour permettre)[^.!?]*[.!?]/iu', $article->getReadingSummary(), $cause);
        return ['article'=>$article,'kind'=>$kind,'where'=>implode(' · ', array_unique($places[0])) ?: 'Secteur à consulter dans la source',
            'cause'=>trim($cause[0] ?? '') ?: 'Non précisée dans l’extrait disponible.'];
    }
}

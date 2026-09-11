<?php

namespace App\News;

/** Classement déterministe d'un titre vers une catégorie et une zone InfoTrak. */
final class FeedClassifier
{
    private const CATEGORY_KEYWORDS = [
        'Cybersécurité' => ['cyber', 'arnaque', 'phishing', 'hamecon', 'ransomware', 'piratage', 'pirate informatique', 'hacker', 'donnees personnelles', 'cnil', 'malware', 'rancongiciel', 'escroquerie en ligne', 'cyberattaque'],
        'Emploi' => ['emploi', 'offre', 'recrutement', 'recrute', 'embauche', 'chomage', 'france travail', 'carriere', 'apprentissage', 'interim', 'metier'],
        'Jeux' => ['jeux video', 'jeu video', 'gaming', 'esport', 'playstation', 'nintendo', 'xbox'],
        'Streaming & créateurs' => ['zevent', 'z event', 'streamer', 'streameuse', 'twitch', 'youtubeur', 'youtubeuse', 'createur de contenu'],
        'Tech & IA' => ['intelligence artificielle', 'chatgpt', 'robotique', 'smartphone', 'processeur'],
        'Sciences' => ['astronomie', 'spatial', 'nasa', 'physique quantique', 'decouverte scientifique'],
        'Santé' => ['sante', 'hopital', 'cancer', 'medecin', 'vaccin', 'gerontopole'],
        'Sport' => ['jeux olympiques', 'tour cycliste', 'football', 'basket', 'tennis', 'rugby', 'sport', 'sportif', 'sportive', 'base jump'],
        'Cinéma & séries' => ['cinema', 'film', 'netflix', 'serie televisee', 'manga', 'anime'],
        'Musique' => ['musique', 'concert', 'chanteur', 'chanteuse', 'album musical'],
        'Économie' => ['economie', 'inflation', 'pouvoir d achat', 'entreprise', 'bourse'],
        'Politique' => ['presidentielle', 'legislatives', 'election', 'municipales', 'gouvernement'],
        'Solidarité' => ['solidarite', 'association caritative', 'collecte de dons', 'benevolat'],
        'Environnement' => ['climat', 'environnement', 'ecologie', 'lagon', 'recif', 'corail', 'cyclone', 'biodiversite', 'energie', 'pollution', 'dechet', 'volcan', 'eruption', 'inondation', 'requin', 'leptospirose', 'secheresse', 'eau potable'],
    ];

    private const PLACE_KEYWORDS = [
        'Chine' => ['chine', 'chinois', 'pekin', 'shanghai'],
        'Japon' => ['japon', 'tokyo'],
        'Asie' => ['asie', 'asiatique', 'singapour', 'indonesie', 'malaisie', 'vietnam', 'thailande', 'coree', 'taiwan', 'jakarta'],
        'International' => ['international', 'mondial', 'europe', 'amerique', 'afrique', 'maurice', 'madagascar', 'seychelles', 'comores', 'inde', 'onu'],
        'France' => ['france', 'francais', 'paris'],
    ];

    private const REUNION_KEYWORDS = ['reunion', 'reunionnais', 'reunionnaise', 'zinfos', 'linfo', 'clicanoo', 'imaz', 'temoignages', 'quotidien', 'saint-denis', 'saint-pierre', 'saint-paul', 'saint-andre', 'saint-benoit', 'saint-louis', 'saint-joseph', 'sainte-marie', 'sainte-suzanne', 'sainte-rose', 'saint-philippe', 'tampon', 'cilaos', 'salazie', 'mafate', 'possession', 'portois', 'dionysien', '974', 'barachois'];

    private function __construct() {}

    public static function categoryFor(string $title, string $default): string
    {
        if (TrafficInfo::isTraffic($title)) { return 'La Circulation'; }
        $text = self::normalize($title);
        foreach (self::CATEGORY_KEYWORDS as $category => $keywords) {
            foreach ($keywords as $keyword) {
                $matches = in_array($category, ['Cybersécurité', 'Emploi', 'Jeux', 'Environnement'], true)
                    ? str_contains($text, self::normalizeKeyword($keyword))
                    : (bool) preg_match('/ '.preg_quote(self::normalizeKeyword($keyword), '/').'(?:s|x)? /', $text);
                if ($matches) {
                    return $category;
                }
            }
        }

        return $default;
    }

    public static function placeFor(string $title, string $default): string
    {
        $text = self::normalize($title);
        foreach (self::REUNION_KEYWORDS as $keyword) {
            if (self::containsWord($text, $keyword)) {
                return 'La Réunion';
            }
        }
        // La zone décrit désormais la couverture du flux : uniquement Réunion ou France.
        if (in_array($default, ['La Réunion', 'France'], true)) {
            return $default;
        }
        foreach (self::PLACE_KEYWORDS as $place => $keywords) {
            foreach ($keywords as $keyword) {
                if (self::containsWord($text, $keyword)) {
                    return $place;
                }
            }
        }
        return 'France';
    }

    public static function normalize(string $text): string
    {
        $transliterator = \Transliterator::create('Any-Latin; Latin-ASCII; Lower');
        $normalized = null !== $transliterator ? $transliterator->transliterate($text) : mb_strtolower($text);

        return ' '.preg_replace('/[^a-z0-9]+/', ' ', (string) $normalized).' ';
    }

    private static function containsWord(string $haystack, string $needle): bool
    {
        return str_contains($haystack, ' '.self::normalizeKeyword($needle).' ');
    }

    private static function normalizeKeyword(string $keyword): string
    {
        return trim((string) preg_replace('/[^a-z0-9]+/', ' ', mb_strtolower(trim($keyword))));
    }

    public static function slugify(string $title): string
    {
        $slug = trim((string) preg_replace('/[^a-z0-9]+/', '-', trim(self::normalize($title))), '-');
        if ('' === $slug) {
            $slug = 'article';
        }

        return mb_substr($slug, 0, 140);
    }

    public static function excerptOf(string $html, int $max = 300): string
    {
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = trim((string) preg_replace('/\s+/', ' ', $text));
        if (mb_strlen($text) > $max) {
            $text = mb_substr($text, 0, $max).'…';
        }

        return $text;
    }
}

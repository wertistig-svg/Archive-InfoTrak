<?php

namespace App\InfoTrak;

final class CommunityCatalog
{
    public const NETWORKS = ['x' => 'X / Twitter', 'instagram' => 'Instagram', 'facebook' => 'Facebook', 'reddit' => 'Reddit', 'threads' => 'Threads', 'tiktok' => 'TikTok'];
    public const COMMUNITIES = [
        'gaming' => ['label' => 'Gaming', 'terms' => ['gaming', 'jeu video', 'jeux video', 'playstation', 'nintendo', 'xbox']],
        'streaming' => ['label' => 'Streaming', 'terms' => ['twitch', 'streamer', 'streameuse', 'youtubeur']],
        'reunion' => ['label' => 'La Réunion', 'terms' => ['reunion', '974']],
        'cyber' => ['label' => 'Cybersécurité', 'terms' => ['cyber', 'phishing', 'ransomware', 'piratage']],
        'ia' => ['label' => 'Intelligence artificielle', 'terms' => ['intelligence artificielle', 'chatgpt', 'llm']],
        'sport' => ['label' => 'Sport', 'terms' => ['sport', 'football', 'rugby', 'tennis']],
        'musique' => ['label' => 'Musique', 'terms' => ['musique', 'concert', 'album']],
    ];

    private const SUGGESTIONS = [
        'La Réunion' => ['volcan à La Réunion', 'météo 974', 'actualité de Saint-Denis'],
        'Cybersécurité' => ['nouvelle cyberattaque', 'ransomware en France', 'arnaque par SMS'],
        'Emploi' => ['recrutement à La Réunion', 'alternance 974', 'nouveaux métiers'],
        'Jeux' => ['nouvelle sortie jeu vidéo', 'esport français', 'Nintendo'],
        'Environnement' => ['climat à La Réunion', 'protection de l’océan', 'énergies renouvelables'],
        'Tech & IA' => ['intelligence artificielle', 'nouveau modèle IA', 'robotique'],
        'Sciences' => ['découverte scientifique', 'espace et astronomie', 'recherche médicale'],
        'Santé' => ['prévention santé', 'hôpitaux à La Réunion', 'recherche médicale'],
        'Sport' => ['sport réunionnais', 'football français', 'trail à La Réunion'],
        'Cinéma & séries' => ['nouvelle série', 'sortie cinéma', 'film français'],
        'Musique' => ['concert à La Réunion', 'nouvel album', 'artistes réunionnais'],
        'Économie' => ['prix à La Réunion', 'entreprises locales', 'pouvoir d’achat'],
        'Politique' => ['actualité politique française', 'collectivités de La Réunion', 'Assemblée nationale'],
        'Streaming & créateurs' => ['streaming français', 'créateurs réunionnais', 'Twitch France'],
        'Solidarité' => ['association à La Réunion', 'appel aux dons', 'initiative solidaire'],
        'Société' => ['débat de société', 'vie quotidienne à La Réunion', 'éducation en France'],
    ];

    /** Une suggestion stable pour un lecteur, choisie parmi ses sujets enregistrés. */
    public static function suggestionFor(array $preferredTopics, string $ownerKey): string
    {
        $available = array_values(array_filter($preferredTopics, static fn ($topic) => isset(self::SUGGESTIONS[$topic])));
        if ([] === $available) { $available = ['La Réunion', 'Cybersécurité', 'Emploi', 'Jeux']; }
        $seed = hexdec(substr(hash('sha256', $ownerKey), 0, 7));
        $topic = $available[$seed % count($available)];
        $suggestions = self::SUGGESTIONS[$topic];

        return $suggestions[intdiv($seed, max(1, count($available))) % count($suggestions)];
    }

    public static function networkForUrl(?string $url): ?string
    {
        if (!in_array(strtolower((string) parse_url($url ?? '', PHP_URL_SCHEME)), ['http', 'https'], true)) { return null; }
        $host = strtolower((string) parse_url($url ?? '', PHP_URL_HOST));
        foreach (['x' => ['x.com', 'twitter.com'], 'instagram' => ['instagram.com'], 'facebook' => ['facebook.com', 'fb.watch'], 'reddit' => ['reddit.com', 'redd.it'], 'threads' => ['threads.net', 'threads.com'], 'tiktok' => ['tiktok.com']] as $network => $domains) {
            foreach ($domains as $domain) {
                if ($host === $domain || str_ends_with($host, '.'.$domain)) { return $network; }
            }
        }
        return null;
    }
}

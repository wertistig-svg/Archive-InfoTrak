<?php

namespace App\InfoTrak;

/**
 * Normalise une URL vidéo en lecteur intégrable (sans redirection externe).
 *
 * Types supportés :
 * - 'file' : mp4/webm/ogv direct → <video>
 * - 'youtube' : watch / youtu.be / shorts / embed → iframe youtube-nocookie
 * - 'vimeo' : vimeo.com/<id> → iframe player.vimeo.com
 */
final class VideoEmbed
{
    private function __construct() {}

    /**
     * @return array{type: 'file'|'youtube'|'vimeo', embedUrl: string, videoId: ?string}|null
     */
    public static function parse(?string $url): ?array
    {
        if (null === $url || '' === trim($url)) {
            return null;
        }
        $url = trim($url);

        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return null;
        }

        // Fichier direct (mp4/webm/ogv, avec query string éventuelle).
        if (preg_match('~\.(mp4|webm|ogv)(\?.*)?$~i', $url)) {
            return ['type' => 'file', 'embedUrl' => $url, 'videoId' => null];
        }

        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $host = (string) preg_replace('~^www\.~', '', $host);
        $path = (string) parse_url($url, PHP_URL_PATH);
        $query = [];
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        // YouTube : youtube.com/watch?v=, /shorts/, /embed/, /live/, youtu.be/.
        if (\in_array($host, ['youtube.com', 'youtu.be', 'youtube-nocookie.com'], true)) {
            $id = null;
            if ('youtu.be' === $host) {
                $id = trim($path, '/');
            } elseif (isset($query['v']) && \is_string($query['v'])) {
                $id = $query['v'];
            } elseif (preg_match('~^/(embed|shorts|live)/([^/?#]+)~', $path, $m)) {
                $id = $m[2];
            }
            $id = $id ? trim($id) : null;
            if ($id && preg_match('~^[A-Za-z0-9_-]{6,20}$~', $id)) {
                return [
                    'type' => 'youtube',
                    'embedUrl' => sprintf('https://www.youtube-nocookie.com/embed/%s?rel=0', $id),
                    'videoId' => $id,
                ];
            }

            return null;
        }

        // Vimeo : vimeo.com/<id>.
        if ('vimeo.com' === $host || str_ends_with($host, '.vimeo.com')) {
            if (preg_match('~/(\d{5,12})(?:$|[/?#])~', $path, $m)) {
                return [
                    'type' => 'vimeo',
                    'embedUrl' => sprintf('https://player.vimeo.com/video/%s?dnt=1', $m[1]),
                    'videoId' => $m[1],
                ];
            }

            return null;
        }

        return null;
    }

    public static function supports(?string $url): bool
    {
        return null !== self::parse($url);
    }
}

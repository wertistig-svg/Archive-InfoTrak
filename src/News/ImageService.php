<?php

namespace App\News;

use Symfony\Contracts\HttpClient\HttpClientInterface;

/** Extrait l'image d'aperçu (og:image / twitter:image) d'une page source. */
final class ImageService
{
    public function __construct(private readonly HttpClientInterface $http) {}

    public function extractImageUrl(?string $pageUrl): ?string
    {
        if (null === $pageUrl || '' === $pageUrl || !filter_var($pageUrl, FILTER_VALIDATE_URL)) {
            return null;
        }

        try {
            $response = $this->http->request('GET', $pageUrl, [
                'headers' => ['User-Agent' => 'Mozilla/5.0 (compatible; InfoTrak/1.0;apercu)'],
                'timeout' => 8,
                'max_duration' => 12,
                'max_redirects' => 3,
            ]);
            if (200 !== $response->getStatusCode()) {
                return null;
            }
            $html = mb_substr($response->getContent(false), 0, 500000);
        } catch (\Throwable) {
            return null;
        }

        return self::findImage($html, $pageUrl);
    }

    public static function findImage(string $html, string $pageUrl): ?string
    {
        if (!preg_match_all('/<meta\s[^>]*>/i', $html, $matches)) {
            return null;
        }

        foreach ($matches[0] as $tag) {
            $isImage = 1 === preg_match('/property=["\']og:image(?::url)?["\']/i', $tag)
                || 1 === preg_match('/name=["\']twitter:image(?::src)?["\']/i', $tag);
            if (!$isImage) {
                continue;
            }
            if (!preg_match('/content=["\']([^"\']+)["\']/i', $tag, $content)) {
                continue;
            }
            $url = self::resolveUrl(html_entity_decode(trim($content[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'), $pageUrl);
            if (null !== $url) {
                return $url;
            }
        }

        return null;
    }

    private static function resolveUrl(string $url, string $pageUrl): ?string
    {
        if (str_starts_with($url, 'data:') || str_starts_with($url, 'blob:')) {
            return null;
        }
        if (str_starts_with($url, '//')) {
            $url = 'https:'.$url;
        } elseif (str_starts_with($url, '/')) {
            $parts = parse_url($pageUrl);
            if (!isset($parts['scheme'], $parts['host'])) {
                return null;
            }
            $url = $parts['scheme'].'://'.$parts['host'].$url;
        }
        if (!filter_var($url, FILTER_VALIDATE_URL) || !str_starts_with($url, 'http')) {
            return null;
        }

        return $url;
    }
}

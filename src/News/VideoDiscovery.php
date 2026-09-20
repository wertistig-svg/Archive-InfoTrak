<?php
namespace App\News;

use App\InfoTrak\VideoEmbed;

/** Finds publicly embedded media, without downloading videos or bypassing players. */
final class VideoDiscovery
{
    public static function find(string $html, string $base): ?string
    {
        if ($html === '') { return null; }
        $doc = new \DOMDocument();
        @$doc->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new \DOMXPath($doc);
        $candidates = [];
        foreach ($xpath->query('//meta[@property="og:video" or @property="og:video:url" or @property="og:video:secure_url"]/@content') as $node) { $candidates[] = $node->nodeValue; }
        $walk = static function (mixed $value) use (&$walk, &$candidates): void {
            if (!is_array($value)) { return; }
            if (in_array('VideoObject', (array) ($value['@type'] ?? []), true)) {
                foreach (['embedUrl', 'contentUrl'] as $key) {
                    if (is_string($value[$key] ?? null)) { $candidates[] = $value[$key]; }
                }
            }
            foreach ($value as $child) { if (is_array($child)) { $walk($child); } }
        };
        foreach ($xpath->query('//script[@type="application/ld+json"]') as $node) { $walk(json_decode($node->textContent, true)); }
        foreach ($xpath->query('//article//video/@src | //article//video/source/@src | //article//iframe/@src | //div[contains(concat(" ",normalize-space(@class)," ")," article__body ")]//iframe/@src') as $node) { $candidates[] = $node->nodeValue; }
        foreach ($candidates as $url) {
            $url = trim(html_entity_decode($url, ENT_QUOTES | ENT_HTML5));
            if (str_starts_with($url, '//')) { $url = 'https:'.$url; }
            elseif (str_starts_with($url, '/')) { $url = 'https://'.parse_url($base, PHP_URL_HOST).$url; }
            if (mb_strlen($url) > 500 || parse_url($url, PHP_URL_SCHEME) !== 'https') { continue; }
            if (VideoEmbed::supports($url)) { return $url; }
        }
        return null;
    }
}

<?php

namespace App\News;

use App\Entity\Article;
use Symfony\Component\HttpClient\NoPrivateNetworkHttpClient;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/** Public publisher pages only. No cookies, login, paywall or Google redirect decoding. */
final class ArticleEnrichment
{
    private HttpClientInterface $safeHttp;
    public function __construct(HttpClientInterface $http, private readonly CacheInterface $cache)
    {
        $this->safeHttp = new NoPrivateNetworkHttpClient($http);
    }

    public static function allowed(string $url): bool
    {
        $p = parse_url($url);
        return ($p['scheme'] ?? '') === 'https' && !isset($p['user']) && !isset($p['pass']) && !isset($p['port'])
            && in_array(strtolower($p['host'] ?? ''), ['www.zinfos974.com', 'zinfos974.com', 'www.linfo.re', 'linfo.re', 'www.temoignages.re', 'temoignages.re'], true);
    }

    private function fetch(string $url): string
    {
        if (!self::allowed($url)) { return ''; }
        return $this->cache->get('news_page_v1_'.hash('sha256', $url), function (ItemInterface $item) use ($url): string {
            $item->expiresAfter(3600);
            try {
                $response = $this->safeHttp->request('GET', $url, [
                    'headers' => ['User-Agent' => 'InfoTrak/1.0 (+https://infotrak-re.onrender.com/)'],
                    'timeout' => 5, 'max_duration' => 8, 'max_redirects' => 0,
                    'on_progress' => static function (int $downloaded, int $size): void {
                        if (max($downloaded, $size) > 1500000) { throw new \RuntimeException('Page too large'); }
                    },
                ]);
                if ($response->getStatusCode() !== 200) { return ''; }
                return $response->getContent();
            } catch (\Throwable) { return ''; }
        });
    }

    public static function extract(string $html): string
    {
        if ($html === '') { return ''; }
        $doc = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        try { $doc->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING); }
        finally { libxml_clear_errors(); libxml_use_internal_errors($previous); }
        $xpath = new \DOMXPath($doc);
        // Restrict extraction to the publisher's article, excluding menus and related stories.
        foreach (['article-content', 'et_pb_post_content', 'entry-content', 'texte'] as $class) {
            $nodes = $xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " '.$class.' ")]//p');
            if ($nodes->length) {
                $parts = [];
                foreach ($nodes as $node) {
                    if ($xpath->query('ancestor::aside | ancestor::nav | ancestor::blockquote', $node)->length) { continue; }
                    $parts[] = $node->textContent;
                }
                if (mb_strlen(implode(' ', $parts)) > 100) { return implode(' ', $parts); }
            }
        }
        foreach ($xpath->query('//meta[@property="og:description" or @name="description"]/@content') as $node) {
            if (mb_strlen($node->nodeValue) > 100) { return $node->nodeValue; }
        }
        return '';
    }

    private function linfoLink(string $title): ?string
    {
        $wanted = trim(FeedClassifier::normalize(NewsBrief::title($title, 'Linfo.re')));
        foreach (['https://www.linfo.re/la-reunion', 'https://www.linfo.re/france'] as $index) {
            $html = $this->fetch($index);
            if (!$html) { continue; }
            preg_match_all('~<a\b[^>]*href=["\']([^"\']+)["\'][^>]*>(.*?)</a>~is', $html, $links, PREG_SET_ORDER);
            foreach ($links as $link) {
                $label = NewsBrief::text($link[2]);
                if (preg_match('~\btitle=["\'](.*?)["\']~is', $link[0], $attr)) { $label = NewsBrief::text($attr[1]); }
                if ($wanted !== trim(FeedClassifier::normalize($label))) { continue; }
                $url = html_entity_decode($link[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                if (str_starts_with($url, '/')) { $url = 'https://www.linfo.re'.$url; }
                if (self::allowed($url)) { return $url; }
            }
        }
        return null;
    }

    public function enrich(Article $article): bool
    {
        $url = $article->getSourceUrl() ?? '';
        if (str_contains(strtolower($article->getSource()?->getName() ?? ''), 'linfo.re') && !self::allowed($url)) {
            $url = $this->linfoLink($article->getTitle()) ?? '';
        }
        if (!self::allowed($url)) { return false; }
        $summary = NewsBrief::summarize(self::extract($this->fetch($url)), $article->getTitle());
        $old = NewsBrief::summarize($article->getContent() ?? '', $article->getTitle());
        if (mb_strlen($summary) <= mb_strlen($old)) { return false; }
        $article->setContent($summary)->setExcerpt(NewsBrief::summarize($summary, $article->getTitle(), 70, 2));
        $article->setSourceUrl($url);
        return true;
    }
}

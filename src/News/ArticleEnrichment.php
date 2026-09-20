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
            && in_array(strtolower($p['host'] ?? ''), self::hosts(), true);
    }

    public string $lastStatus = '';

    private static function hosts(): array
    {
        $hosts = [];
        foreach (DirectFeeds::SOURCES as $source) {
            foreach (['website','feed'] as $key) { $host = parse_url($source[$key], PHP_URL_HOST); $hosts[] = $host; $hosts[] = str_starts_with($host, 'www.') ? substr($host, 4) : 'www.'.$host; }
        }
        foreach (\App\InfoTrak\PublisherCatalog::SOURCES as $source) {
            if ($source['mode'] === 'portal') { continue; }
            $host = parse_url($source['url'], PHP_URL_HOST);
            $hosts[] = $host;
            $hosts[] = str_starts_with($host, 'www.') ? substr($host, 4) : 'www.'.$host;
        }
        return array_unique($hosts);
    }

    private function fetch(string $url): string
    {
        if (!self::allowed($url)) { return ''; }
        return $this->cache->get('news_page_v3_'.hash('sha256', $url), function (ItemInterface $item) use ($url): string {
            $item->expiresAfter(3600);
            try {
                $originalHost = preg_replace('/^www\./', '', parse_url($url, PHP_URL_HOST));
                for ($redirects = 0; $redirects <= 3; ++$redirects) {
                $response = $this->safeHttp->request('GET', $url, [
                    'headers' => ['User-Agent' => 'InfoTrak/1.0 (+https://infotrak-re.onrender.com/)'],
                    'timeout' => 5, 'max_duration' => 8, 'max_redirects' => 0,
                    'on_progress' => static function (int $downloaded, int $size): void {
                        if (max($downloaded, $size) > 1500000) { throw new \RuntimeException('Page too large'); }
                    },
                ]);
                $status = $response->getStatusCode();
                if (in_array($status, [301, 302, 303, 307, 308], true)) {
                    $next = $response->getHeaders(false)['location'][0] ?? '';
                    if (str_starts_with($next, '/') && !str_starts_with($next, '//')) { $next = 'https://'.parse_url($url, PHP_URL_HOST).$next; }
                    if (!self::allowed($next) || preg_replace('/^www\./', '', parse_url($next, PHP_URL_HOST) ?? '') !== $originalHost) { $this->lastStatus = 'Redirection hors du site source'; return ''; }
                    $url = $next;
                    continue;
                }
                if ($status !== 200) { $this->lastStatus = 'HTTP '.$status; return ''; }
                return $response->getContent();
                }
                $this->lastStatus = 'Trop de redirections';
                return '';
            } catch (\Throwable) { $this->lastStatus = 'Connexion indisponible'; return ''; }
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
        foreach (['article__body', 'article-body', 'article-content', 'et_pb_post_content', 'entry-content', 'texte'] as $class) {
            $nodes = $xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " '.$class.' ")]//p');
            if ($nodes->length) {
                $parts = [];
                foreach ($nodes as $node) {
                    if ($xpath->query("ancestor::aside | ancestor::nav | ancestor::blockquote | ancestor::*[@id='comments' or contains(@class, 'comments-area')]", $node)->length) { continue; }
                    if ($node->getAttribute('class') === 'article__tags') { continue; }
                    $text = NewsBrief::text(preg_replace('/\(Photo\s*:.*?\)/iu', '', $node->textContent));
                    if (!preg_match('/[\p{L}\p{N}]/u', $text)) { continue; }
                    if (str_starts_with($text, 'www.') || str_contains($text, '[email')) { continue; }
                    if ($text !== '' && !preg_match('/[.!?…][»”"\']?$/u', $text)) { $text .= '.'; }
                    $parts[] = $text;
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
        $sitemapLink = LinfoSitemap::findLink($this->fetch('https://www.linfo.re/sitemap-news.xml'), $title);
        if ($sitemapLink) { return $sitemapLink; }
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

    public static function findLink(string $html, string $base, string $title, string $publisher): ?string
    {
        $wanted = trim(FeedClassifier::normalize(NewsBrief::title($title, $publisher)));
        if ($wanted === '') { return null; }
        $doc = new \DOMDocument();
        @$doc->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        foreach ($doc->getElementsByTagName('a') as $a) {
            $labels = [$a->textContent, $a->getAttribute('title')];
            foreach (['h1','h2','h3'] as $tag) {
                foreach ($a->getElementsByTagName($tag) as $heading) { $labels[] = $heading->textContent; }
            }
            $url = html_entity_decode($a->getAttribute('href'), ENT_QUOTES | ENT_HTML5);
            if (str_starts_with($url, '/') && !str_starts_with($url, '//')) { $url = 'https://'.parse_url($base, PHP_URL_HOST).$url; }
            if (!array_filter($labels, static fn ($label) => DirectFeeds::matchesTitle(NewsBrief::text($label), $title, $publisher, $url))) { continue; }
            if (self::allowed($url) && parse_url($url, PHP_URL_HOST) === parse_url($base, PHP_URL_HOST)) { return $url; }
        }
        return null;
    }

    private function publisherLink(Article $article): ?string
    {
        $name = $article->getSource()?->getName() ?? '';
        $base = \App\InfoTrak\PublisherCatalog::websiteFor($name) ?? (DirectFeeds::forPublisher($name)[0]['website'] ?? null);
        if (!$base || !self::allowed($base)) { return null; }
        foreach (\App\InfoTrak\PublisherCatalog::SOURCES as $source) {
            if ($source['url'] !== $base || $source['mode'] !== 'rss') { continue; }
            $xml = @simplexml_load_string($this->fetch($source['feed']), \SimpleXMLElement::class, LIBXML_NONET);
            if (!$xml) { continue; }
            $wanted = trim(FeedClassifier::normalize(NewsBrief::title($article->getTitle(), $name)));
            foreach ($xml->channel->item ?? [] as $item) {
                $url = trim((string) $item->link);
                if (trim(FeedClassifier::normalize(NewsBrief::title((string) $item->title, $name))) === $wanted
                    && self::allowed($url) && parse_url($url, PHP_URL_HOST) === parse_url($base, PHP_URL_HOST)) { return $url; }
            }
        }
        $indexes = [$base];
        if ($base === 'https://imazpress.com') { $indexes = [$base.'/actualite', $base.'/article/zoom/meteo', $base.'/article/les-plus-lus']; }
        foreach ($indexes as $index) {
            $link = self::findLink($this->fetch($index), $base, $article->getTitle(), $name);
            if ($link) { return $link; }
        }
        return null;
    }

    public function enrich(Article $article): bool
    {
        $this->lastStatus = '';
        $changed = false;
        $name = $article->getSource()?->getName() ?? '';
        foreach (DirectFeeds::forPublisher($name) as $feed) {
            $xml = @simplexml_load_string($this->fetch($feed['feed']), \SimpleXMLElement::class, LIBXML_NONET);
            if (!$xml) { continue; }
            foreach ($xml->channel->item ?? [] as $item) {
                $link = trim((string) $item->link);
                if (!DirectFeeds::matchesTitle((string) $item->title, $article->getDisplayTitle(), $name, $link)) { continue; }
                $host = preg_replace('/^www\./', '', parse_url($link, PHP_URL_HOST) ?? '');
                $expected = preg_replace('/^www\./', '', parse_url($feed['website'], PHP_URL_HOST) ?? '');
                if (!self::allowed($link) || $host !== $expected) { continue; }
                $text = (string) $item->children('http://purl.org/rss/1.0/modules/content/')->encoded;
                $text = $text ?: (string) $item->description;
                $brief = NewsBrief::summarize($text, $article->getDisplayTitle());
                if (mb_strlen($brief) > mb_strlen($article->getReadingSummary())) {
                    $article->setContent($brief)->setExcerpt(NewsBrief::summarize($brief, $article->getDisplayTitle(), 70, 2)); $changed = true;
                }
                if ($article->getSourceUrl() !== $link) { $article->setSourceUrl($link); $changed = true; }
                break 2;
            }
        }
        $url = $article->getSourceUrl() ?? '';
        if (str_contains(strtolower($article->getSource()?->getName() ?? ''), 'linfo.re') && !self::allowed($url)) {
            $url = $this->linfoLink($article->getTitle()) ?? '';
        }
        if (!self::allowed($url)) { $url = $this->publisherLink($article) ?? ''; }
        if (!self::allowed($url)) { $this->lastStatus = $this->lastStatus ?: 'Lien original introuvable'; return $changed; }
        $html = $this->fetch($url);
        if ($html === '') { $this->lastStatus = $changed && $article->getReadingSummary() !== '' ? 'Résumé récupéré dans le flux direct' : ($this->lastStatus ?: 'Page inaccessible (cache)'); return $changed; }
        $metadata = ArticleMetadata::extract($html);
        foreach (['published'=>['getSourcePublishedAt','setSourcePublishedAt'], 'modified'=>['getSourceModifiedAt','setSourceModifiedAt'], 'minutes'=>['getSourceReadingMinutes','setSourceReadingMinutes']] as $key=>[$getter,$setter]) {
            if (isset($metadata[$key]) && $article->$getter() != $metadata[$key]) { $article->$setter($metadata[$key]); $changed = true; }
        }
        if (!$article->getVideoUrl() && ($video = VideoDiscovery::find($html, $url))) {
            $article->setVideoUrl($video);
            $changed = true;
        }
        if (!$article->hasImage() && ($image = ImageService::findImage($html, $url)) && mb_strlen($image) <= 500) {
            $article->setImageUrl($image);
            $changed = true;
        }
        $summary = NewsBrief::summarize(self::extract($html), $article->getTitle());
        $this->lastStatus = $summary === '' && $article->getReadingSummary() === '' ? 'Pas de résumé exploitable' : 'Résumé disponible';
        $old = NewsBrief::summarize($article->getContent() ?? '', $article->getTitle());
        if ($article->getSourceUrl() !== $url) { $article->setSourceUrl($url); $changed = true; }
        if (mb_strlen($summary) <= mb_strlen($old)) { return $changed; }
        $article->setContent($summary)->setExcerpt(NewsBrief::summarize($summary, $article->getTitle(), 70, 2));
        return true;
    }
}

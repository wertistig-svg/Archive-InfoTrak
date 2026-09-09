<?php

namespace App\Service;

use App\Entity\Article;
use App\InfoTrak\CommunityCatalog;
use App\News\FeedClassifier;

/** Mesures sur des messages collectés, sans estimation des tendances globales. */
final class CommunityService
{
    /** @param iterable<Article> $articles */
    public function summarize(iterable $articles, array $networks, string $community, string $query, \DateTimeImmutable $since, \DateTimeImmutable $until): array
    {
        $posts = $trends = $seen = [];
        $counts = array_fill_keys(array_keys(CommunityCatalog::NETWORKS), 0);
        $sourceNames = array_fill_keys(array_keys(CommunityCatalog::NETWORKS), []);
        $terms = CommunityCatalog::COMMUNITIES[$community]['terms'] ?? [];
        foreach ($articles as $article) {
            if ($article->isDemo() || $article->getPublishedAt() < $since || $article->getPublishedAt() > $until || 'social' !== $article->getSource()?->getType()) { continue; }
            $network = CommunityCatalog::networkForUrl($article->getSourceUrl());
            if (null === $network) { continue; }
            // Certains réseaux identifient le message dans la query string (Facebook).
            $url = preg_replace('/#.*$/', '', $article->getSourceUrl());
            if (isset($seen[$url])) { continue; }
            $seen[$url] = true;
            ++$counts[$network];
            $sourceName = trim((string) $article->getSource()?->getName());
            if ('' !== $sourceName) { $sourceNames[$network][$sourceName] = true; }
            if ($networks && !in_array($network, $networks, true)) { continue; }
            $raw = $article->getTitle().' '.$article->getExcerpt();
            $text = FeedClassifier::normalize($raw);
            if ($terms && !array_any($terms, static fn ($term) => str_contains($text, FeedClassifier::normalize($term)))) { continue; }
            if ('' !== $query && !str_contains($text, FeedClassifier::normalize($query))) { continue; }
            $posts[] = ['article' => $article, 'network' => $network];
            preg_match_all('/(?<![\p{L}\p{N}_])#([\p{L}\p{N}_]{2,50})/u', $raw, $matches);
            $labels = array_map(static fn ($tag) => '#'.mb_strtolower($tag), $matches[1]);
            foreach (CommunityCatalog::COMMUNITIES as $entry) {
                if (array_any($entry['terms'], static fn ($term) => str_contains($text, FeedClassifier::normalize($term)))) { $labels[] = $entry['label']; }
            }
            foreach (array_unique($labels) as $label) { $trends[$label] = ($trends[$label] ?? 0) + 1; }
        }
        usort($posts, static fn ($a, $b) => $b['article']->getPublishedAt() <=> $a['article']->getPublishedAt());
        arsort($trends);
        $sourceCounts = array_map('count', $sourceNames);
        return ['posts' => $posts, 'total' => count($posts), 'counts' => $counts, 'sourceCounts' => $sourceCounts, 'trends' => array_slice(array_filter($trends, static fn ($n) => $n >= 2), 0, 8, true)];
    }
}

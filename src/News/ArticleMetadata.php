<?php
namespace App\News;

final class ArticleMetadata
{
    public static function extract(string $html): array
    {
        $values = [];
        if ($html === '') { return $values; }
        $doc = new \DOMDocument();
        @$doc->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new \DOMXPath($doc);
        foreach (['published'=>'article:published_time','modified'=>'article:modified_time'] as $key=>$property) {
            foreach ($xpath->query('//meta[@property="'.$property.'"]/@content') as $node) {
                if ($date = self::date($node->nodeValue)) { $values[$key] = $date; break; }
            }
        }
        $walk = static function (mixed $node) use (&$walk, &$values): void {
            if (!is_array($node)) { return; }
            if (array_intersect((array) ($node['@type'] ?? []), ['NewsArticle','Article','BlogPosting','ReportageNewsArticle'])) {
                foreach (['published'=>'datePublished','modified'=>'dateModified'] as $key=>$field) {
                    if (!isset($values[$key]) && is_string($node[$field] ?? null) && ($date = self::date($node[$field]))) { $values[$key] = $date; }
                }
                if (is_string($node['timeRequired'] ?? null) && preg_match('/^PT(?:(\d+)H)?(?:(\d+)M)?(?:(\d+)S)?$/', $node['timeRequired'], $m)) {
                    $minutes = (int) ceil(((int) ($m[1] ?? 0)*3600 + (int) ($m[2] ?? 0)*60 + (int) ($m[3] ?? 0))/60);
                    if ($minutes > 0 && $minutes <= 180) { $values['minutes'] = $minutes; }
                }
                return; // Do not take dates from related articles nested inside this article.
            }
            foreach ($node as $child) { if (is_array($child)) { $walk($child); } }
        };
        foreach ($xpath->query('//script[@type="application/ld+json"]') as $node) { $walk(json_decode($node->textContent, true)); }
        if (isset($values['published'], $values['modified']) && $values['modified'] < $values['published']) { unset($values['modified']); }
        return $values;
    }
    private static function date(string $value): ?\DateTimeImmutable
    {
        // Require an explicit timezone; never guess the publisher's local time.
        if (!preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(?::\d{2}(?:\.\d+)?)?(?:Z|[+-]\d{2}:?\d{2})$/', $value)) { return null; }
        try {
            $date = new \DateTimeImmutable($value);
            if (\DateTimeImmutable::getLastErrors() !== false) { return null; }
            return $date->setTimezone(new \DateTimeZone('UTC'));
        } catch (\Exception) { return null; }
    }
}

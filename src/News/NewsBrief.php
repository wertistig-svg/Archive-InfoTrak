<?php

namespace App\News;

/** Short, faithful extracts: never complete a missing sentence or invent a fact. */
final class NewsBrief
{
    public static function text(string $html): string
    {
        $html = preg_replace('~<(script|style)\b[^>]*>.*?</\1>~is', '', $html);
        $html = preg_replace('~</(?:p|div|li|h[1-6])>|<br\s*/?>~i', ' ', $html);
        return trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    }

    public static function title(string $title, string $publisher): string
    {
        $title = self::text($title);
        return trim(preg_replace('~\s+[-–—|]\s*'.preg_quote($publisher, '~').'\s*$~iu', '', $title));
    }

    public static function summarize(string $html, string $title = '', int $words = 110, int $sentences = 4): string
    {
        $text = self::text($html);
        // Drop incomplete tails (RSS ellipses) rather than presenting them as facts.
        $parts = preg_split('/(?<=[.!?])\s+(?=[\p{Lu}\d«“])/u', $text) ?: [];
        $selected = []; $count = 0;
        foreach ($parts as $part) {
            $part = trim($part);
            if (preg_match('/(?:\[?…\]?|\.{3})/u', $part)) { continue; }
            if (!preg_match('/[.!?][»”"\']?$/u', $part)) { continue; }
            if (preg_match('/^(?:Lire (?:aussi|la suite)|À lire|Plus sur|Cet article|L’article .* est apparu)/iu', $part)) { continue; }
            if (trim(FeedClassifier::normalize($part)) === trim(FeedClassifier::normalize($title))) { continue; }
            $key = mb_strtolower($part);
            if (isset($selected[$key])) { continue; }
            $n = count(preg_split('/\s+/u', $part));
            if ($count + $n > $words) { break; }
            $selected[$key] = $part; $count += $n;
            if (count($selected) >= $sentences) { break; }
        }
        return implode(' ', $selected);
    }
}

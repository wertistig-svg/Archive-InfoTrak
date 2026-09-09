<?php

namespace App\Search;

/** Un résultat web normalisé : toujours avec sa source et son résumé. */
final readonly class LiveResult
{
    /** @param 'news'|'wiki'|'place'|'social' $type */
    public function __construct(
        public string $type,
        public string $title,
        public string $summary,
        public string $source,
        public string $url,
        public ?string $publishedAt = null,
    ) {}

    /** @return array{type: string, title: string, summary: string, source: string, url: string, publishedAt: ?string} */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'title' => $this->title,
            'summary' => $this->summary,
            'source' => $this->source,
            'url' => $this->url,
            'publishedAt' => $this->publishedAt,
        ];
    }
}

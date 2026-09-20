<?php

namespace App\Entity;

use App\Repository\ArticleRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

#[ORM\Entity(repositoryClass: ArticleRepository::class)]
#[ORM\Table(name: 'article')]
#[ORM\Index(columns: ['category'])]
#[ORM\Index(columns: ['place'])]
#[ORM\Index(columns: ['published_at'])]
#[UniqueEntity('slug')]
class Article
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(options: ['default' => false])]
    private bool $isDemo = false;

    #[ORM\Column(length: 255)]
    private string $title = '';

    #[ORM\Column(length: 180, unique: true)]
    private string $slug = '';

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $excerpt = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $content = null;

    #[ORM\Column(length: 50)]
    private string $category = '';

    #[ORM\Column(length: 50)]
    private string $place = '';

    #[ORM\ManyToOne(targetEntity: Source::class, inversedBy: 'articles')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private ?Source $source = null;

    /** Lien direct vers l'article d'origine ("consulter à la source"). */
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $sourceUrl = null;

    /** URL vidéo (mp4 / embed) lue directement dans l'app. Null = pas de vidéo. */
    #[ORM\Column(length: 500, nullable: true)]
    private ?string $videoUrl = null;

    /** Aperçu image de la page source (og:image). Null = pas d'aperçu. */
    #[ORM\Column(length: 500, nullable: true)]
    private ?string $imageUrl = null;

    #[ORM\Column]
    private \DateTimeImmutable $publishedAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $sourcePublishedAt = null;
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $sourceModifiedAt = null;
    #[ORM\Column(nullable: true)]
    private ?int $sourceReadingMinutes = null;

    #[ORM\Column(options: ['default' => false])]
    private bool $isVerified = false;

    #[ORM\Column(length: 50, options: ['default' => 'À recouper'])]
    private string $verificationLabel = 'À recouper';

    /** Mis en avant pour la fréquence "Important seulement". */
    #[ORM\Column(options: ['default' => false])]
    private bool $isImportant = false;

    #[ORM\Column(options: ['default' => 0])]
    private int $viewCount = 0;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $now = new \DateTimeImmutable();
        $this->publishedAt = $now;
        $this->createdAt = $now;
    }

    public function getId(): ?int { return $this->id; }
    public function isDemo(): bool { return $this->isDemo; }
    public function setDemo(bool $demo): self { $this->isDemo = $demo; return $this; }
    public function getTrustLabel(): string
    {
        if ($this->isDemo) { return 'Démonstration'; }
        // Compatibilité avec les anciens imports : aucun badge ne certifie les faits.
        if ('Source retrouvée' === $this->verificationLabel) { return $this->verificationLabel; }
        if ($this->isVerified) { return 'Source identifiée'; }
        return $this->verificationLabel;
    }
    public function getTitle(): string { return $this->title; }
    public function getDisplayTitle(): string { return \App\News\NewsBrief::title($this->title, $this->source?->getName() ?? ''); }
    public function getReadingSummary(): string
    {
        $text = $this->content ?: ($this->excerpt ?? '');
        return $this->source?->getType() === 'social' ? \App\News\NewsBrief::text($text) : \App\News\NewsBrief::summarize($text, $this->getDisplayTitle());
    }
    public function getCardSummary(): string { return \App\News\NewsBrief::summarize($this->getReadingSummary(), $this->getDisplayTitle(), 70, 2); }
    public function setTitle(string $title): self { $this->title = $title; return $this; }
    public function getSlug(): string { return $this->slug; }
    public function setSlug(string $slug): self { $this->slug = $slug; return $this; }
    public function getExcerpt(): ?string { return $this->excerpt; }
    public function setExcerpt(?string $excerpt): self { $this->excerpt = $excerpt; return $this; }
    public function getContent(): ?string { return $this->content; }
    public function setContent(?string $content): self { $this->content = $content; return $this; }
    public function getCategory(): string { return $this->category; }
    public function setCategory(string $category): self { $this->category = $category; return $this; }
    public function getPlace(): string { return $this->place; }
    public function setPlace(string $place): self { $this->place = $place; return $this; }
    public function getSource(): ?Source { return $this->source; }
    public function setSource(?Source $source): self { $this->source = $source; return $this; }
    public function getSourceUrl(): ?string { return $this->sourceUrl; }
    public function setSourceUrl(?string $url): self { $this->sourceUrl = $url; return $this; }
    public function getVideoUrl(): ?string { return $this->videoUrl; }
    public function setVideoUrl(?string $url): self { $this->videoUrl = $url; return $this; }
    public function hasVideo(): bool { return null !== $this->videoUrl && '' !== $this->videoUrl; }
    public function getImageUrl(): ?string { return $this->imageUrl; }    public function setImageUrl(?string $url): self { $this->imageUrl = $url; return $this; }
    public function hasImage(): bool { return null !== $this->imageUrl && '' !== $this->imageUrl; }

    /** Domaine de la page source (pour le favicon réel du média). */
    public function getSourceHost(): ?string
    {
        foreach ([\App\InfoTrak\PublisherCatalog::websiteFor($this->source?->getName() ?? ''), $this->source?->getWebsiteUrl(), $this->sourceUrl] as $url) {
            if (null !== $url && '' !== $url) {
                $host = parse_url($url, PHP_URL_HOST);
                if (\is_string($host) && '' !== $host && $host !== 'news.google.com') {
                    return $host;
                }
            }
        }

        return null;
    }
    public function getPublisherLogo(): ?string
    {
        return in_array($this->getSourceHost(), ['www.linfo.re', 'linfo.re'], true) ? '/images/publishers/linfo.png' : null;
    }
    public function getPublishedAt(): \DateTimeImmutable { return $this->publishedAt; }
    public function getSourcePublishedAt(): ?\DateTimeImmutable { return $this->sourcePublishedAt; }
    public function setSourcePublishedAt(?\DateTimeImmutable $date): self { $this->sourcePublishedAt = $date; return $this; }
    public function getSourceModifiedAt(): ?\DateTimeImmutable { return $this->sourceModifiedAt; }
    public function setSourceModifiedAt(?\DateTimeImmutable $date): self { $this->sourceModifiedAt = $date; return $this; }
    public function getSourceReadingMinutes(): ?int { return $this->sourceReadingMinutes; }
    public function setSourceReadingMinutes(?int $minutes): self { $this->sourceReadingMinutes = $minutes; return $this; }
    public function setPublishedAt(\DateTimeImmutable $dt): self { $this->publishedAt = $dt; return $this; }
    public function isVerified(): bool { return $this->isVerified; }
    public function setVerified(bool $v): self { $this->isVerified = $v; return $this; }
    public function getVerificationLabel(): string { return $this->verificationLabel; }
    public function setVerificationLabel(string $label): self { $this->verificationLabel = $label; return $this; }
    public function isImportant(): bool { return $this->isImportant; }
    public function setImportant(bool $v): self { $this->isImportant = $v; return $this; }
    public function getViewCount(): int { return $this->viewCount; }
    public function incrementViews(): self { ++$this->viewCount; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}

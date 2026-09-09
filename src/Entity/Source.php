<?php

namespace App\Entity;

use App\Repository\SourceRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

#[ORM\Entity(repositoryClass: SourceRepository::class)]
#[ORM\Table(name: 'source')]
#[UniqueEntity('slug')]
class Source
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 150)]
    private string $name = '';

    #[ORM\Column(length: 150, unique: true)]
    private string $slug = '';

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $websiteUrl = null;

    /** official | public | press */
    #[ORM\Column(length: 20, options: ['default' => 'press'])]
    private string $type = 'press';

    #[ORM\Column(type: 'smallint', options: ['default' => 80])]
    private int $reliabilityScore = 80;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    /** @var Collection<int, Article> */
    #[ORM\OneToMany(targetEntity: Article::class, mappedBy: 'source')]
    private Collection $articles;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->articles = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }
    public function getName(): string { return $this->name; }
    public function setName(string $name): self { $this->name = $name; return $this; }
    public function getSlug(): string { return $this->slug; }
    public function setSlug(string $slug): self { $this->slug = $slug; return $this; }
    public function getWebsiteUrl(): ?string { return $this->websiteUrl; }
    public function setWebsiteUrl(?string $url): self { $this->websiteUrl = $url; return $this; }
    public function getType(): string { return $this->type; }
    public function setType(string $type): self { $this->type = $type; return $this; }
    public function getReliabilityScore(): int { return $this->reliabilityScore; }
    public function setReliabilityScore(int $score): self { $this->reliabilityScore = max(0, min(100, $score)); return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    public function isTrusted(): bool
    {
        return $this->reliabilityScore >= 70;
    }
}

<?php

namespace App\Entity;

use App\Repository\ArticleFeedbackRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ArticleFeedbackRepository::class)]
#[ORM\Table(name: 'article_feedback')]
#[ORM\UniqueConstraint(name: 'uniq_feedback_article_owner', columns: ['article_id', 'owner_key'])]
class ArticleFeedback
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Article::class)]
    #[ORM\JoinColumn(name: 'article_id', nullable: false, onDelete: 'CASCADE')]
    private ?Article $article = null;

    #[ORM\Column(name: 'owner_key', length: 64)]
    private string $ownerKey = 'default';

    /** true = "ça m'intéresse", false = "pas intéressé". */
    #[ORM\Column]
    private bool $interested = true;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getArticle(): ?Article { return $this->article; }
    public function setArticle(?Article $article): self { $this->article = $article; return $this; }
    public function getOwnerKey(): string { return $this->ownerKey; }
    public function setOwnerKey(string $key): self { $this->ownerKey = $key; return $this; }
    public function isInterested(): bool { return $this->interested; }
    public function setInterested(bool $v): self { $this->interested = $v; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}

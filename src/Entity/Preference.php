<?php

namespace App\Entity;

use App\Repository\PreferenceRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PreferenceRepository::class)]
#[ORM\Table(name: 'preference')]
class Preference
{
    public const FREQUENCY_IMPORTANT = 'important';
    public const FREQUENCY_DAILY = 'daily';
    public const FREQUENCY_LIVE = 'live';

    public const FREQUENCIES = [
        self::FREQUENCY_IMPORTANT => 'Important seulement',
        self::FREQUENCY_DAILY => 'Résumé quotidien',
        self::FREQUENCY_LIVE => 'Tout en direct',
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** Clé du propriétaire (ex: 'default' en mono-user, ou id de session plus tard). */
    #[ORM\Column(length: 64, unique: true)]
    private string $ownerKey = 'default';

    /** @var string[] */
    #[ORM\Column(type: 'json', options: ['default' => '[]'])]
    private array $topics = [];

    /** @var string[] Zones suivies (La Réunion, France, Chine, Asie, International...). */
    #[ORM\Column(type: 'json', options: ['default' => '[]'])]
    private array $zones = [];

    #[ORM\Column(length: 20, options: ['default' => 'important'])]
    private string $frequency = self::FREQUENCY_IMPORTANT;

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $notifyEmail = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $now = new \DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    public function getId(): ?int { return $this->id; }
    public function getOwnerKey(): string { return $this->ownerKey; }
    public function setOwnerKey(string $key): self { $this->ownerKey = $key; return $this; }

    /** @return string[] */
    public function getTopics(): array { return $this->topics; }
    /** @param string[] $topics */
    public function setTopics(array $topics): self { $this->topics = array_values(array_unique($topics)); $this->touch(); return $this; }

    /** @return string[] */
    public function getZones(): array { return $this->zones; }
    /** @param string[] $zones */
    public function setZones(array $zones): self { $this->zones = array_values(array_unique($zones)); $this->touch(); return $this; }

    public function getFrequency(): string { return $this->frequency; }
    public function setFrequency(string $frequency): self
    {
        if (!isset(self::FREQUENCIES[$frequency])) {
            throw new \InvalidArgumentException(sprintf('Fréquence inconnue "%s".', $frequency));
        }
        $this->frequency = $frequency;
        $this->touch();
        return $this;
    }

    public function getNotifyEmail(): ?string { return $this->notifyEmail; }
    public function setNotifyEmail(?string $email): self { $this->notifyEmail = $email; $this->touch(); return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }

    public function touch(): void { $this->updatedAt = new \DateTimeImmutable(); }

    public function matches(Article $article): bool
    {
        if ([] !== $this->topics && !\in_array($article->getCategory(), $this->topics, true)) {
            return false;
        }
        if ([] !== $this->zones && !\in_array($article->getPlace(), $this->zones, true)) {
            return false;
        }
        if (self::FREQUENCY_IMPORTANT === $this->frequency && !$article->isImportant()) {
            return false;
        }

        return true;
    }
}

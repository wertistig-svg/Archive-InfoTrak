<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'app_user')]
#[UniqueEntity('googleId')]
#[UniqueEntity('email')]
class User implements UserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** Identifiant stable fourni par Google (sub). */
    #[ORM\Column(length: 64, unique: true)]
    private string $googleId = '';

    #[ORM\Column(length: 180, unique: true)]
    private string $email = '';

    #[ORM\Column(length: 150)]
    private string $displayName = '';

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $avatarUrl = null;

    /** @var string[] */
    #[ORM\Column(type: 'json', options: ['default' => '[]'])]
    private array $roles = [];

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getGoogleId(): string { return $this->googleId; }
    public function setGoogleId(string $googleId): self { $this->googleId = $googleId; return $this; }
    public function getEmail(): string { return $this->email; }
    public function setEmail(string $email): self { $this->email = $email; return $this; }
    public function getDisplayName(): string { return $this->displayName; }
    public function setDisplayName(string $displayName): self { $this->displayName = $displayName; return $this; }
    public function getAvatarUrl(): ?string { return $this->avatarUrl; }
    public function setAvatarUrl(?string $avatarUrl): self { $this->avatarUrl = $avatarUrl; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    public function getInitials(): string
    {
        $parts = preg_split('/\s+/', trim($this->displayName !== '' ? $this->displayName : $this->email));
        $initials = '';
        foreach (\array_slice($parts ?: [], 0, 2) as $part) {
            $initials .= mb_strtoupper(mb_substr($part, 0, 1));
        }

        return '' !== $initials ? $initials : '?';
    }

    public function getRoles(): array
    {
        $roles = $this->roles;
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    /** @param string[] $roles */
    public function setRoles(array $roles): self { $this->roles = $roles; return $this; }

    public function eraseCredentials(): void {}

    public function getUserIdentifier(): string
    {
        return $this->googleId;
    }
}

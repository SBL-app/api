<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'users')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180, unique: true)]
    private ?string $username = null;

    #[ORM\Column]
    private array $roles = [];

    #[ORM\Column]
    private ?string $password = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $apiKey = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $lastLogin = null;

    #[ORM\Column(type: 'boolean')]
    private bool $isActive = true;

    #[ORM\Column(length: 255, unique: true, nullable: true)]
    private ?string $discordId = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $discordUsername = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $discordAvatar = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $apiKeyExpiresAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $tokenInvalidatedAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function setUsername(string $username): static
    {
        $this->username = $username;
        return $this;
    }

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->username;
    }

    /**
     * @see UserInterface
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // guarantee every user at least has ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    public function setRoles(array $roles): static
    {
        $this->roles = $roles;
        return $this;
    }

    /**
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;
        return $this;
    }

    /**
     * @see UserInterface
     */
    public function eraseCredentials(): void
    {
        // If you store any temporary, sensitive data on the user, clear it here
        // $this->plainPassword = null;
    }

    public function getApiKey(): ?string
    {
        return $this->apiKey;
    }

    public function setApiKey(?string $apiKey): static
    {
        $this->apiKey = $apiKey;
        return $this;
    }

    public function getLastLogin(): ?\DateTimeInterface
    {
        return $this->lastLogin;
    }

    public function setLastLogin(?\DateTimeInterface $lastLogin): static
    {
        $this->lastLogin = $lastLogin;
        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;
        return $this;
    }

    public function getDiscordId(): ?string
    {
        return $this->discordId;
    }

    public function setDiscordId(?string $discordId): static
    {
        $this->discordId = $discordId;
        return $this;
    }

    public function getDiscordUsername(): ?string
    {
        return $this->discordUsername;
    }

    public function setDiscordUsername(?string $discordUsername): static
    {
        $this->discordUsername = $discordUsername;
        return $this;
    }

    public function getDiscordAvatar(): ?string
    {
        return $this->discordAvatar;
    }

    public function setDiscordAvatar(?string $discordAvatar): static
    {
        $this->discordAvatar = $discordAvatar;
        return $this;
    }

    public function getApiKeyExpiresAt(): ?\DateTimeImmutable
    {
        return $this->apiKeyExpiresAt;
    }

    public function setApiKeyExpiresAt(?\DateTimeImmutable $apiKeyExpiresAt): static
    {
        $this->apiKeyExpiresAt = $apiKeyExpiresAt;
        return $this;
    }

    public function isApiKeyExpired(): bool
    {
        if ($this->apiKeyExpiresAt === null) {
            return false;
        }
        return $this->apiKeyExpiresAt < new \DateTimeImmutable();
    }

    public function getTokenInvalidatedAt(): ?\DateTimeImmutable
    {
        return $this->tokenInvalidatedAt;
    }

    public function setTokenInvalidatedAt(?\DateTimeImmutable $tokenInvalidatedAt): static
    {
        $this->tokenInvalidatedAt = $tokenInvalidatedAt;
        return $this;
    }

    public function invalidateAllTokens(): static
    {
        $this->tokenInvalidatedAt = new \DateTimeImmutable();
        return $this;
    }
}

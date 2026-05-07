<?php

namespace App\Entity;

use App\Repository\GameResultRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: GameResultRepository::class)]
class GameResult
{
    public const STATUS_PENDING_VALIDATION = 'pending_validation';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_DISPUTED = 'disputed';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Game $game = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Team $submittedByTeam = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $submittedBy = null;

    #[ORM\Column]
    private ?int $score1 = null;

    #[ORM\Column]
    private ?int $score2 = null;

    #[ORM\Column(length: 30)]
    private string $status = self::STATUS_PENDING_VALIDATION;

    #[ORM\ManyToOne]
    private ?User $respondedBy = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $respondedAt = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getGame(): ?Game { return $this->game; }
    public function setGame(?Game $game): static { $this->game = $game; return $this; }

    public function getSubmittedByTeam(): ?Team { return $this->submittedByTeam; }
    public function setSubmittedByTeam(?Team $team): static { $this->submittedByTeam = $team; return $this; }

    public function getSubmittedBy(): ?User { return $this->submittedBy; }
    public function setSubmittedBy(?User $user): static { $this->submittedBy = $user; return $this; }

    public function getScore1(): ?int { return $this->score1; }
    public function setScore1(int $score1): static { $this->score1 = $score1; return $this; }

    public function getScore2(): ?int { return $this->score2; }
    public function setScore2(int $score2): static { $this->score2 = $score2; return $this; }

    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): static { $this->status = $status; return $this; }

    public function getRespondedBy(): ?User { return $this->respondedBy; }
    public function setRespondedBy(?User $user): static { $this->respondedBy = $user; return $this; }

    public function getRespondedAt(): ?\DateTimeImmutable { return $this->respondedAt; }
    public function setRespondedAt(?\DateTimeImmutable $at): static { $this->respondedAt = $at; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    public function isPendingValidation(): bool { return $this->status === self::STATUS_PENDING_VALIDATION; }
    public function isConfirmed(): bool { return $this->status === self::STATUS_CONFIRMED; }
    public function isDisputed(): bool { return $this->status === self::STATUS_DISPUTED; }

    public function confirm(User $respondedBy): static
    {
        $this->status = self::STATUS_CONFIRMED;
        $this->respondedBy = $respondedBy;
        $this->respondedAt = new \DateTimeImmutable();
        return $this;
    }

    public function dispute(User $respondedBy): static
    {
        $this->status = self::STATUS_DISPUTED;
        $this->respondedBy = $respondedBy;
        $this->respondedAt = new \DateTimeImmutable();
        return $this;
    }
}

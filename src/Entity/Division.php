<?php

namespace App\Entity;

use App\Repository\DivisionRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DivisionRepository::class)]
class Division
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\ManyToOne]
    private ?Season $season = null;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $isFinalized = false;

    /**
     * Niveau hiérarchique de la division (1 = division la plus haute).
     * Utilisé pour la promotion (vers un niveau inférieur en nombre) et la
     * relégation (vers un niveau supérieur en nombre).
     */
    #[ORM\Column(type: 'integer', options: ['default' => 1])]
    private int $level = 1;

    /** Nombre d'équipes promues depuis cette division en fin de saison. */
    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $promotionCount = 0;

    /** Nombre d'équipes reléguées depuis cette division en fin de saison. */
    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $relegationCount = 0;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getSeason(): ?Season
    {
        return $this->season;
    }

    public function setSeason(?Season $seasonId): static
    {
        $this->season = $seasonId;

        return $this;
    }

    public function isFinalized(): bool
    {
        return $this->isFinalized;
    }

    public function setIsFinalized(bool $isFinalized): static
    {
        $this->isFinalized = $isFinalized;

        return $this;
    }

    public function getLevel(): int
    {
        return $this->level;
    }

    public function setLevel(int $level): static
    {
        $this->level = max(1, $level);

        return $this;
    }

    public function getPromotionCount(): int
    {
        return $this->promotionCount;
    }

    public function setPromotionCount(int $promotionCount): static
    {
        $this->promotionCount = max(0, $promotionCount);

        return $this;
    }

    public function getRelegationCount(): int
    {
        return $this->relegationCount;
    }

    public function setRelegationCount(int $relegationCount): static
    {
        $this->relegationCount = max(0, $relegationCount);

        return $this;
    }
}
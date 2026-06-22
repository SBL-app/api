<?php

namespace App\Entity;

use App\Repository\BracketEntryRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: BracketEntryRepository::class)]
#[ORM\UniqueConstraint(name: 'unique_bracket_seed', columns: ['bracket_id', 'seed'])]
class BracketEntry
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Bracket $bracket = null;

    #[ORM\Column]
    private ?int $seed = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Team $team = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getBracket(): ?Bracket
    {
        return $this->bracket;
    }

    public function setBracket(?Bracket $bracket): static
    {
        $this->bracket = $bracket;

        return $this;
    }

    public function getSeed(): ?int
    {
        return $this->seed;
    }

    public function setSeed(int $seed): static
    {
        $this->seed = $seed;

        return $this;
    }

    public function getTeam(): ?Team
    {
        return $this->team;
    }

    public function setTeam(?Team $team): static
    {
        $this->team = $team;

        return $this;
    }
}

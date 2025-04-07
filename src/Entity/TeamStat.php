<?php

namespace App\Entity;

use App\Repository\TeamStatRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TeamStatRepository::class)]
class TeamStat
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?int $wins = null;

    #[ORM\Column]
    private ?int $losses = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Team $team = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Division $division = null;

    #[ORM\Column]
    private ?int $points = null;

    #[ORM\Column(nullable: true)]
    private ?int $ties = null;

    #[ORM\Column]
    private ?int $winRounds = null;

    #[ORM\Column]
    private ?int $looseRounds = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getWins(): ?int
    {
        return $this->wins;
    }

    public function setWins(int $wins): static
    {
        $this->wins = $wins;

        return $this;
    }

    public function getLosses(): ?int
    {
        return $this->losses;
    }

    public function setLosses(int $losses): static
    {
        $this->losses = $losses;

        return $this;
    }

    public function getTeam(): ?Team
    {
        return $this->team;
    }

    public function setTeam(?Team $teamId): static
    {
        $this->team = $teamId;

        return $this;
    }

    public function getDivision(): ?Division
    {
        return $this->division;
    }

    public function setDivision(?Division $divisionId): static
    {
        $this->division = $divisionId;

        return $this;
    }

    public function getPoints(): ?int
    {
        return $this->points;
    }

    public function setPoints(int $points): static
    {
        $this->points = $points;

        return $this;
    }

    public function getTies(): ?int
    {
        return $this->ties;
    }

    public function setTies(?int $ties): static
    {
        $this->ties = $ties;

        return $this;
    }

    public function getWinRounds(): ?int
    {
        return $this->winRounds;
    }

    public function setWinRounds(int $winRounds): static
    {
        $this->winRounds = $winRounds;

        return $this;
    }

    public function getLooseRounds(): ?int
    {
        return $this->looseRounds;
    }

    public function setLooseRounds(int $looseRounds): static
    {
        $this->looseRounds = $looseRounds;

        return $this;
    }
}
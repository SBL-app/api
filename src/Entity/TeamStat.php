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
    private ?int $win = null;

    #[ORM\Column]
    private ?int $loose = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Team $team = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Division $division = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getWin(): ?int
    {
        return $this->win;
    }

    public function setWin(int $win): static
    {
        $this->win = $win;

        return $this;
    }

    public function getLoose(): ?int
    {
        return $this->loose;
    }

    public function setLoose(int $loose): static
    {
        $this->loose = $loose;

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
}
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
    private ?Team $teamId = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Division $divisionId = null;

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

    public function getTeamId(): ?Team
    {
        return $this->teamId;
    }

    public function setTeamId(?Team $teamId): static
    {
        $this->teamId = $teamId;

        return $this;
    }

    public function getDivisionId(): ?Division
    {
        return $this->divisionId;
    }

    public function setDivisionId(?Division $divisionId): static
    {
        $this->divisionId = $divisionId;

        return $this;
    }
}
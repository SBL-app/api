<?php

namespace App\Entity;

use App\Repository\TeamsStatRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TeamsStatRepository::class)]
class TeamsStat
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?int $wins = null;

    #[ORM\Column]
    private ?int $losses = null;

    #[ORM\ManyToOne(inversedBy: 'teamsStats')]
    private ?Team $teamID = null;

    #[ORM\ManyToOne(inversedBy: 'teamsStats')]
    private ?Division $divisionID = null;

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

    public function getTeamID(): ?Team
    {
        return $this->teamID;
    }

    public function setTeamID(?Team $teamID): static
    {
        $this->teamID = $teamID;

        return $this;
    }

    public function getDivisionID(): ?Division
    {
        return $this->divisionID;
    }

    public function setDivisionID(?Division $divisionID): static
    {
        $this->divisionID = $divisionID;

        return $this;
    }
}

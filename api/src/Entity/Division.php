<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use App\Repository\DivisionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DivisionRepository::class)]
#[ApiResource]
class Division
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\ManyToOne(inversedBy: 'divisions')]
    private ?Season $seasonID = null;

    #[ORM\OneToMany(mappedBy: 'divisionID', targetEntity: TeamsStat::class)]
    private Collection $teamsStats;

    #[ORM\ManyToMany(targetEntity: Team::class)]
    private Collection $teams;

    public function __construct()
    {
        $this->teamsStats = new ArrayCollection();
        $this->teams = new ArrayCollection();
    }

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

    public function getSeasonID(): ?Season
    {
        return $this->seasonID;
    }

    public function setSeasonID(?Season $seasonID): static
    {
        $this->seasonID = $seasonID;

        return $this;
    }

    /**
     * @return Collection<int, TeamsStat>
     */
    public function getTeamsStats(): Collection
    {
        return $this->teamsStats;
    }

    public function addTeamsStat(TeamsStat $teamsStat): static
    {
        if (!$this->teamsStats->contains($teamsStat)) {
            $this->teamsStats->add($teamsStat);
            $teamsStat->setDivisionID($this);
        }

        return $this;
    }

    public function removeTeamsStat(TeamsStat $teamsStat): static
    {
        if ($this->teamsStats->removeElement($teamsStat)) {
            // set the owning side to null (unless already changed)
            if ($teamsStat->getDivisionID() === $this) {
                $teamsStat->setDivisionID(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Team>
     */
    public function getTeams(): Collection
    {
        return $this->teams;
    }

    public function addTeam(Team $team): static
    {
        if (!$this->teams->contains($team)) {
            $this->teams->add($team);
        }

        return $this;
    }

    public function removeTeam(Team $team): static
    {
        $this->teams->removeElement($team);

        return $this;
    }
}

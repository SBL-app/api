<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use App\Repository\TeamRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TeamRepository::class)]
#[ApiResource]
class Team
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\OneToMany(mappedBy: 'teamID', targetEntity: Member::class)]
    private Collection $members;

    #[ORM\OneToOne(cascade: ['persist', 'remove'])]
    private ?Member $capitainID = null;

    #[ORM\OneToMany(mappedBy: 'teamID', targetEntity: TeamsStat::class)]
    private Collection $teamsStats;

    #[ORM\OneToMany(mappedBy: 'team1', targetEntity: Game::class)]
    private Collection $games;

    public function __construct()
    {
        $this->members = new ArrayCollection();
        $this->teamsStats = new ArrayCollection();
        $this->games = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * @return Collection<int, Member>
     */
    public function getMembers(): Collection
    {
        return $this->members;
    }

    public function addMember(Member $member): static
    {
        if (!$this->members->contains($member)) {
            $this->members->add($member);
            $member->setTeamID($this);
        }

        return $this;
    }

    public function removeMember(Member $member): static
    {
        if ($this->members->removeElement($member)) {
            // set the owning side to null (unless already changed)
            if ($member->getTeamID() === $this) {
                $member->setTeamID(null);
            }
        }

        return $this;
    }

    public function getCapitainID(): ?Member
    {
        return $this->capitainID;
    }

    public function setCapitainID(?Member $capitainID): static
    {
        $this->capitainID = $capitainID;

        return $this;
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
            $teamsStat->setTeamID($this);
        }

        return $this;
    }

    public function removeTeamsStat(TeamsStat $teamsStat): static
    {
        if ($this->teamsStats->removeElement($teamsStat)) {
            // set the owning side to null (unless already changed)
            if ($teamsStat->getTeamID() === $this) {
                $teamsStat->setTeamID(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Game>
     */
    public function getGames(): Collection
    {
        return $this->games;
    }

    public function addGame(Game $game): static
    {
        if (!$this->games->contains($game)) {
            $this->games->add($game);
            $game->setTeam1($this);
        }

        return $this;
    }

    public function removeGame(Game $game): static
    {
        if ($this->games->removeElement($game)) {
            // set the owning side to null (unless already changed)
            if ($game->getTeam1() === $this) {
                $game->setTeam1(null);
            }
        }

        return $this;
    }
}

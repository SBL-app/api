<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use App\Repository\MemberRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MemberRepository::class)]
#[ApiResource]
class Member
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(length: 255)]
    private ?string $discordID = null;

    #[ORM\ManyToOne(inversedBy: 'members')]
    private ?Team $teamID = null;

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

    public function getDiscordID(): ?string
    {
        return $this->discordID;
    }

    public function setDiscordID(string $discordID): static
    {
        $this->discordID = $discordID;

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
}

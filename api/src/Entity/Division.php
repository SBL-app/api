<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use App\Repository\DivisionRepository;
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
}

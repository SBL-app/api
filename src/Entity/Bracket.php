<?php

namespace App\Entity;

use App\Repository\BracketRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: BracketRepository::class)]
class Bracket
{
    public const FORMAT_SINGLE_ELIMINATION = 'single_elimination';

    public const STATUS_DRAFT = 'draft';
    public const STATUS_READY = 'ready';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED = 'completed';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(length: 50, options: ['default' => self::FORMAT_SINGLE_ELIMINATION])]
    private string $format = self::FORMAT_SINGLE_ELIMINATION;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    private bool $hasThirdPlaceMatch = false;

    #[ORM\Column]
    private ?int $qualifiedCount = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    private ?Division $division = null;

    #[ORM\Column(length: 20, options: ['default' => self::STATUS_DRAFT])]
    private string $status = self::STATUS_DRAFT;

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

    public function getFormat(): string
    {
        return $this->format;
    }

    public function setFormat(string $format): static
    {
        $this->format = $format;

        return $this;
    }

    public function hasThirdPlaceMatch(): bool
    {
        return $this->hasThirdPlaceMatch;
    }

    public function setHasThirdPlaceMatch(bool $hasThirdPlaceMatch): static
    {
        $this->hasThirdPlaceMatch = $hasThirdPlaceMatch;

        return $this;
    }

    public function getQualifiedCount(): ?int
    {
        return $this->qualifiedCount;
    }

    public function setQualifiedCount(int $qualifiedCount): static
    {
        $this->qualifiedCount = $qualifiedCount;

        return $this;
    }

    public function getDivision(): ?Division
    {
        return $this->division;
    }

    public function setDivision(?Division $division): static
    {
        $this->division = $division;

        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
    }
}

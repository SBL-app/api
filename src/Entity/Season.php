<?php

namespace App\Entity;

use App\Repository\SeasonRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SeasonRepository::class)]
class Season
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $startDate = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $endDate = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $registrationOpenDate = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $registrationCloseDate = null;

    /**
     * @var Collection<int, Registration>
     */
    #[ORM\OneToMany(targetEntity: Registration::class, mappedBy: 'season')]
    private Collection $registrations;

    public function __construct()
    {
        $this->registrations = new ArrayCollection();
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

    public function getStartDate(): ?\DateTimeInterface
    {
        return $this->startDate;
    }

    public function setStartDate(?\DateTimeInterface $startDate): static
    {
        $this->startDate = $startDate;

        return $this;
    }

    public function getEndDate(): ?\DateTimeInterface
    {
        return $this->endDate;
    }

    public function setEndDate(?\DateTimeInterface $endDate): static
    {
        $this->endDate = $endDate;

        return $this;
    }

    public function getRegistrationOpenDate(): ?\DateTimeInterface
    {
        return $this->registrationOpenDate;
    }

    public function setRegistrationOpenDate(?\DateTimeInterface $registrationOpenDate): static
    {
        $this->registrationOpenDate = $registrationOpenDate;

        return $this;
    }

    public function getRegistrationCloseDate(): ?\DateTimeInterface
    {
        return $this->registrationCloseDate;
    }

    public function setRegistrationCloseDate(?\DateTimeInterface $registrationCloseDate): static
    {
        $this->registrationCloseDate = $registrationCloseDate;

        return $this;
    }

    /**
     * Indique si la période d'inscription est ouverte à l'instant donné.
     * Une borne nulle est considérée comme non contraignante :
     * - open null  → pas de date d'ouverture (déjà ouverte)
     * - close null → pas de date de fermeture (toujours ouverte)
     */
    public function isRegistrationOpen(?\DateTimeInterface $now = null): bool
    {
        $now = $now ?? new \DateTimeImmutable();

        if ($this->registrationOpenDate !== null && $now < $this->registrationOpenDate) {
            return false;
        }

        if ($this->registrationCloseDate !== null) {
            // Fin de journée incluse pour la date de fermeture.
            $closeEndOfDay = (new \DateTimeImmutable($this->registrationCloseDate->format('Y-m-d')))->setTime(23, 59, 59);
            if ($now > $closeEndOfDay) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return Collection<int, Registration>
     */
    public function getRegistrations(): Collection
    {
        return $this->registrations;
    }

    public function addRegistration(Registration $registration): static
    {
        if (!$this->registrations->contains($registration)) {
            $this->registrations->add($registration);
            $registration->setSeason($this);
        }

        return $this;
    }

    public function removeRegistration(Registration $registration): static
    {
        if ($this->registrations->removeElement($registration)) {
            // set the owning side to null (unless already changed)
            if ($registration->getSeason() === $this) {
                $registration->setSeason(null);
            }
        }

        return $this;
    }
}

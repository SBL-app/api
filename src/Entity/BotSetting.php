<?php

namespace App\Entity;

use App\Repository\BotSettingRepository;
use Doctrine\ORM\Mapping as ORM;

/** Réglage clé/valeur de la configuration du bot (salons, horaires, jour butoir). */
#[ORM\Entity(repositoryClass: BotSettingRepository::class)]
#[ORM\Table(name: 'bot_setting')]
class BotSetting
{
    #[ORM\Id]
    #[ORM\Column(name: 'setting_key', length: 64)]
    private string $key;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $value = null;

    public function __construct(string $key, ?string $value = null)
    {
        $this->key = $key;
        $this->value = $value;
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function getValue(): ?string
    {
        return $this->value;
    }

    public function setValue(?string $value): static
    {
        $this->value = $value;
        return $this;
    }
}

<?php

namespace App\Entity\Org;

use App\Repository\OrganisatieRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: OrganisatieRepository::class)]
#[ORM\Table(name: 'organisatie')]
class Organisatie
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $naam = null;

    #[ORM\Column(length: 255)]
    private ?string $adres = null;

    #[ORM\Column(length: 255)]
    private ?string $stad = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNaam(): ?string
    {
        return $this->naam;
    }

    public function setNaam(string $naam): static
    {
        $this->naam = $naam;
        return $this;
    }

    public function getAdres(): ?string
    {
        return $this->adres;
    }

    public function setAdres(string $adres): static
    {
        $this->adres = $adres;
        return $this;
    }

    public function getStad(): ?string
    {
        return $this->stad;
    }

    public function setStad(string $stad): static
    {
        $this->stad = $stad;
        return $this;
    }
}

// test

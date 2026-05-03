<?php

namespace App\Document;

use Doctrine\ODM\MongoDB\Mapping\Annotations as MongoDB;
use Symfony\Component\Validator\Constraints as Assert;

#[MongoDB\Document(collection: 'reservations')]
#[MongoDB\Index(keys: ['trajetId' => 'asc', 'passagerId' => 'asc'])]
class Reservation
{
    #[MongoDB\Id]
    private ?string $id = null;

    #[MongoDB\Field(type: 'string')]
    #[Assert\NotBlank]
    private ?string $trajetId = null;

    #[MongoDB\Field(type: 'string')]
    #[Assert\NotBlank]
    private ?string $passagerId = null;

    #[MongoDB\Field(type: 'int')]
    #[Assert\Positive]
    #[Assert\Range(min: 1, max: 8)]
    private int $nbPlaces = 1;

    #[MongoDB\Field(type: 'float')]
    private float $prixTotal = 0.0;

    #[MongoDB\Field(type: 'string')]
    private string $statut = 'confirmee'; // confirmeé, en_attente, terminee, annulee

    #[MongoDB\Field(type: 'date_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?string { return $this->id; }

    public function getTrajetId(): ?string { return $this->trajetId; }
    public function setTrajetId(string $trajetId): self { $this->trajetId = $trajetId; return $this; }

    public function getPassagerId(): ?string { return $this->passagerId; }
    public function setPassagerId(string $passagerId): self { $this->passagerId = $passagerId; return $this; }

    public function getNbPlaces(): int { return $this->nbPlaces; }
    public function setNbPlaces(int $nbPlaces): self { $this->nbPlaces = $nbPlaces; return $this; }

    public function getPrixTotal(): float { return $this->prixTotal; }
    public function setPrixTotal(float $prixTotal): self { $this->prixTotal = $prixTotal; return $this; }

    public function getStatut(): string { return $this->statut; }
    public function setStatut(string $statut): self { $this->statut = $statut; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}

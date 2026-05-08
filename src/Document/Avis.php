<?php

namespace App\Document;

use Doctrine\ODM\MongoDB\Mapping\Annotations as MongoDB;
use Symfony\Component\Validator\Constraints as Assert;

#[MongoDB\Document(collection: 'avis')]
#[MongoDB\Index(keys: ['destinataireId' => 'asc'])]
class Avis
{
    #[MongoDB\Id]
    private ?string $id = null;

    #[MongoDB\Field(type: 'string')]
    private ?string $auteurId = null;

    #[MongoDB\Field(type: 'string')]
    private ?string $destinataireId = null;

    #[MongoDB\Field(type: 'string')]
    private ?string $trajetId = null;

    #[MongoDB\Field(type: 'int')]
    #[Assert\Range(min: 1, max: 5)]
    private int $note = 5;

    #[MongoDB\Field(type: 'string')]
    private ?string $commentaire = null;

    /**
     * "conducteur_vers_passager" ou "passager_vers_conducteur"
     */
    #[MongoDB\Field(type: 'string')]
    private string $type;

    #[MongoDB\Field(type: 'date_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?string { return $this->id; }

    public function getAuteurId(): ?string { return $this->auteurId; }
    public function setAuteurId(string $auteurId): self { $this->auteurId = $auteurId; return $this; }

    public function getDestinataireId(): ?string { return $this->destinataireId; }
    public function setDestinataireId(string $destinataireId): self { $this->destinataireId = $destinataireId; return $this; }

    public function getTrajetId(): ?string { return $this->trajetId; }
    public function setTrajetId(string $trajetId): self { $this->trajetId = $trajetId; return $this; }

    public function getNote(): int { return $this->note; }
    public function setNote(int $note): self { $this->note = $note; return $this; }

    public function getCommentaire(): ?string { return $this->commentaire; }
    public function setCommentaire(?string $commentaire): self { $this->commentaire = $commentaire; return $this; }

    public function getType(): string { return $this->type; }
    public function setType(string $type): self { $this->type = $type; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}

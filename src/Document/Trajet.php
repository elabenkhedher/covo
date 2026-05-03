<?php

namespace App\Document;

use App\Repository\TrajetRepository;
use Doctrine\ODM\MongoDB\Mapping\Annotations as MongoDB;
use Symfony\Component\Validator\Constraints as Assert;

#[MongoDB\Document(repositoryClass: TrajetRepository::class, collection: 'trajets')]
#[MongoDB\Index(keys: ['dateDepart' => 1, 'statut' => 1], name: 'idx_date_statut')]
class Trajet
{
    #[MongoDB\Id]
    private ?string $id = null;

    /** ID du conducteur connecté (User->getId()) */
    #[MongoDB\Field(type: 'string')]
    #[MongoDB\Index]
    private string $conducteurId = '';

    #[MongoDB\Field(type: 'string')]
    #[Assert\NotBlank(message: 'La ville de départ est obligatoire.')]
    private string $villeDepart = '';

    #[MongoDB\Field(type: 'string')]
    #[Assert\NotBlank(message: "La ville d'arrivée est obligatoire.")]
    private string $villeArrivee = '';

    #[MongoDB\Field(type: 'string', nullable: true)]
    private ?string $pointRendezVous = null;

    #[MongoDB\Field(type: 'string', nullable: true)]
    private ?string $dureeEstimee = null;

    /**
     * GeoJSON Point : { "type": "Point", "coordinates": [lng, lat] }
     */
    #[MongoDB\Field(type: 'hash')]
    #[MongoDB\Index(keys: ['coordDepart.coordinates' => '2dsphere'], name: 'idx_coordDepart_2dsphere')]
    private array $coordDepart = [];

    #[MongoDB\Field(type: 'hash')]
    #[MongoDB\Index(keys: ['coordArrivee.coordinates' => '2dsphere'], name: 'idx_coordArrivee_2dsphere')]
    private array $coordArrivee = [];

    #[MongoDB\Field(type: 'date')]
    #[Assert\NotNull(message: 'La date de départ est obligatoire.')]
    private ?\DateTimeInterface $dateDepart = null;

    #[MongoDB\Field(type: 'string')]
    #[Assert\NotBlank(message: "L'heure de départ est obligatoire.")]
    #[Assert\Regex(pattern: '/^\d{2}:\d{2}$/', message: "Format attendu : HH:MM")]
    private string $heureDepart = '';

    #[MongoDB\Field(type: 'int')]
    #[Assert\Range(min: 1, max: 8, notInRangeMessage: 'Le nombre de places doit être entre {{ min }} et {{ max }}.')]
    private int $nbPlacesTotal = 1;

    #[MongoDB\Field(type: 'int')]
    private int $nbPlacesDisponibles = 1;

    #[MongoDB\Field(type: 'float')]
    #[Assert\PositiveOrZero(message: 'Le prix ne peut pas être négatif.')]
    private float $prix = 0.0;

    /**
     * ENUM : "actif" | "en_cours" | "annule" | "termine"
     */
    #[MongoDB\Field(type: 'string')]
    #[Assert\Choice(choices: ['actif', 'en_cours', 'annule', 'termine'], message: 'Statut invalide.')]
    private string $statut = 'actif';

    /**
     * { "fumeur": bool, "musique": bool, "animaux": bool }
     */
    #[MongoDB\Field(type: 'hash')]
    private array $preferences = [
        'fumeur'  => false,
        'musique' => false,
        'animaux' => false,
    ];

    #[MongoDB\Field(type: 'string', nullable: true)]
    private ?string $messagePassagers = null;

    #[MongoDB\Field(type: 'date_immutable')]
    private \DateTimeImmutable $createdAt;

    #[MongoDB\Field(type: 'date_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    // ──────────────────────────────── Getters / Setters ────────────────────────────────

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getConducteurId(): string
    {
        return $this->conducteurId;
    }

    public function setConducteurId(string $conducteurId): static
    {
        $this->conducteurId = $conducteurId;
        return $this;
    }

    public function getVilleDepart(): string
    {
        return $this->villeDepart;
    }

    public function setVilleDepart(string $villeDepart): static
    {
        $this->villeDepart = $villeDepart;
        return $this;
    }

    public function getVilleArrivee(): string
    {
        return $this->villeArrivee;
    }

    public function setVilleArrivee(string $villeArrivee): static
    {
        $this->villeArrivee = $villeArrivee;
        return $this;
    }

    public function getPointRendezVous(): ?string
    {
        return $this->pointRendezVous;
    }

    public function setPointRendezVous(?string $pointRendezVous): static
    {
        $this->pointRendezVous = $pointRendezVous;
        return $this;
    }

    public function getDureeEstimee(): ?string
    {
        return $this->dureeEstimee;
    }

    public function setDureeEstimee(?string $dureeEstimee): static
    {
        $this->dureeEstimee = $dureeEstimee;
        return $this;
    }

    public function getCoordDepart(): array
    {
        return $this->coordDepart;
    }

    public function setCoordDepart(array $coordDepart): static
    {
        $this->coordDepart = $coordDepart;
        return $this;
    }

    public function getCoordArrivee(): array
    {
        return $this->coordArrivee;
    }

    public function setCoordArrivee(array $coordArrivee): static
    {
        $this->coordArrivee = $coordArrivee;
        return $this;
    }

    public function getDateDepart(): ?\DateTimeInterface
    {
        return $this->dateDepart;
    }

    public function setDateDepart(\DateTimeInterface $dateDepart): static
    {
        $this->dateDepart = $dateDepart;
        return $this;
    }

    public function getHeureDepart(): string
    {
        return $this->heureDepart;
    }

    public function setHeureDepart(string $heureDepart): static
    {
        $this->heureDepart = $heureDepart;
        return $this;
    }

    public function getNbPlacesTotal(): int
    {
        return $this->nbPlacesTotal;
    }

    public function setNbPlacesTotal(int $nbPlacesTotal): static
    {
        $this->nbPlacesTotal = $nbPlacesTotal;
        return $this;
    }

    public function getNbPlacesDisponibles(): int
    {
        return $this->nbPlacesDisponibles;
    }

    public function setNbPlacesDisponibles(int $nbPlacesDisponibles): static
    {
        $this->nbPlacesDisponibles = $nbPlacesDisponibles;
        return $this;
    }

    public function getPrix(): float
    {
        return $this->prix;
    }

    public function setPrix(float $prix): static
    {
        $this->prix = $prix;
        return $this;
    }

    public function getStatut(): string
    {
        return $this->statut;
    }

    public function setStatut(string $statut): static
    {
        $this->statut = $statut;
        return $this;
    }

    public function getPreferences(): array
    {
        return $this->preferences;
    }

    public function setPreferences(array $preferences): static
    {
        $this->preferences = array_merge($this->preferences, $preferences);
        return $this;
    }

    public function getMessagePassagers(): ?string
    {
        return $this->messagePassagers;
    }

    public function setMessagePassagers(?string $messagePassagers): static
    {
        $this->messagePassagers = $messagePassagers;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    public function getPlacesReservees(): int
    {
        return $this->nbPlacesTotal - $this->nbPlacesDisponibles;
    }

    public function getPlacesTotales(): int
    {
        return $this->nbPlacesTotal;
    }

    public function getPlacesDisponibles(): int
    {
        return $this->nbPlacesDisponibles;
    }

    /**
     * Sérialisation pour les réponses JSON (appelée dans le contrôleur).
     */
    public function toArray(): array
    {
        return [
            'id'                  => $this->id,
            'conducteurId'        => $this->conducteurId,
            'villeDepart'         => $this->villeDepart,
            'villeArrivee'        => $this->villeArrivee,
            'pointRendezVous'     => $this->pointRendezVous,
            'dureeEstimee'        => $this->dureeEstimee,
            'coordDepart'         => $this->coordDepart,
            'coordArrivee'        => $this->coordArrivee,
            'dateDepart'          => $this->dateDepart?->format('Y-m-d'),
            'heureDepart'         => $this->heureDepart,
            'nbPlacesTotal'       => $this->nbPlacesTotal,
            'nbPlacesDisponibles' => $this->nbPlacesDisponibles,
            'prix'                => $this->prix,
            'statut'              => $this->statut,
            'preferences'         => $this->preferences,
            'messagePassagers'    => $this->messagePassagers,
            'createdAt'           => $this->createdAt->format(\DateTimeInterface::ATOM),
            'updatedAt'           => $this->updatedAt->format(\DateTimeInterface::ATOM),
        ];
    }
}

<?php

namespace App\Document;

use App\Repository\UserRepository;
use Doctrine\ODM\MongoDB\Mapping\Annotations as MongoDB;
use Doctrine\Bundle\MongoDBBundle\Validator\Constraints\Unique as UniqueDocument;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[MongoDB\Document(repositoryClass: UserRepository::class, collection: 'users')]
#[UniqueDocument(fields: ['email'], message: 'There is already an account with this email')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[MongoDB\Id]
    private ?string $id = null;

    #[MongoDB\Field(type: 'string')]
    #[MongoDB\Index(unique: true)]
    private ?string $email = null;

    /**
     * @var list<string> The user roles
     */
    #[MongoDB\Field(type: 'collection')]
    private array $roles = [];

    /**
     * @var string The hashed password
     */
    #[MongoDB\Field(type: 'string')]
    private ?string $password = null;

    #[MongoDB\Field(type: 'string')]
    private ?string $prenom = null;

    #[MongoDB\Field(type: 'string')]
    private ?string $nom = null;

    #[MongoDB\Field(type: 'string')]
    private ?string $telephone = null;

    // Infos véhicule pour conducteurs
    #[MongoDB\Field(type: 'string')]
    private ?string $marqueVehicule = null;

    #[MongoDB\Field(type: 'string')]
    private ?string $immatriculation = null;

    #[MongoDB\Field(type: 'string')]
    private ?string $couleur = null;

    #[MongoDB\Field(type: 'int')]
    private ?int $nbPlacesVehicule = null;

    #[MongoDB\Field(type: 'string', nullable: true)]
    private ?string $bio = null;

    /** Note moyenne reçue (calculée lors de chaque nouvelle notation) */
    #[MongoDB\Field(type: 'float')]
    private float $noteMoyenne = 0.0;

    /** Nombre total de notations reçues (pour calculer la moyenne pondérée) */
    #[MongoDB\Field(type: 'int')]
    private int $nbNotations = 0;

    #[MongoDB\Field(type: 'bool')]
    private bool $prefNonFumeur = true;

    #[MongoDB\Field(type: 'bool')]
    private bool $prefMusique = false;

    #[MongoDB\Field(type: 'bool')]
    private bool $prefAnimaux = false;

    #[MongoDB\Field(type: 'bool')]
    private bool $prefDiscussion = false;

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    /**
     * @see UserInterface
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // guarantee every user at least has ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    /**
     * @param list<string> $roles
     */
    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    /**
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    public function getPrenom(): ?string { return $this->prenom; }
    public function setPrenom(?string $prenom): self { $this->prenom = $prenom; return $this; }

    public function getNom(): ?string { return $this->nom; }
    public function setNom(?string $nom): self { $this->nom = $nom; return $this; }

    public function getTelephone(): ?string { return $this->telephone; }
    public function setTelephone(?string $telephone): self { $this->telephone = $telephone; return $this; }

    public function getMarqueVehicule(): ?string { return $this->marqueVehicule; }
    public function setMarqueVehicule(?string $marqueVehicule): self { $this->marqueVehicule = $marqueVehicule; return $this; }

    public function getImmatriculation(): ?string { return $this->immatriculation; }
    public function setImmatriculation(?string $immatriculation): self { $this->immatriculation = $immatriculation; return $this; }

    public function getCouleur(): ?string { return $this->couleur; }
    public function setCouleur(?string $couleur): self { $this->couleur = $couleur; return $this; }

    public function getNbPlacesVehicule(): ?int { return $this->nbPlacesVehicule; }
    public function setNbPlacesVehicule(?int $nbPlacesVehicule): self { $this->nbPlacesVehicule = $nbPlacesVehicule; return $this; }

    public function getBio(): ?string { return $this->bio; }
    public function setBio(?string $bio): self { $this->bio = $bio; return $this; }

    public function isPrefNonFumeur(): bool { return $this->prefNonFumeur; }
    public function setPrefNonFumeur(bool $prefNonFumeur): self { $this->prefNonFumeur = $prefNonFumeur; return $this; }

    public function isPrefMusique(): bool { return $this->prefMusique; }
    public function setPrefMusique(bool $prefMusique): self { $this->prefMusique = $prefMusique; return $this; }

    public function isPrefAnimaux(): bool { return $this->prefAnimaux; }
    public function setPrefAnimaux(bool $prefAnimaux): self { $this->prefAnimaux = $prefAnimaux; return $this; }

    public function isPrefDiscussion(): bool { return $this->prefDiscussion; }
    public function setPrefDiscussion(bool $prefDiscussion): self { $this->prefDiscussion = $prefDiscussion; return $this; }

    public function getNoteMoyenne(): float { return $this->noteMoyenne; }
    public function setNoteMoyenne(float $noteMoyenne): self { $this->noteMoyenne = $noteMoyenne; return $this; }

    public function getNbNotations(): int { return $this->nbNotations; }
    public function setNbNotations(int $nbNotations): self { $this->nbNotations = $nbNotations; return $this; }

    /**
     * Met à jour la note moyenne de façon incrémentale.
     */
    public function ajouterNotation(float $nouvelleNote): void
    {
        $this->noteMoyenne = (($this->noteMoyenne * $this->nbNotations) + $nouvelleNote) / ($this->nbNotations + 1);
        $this->nbNotations++;
    }

    public function getFullName(): string
    {
        return $this->prenom . ' ' . $this->nom;
    }

    public function getInitials(): string
    {
        return strtoupper(substr($this->prenom, 0, 1) . substr($this->nom, 0, 1));
    }

    /**
     * Ensure the session doesn't contain actual password hashes by CRC32C-hashing them, as supported since Symfony 7.3.
     */
    public function __serialize(): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'roles' => $this->roles,
            'password' => $this->password,
            'prenom' => $this->prenom,
            'nom' => $this->nom,
        ];
    }

    public function __unserialize(array $data): void
    {
        $this->id = $data['id'] ?? null;
        $this->email = $data['email'] ?? null;
        $this->roles = $data['roles'] ?? [];
        $this->password = $data['password'] ?? null;
        $this->prenom = $data['prenom'] ?? null;
        $this->nom = $data['nom'] ?? null;
    }
}

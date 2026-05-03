<?php

namespace App\Service;

use App\Document\Trajet;
use App\Repository\TrajetRepository;
use Doctrine\ODM\MongoDB\DocumentManager;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Bundle\SecurityBundle\Security;
use App\Document\User;

class TrajetService
{
    public function __construct(
        private readonly DocumentManager               $dm,
        private readonly TrajetRepository             $trajetRepo,
        private readonly Security                     $security,
        private readonly AuthorizationCheckerInterface $authChecker,
    ) {}

    // ─────────────────────────────────────────────────────────────────
    //  1. Publier un trajet
    // ─────────────────────────────────────────────────────────────────

    /**
     * Crée et persiste un nouveau Trajet.
     *
     * @throws AccessDeniedException si l'utilisateur n'a pas ROLE_CONDUCTEUR
     */
    public function publierTrajet(array $data): Trajet
    {
        if (!$this->authChecker->isGranted('ROLE_CONDUCTEUR')) {
            throw new AccessDeniedException('Seuls les conducteurs peuvent publier un trajet.');
        }

        /** @var User $user */
        $user = $this->security->getUser();

        $trajet = new Trajet();
        $trajet->setConducteurId((string) $user->getId());

        $this->hydrateTrajet($trajet, $data);

        // nbPlacesDisponibles = nbPlacesTotal à la création
        $trajet->setNbPlacesDisponibles($trajet->getNbPlacesTotal());

        $this->dm->persist($trajet);
        $this->dm->flush();

        return $trajet;
    }

    // ─────────────────────────────────────────────────────────────────
    //  2. Modifier un trajet
    // ─────────────────────────────────────────────────────────────────

    /**
     * Met à jour les champs modifiables d'un trajet existant.
     *
     * @throws \RuntimeException      si le trajet n'existe pas
     * @throws AccessDeniedException  si le conducteur n'est pas propriétaire
     * @throws \LogicException        si le statut n'est pas "actif"
     */
    public function modifierTrajet(string $id, array $data): Trajet
    {
        $trajet = $this->getTrajetOuException($id);
        $this->verifierProprietaire($trajet);

        if ($trajet->getStatut() !== 'actif') {
            throw new \LogicException('Seul un trajet actif peut être modifié.');
        }

        // Champs modifiables uniquement
        $modifiables = [
            'villeDepart', 'villeArrivee', 'dateDepart',
            'heureDepart', 'prix', 'preferences', 'messagePassagers',
        ];

        $filtered = array_intersect_key($data, array_flip($modifiables));
        $this->hydrateTrajet($trajet, $filtered);

        $trajet->setUpdatedAt(new \DateTimeImmutable());

        $this->dm->flush();

        return $trajet;
    }

    // ─────────────────────────────────────────────────────────────────
    //  3. Annuler un trajet
    // ─────────────────────────────────────────────────────────────────

    /**
     * Passe le statut à "annulé".
     *
     * @throws \RuntimeException     si le trajet n'existe pas
     * @throws AccessDeniedException si le conducteur n'est pas propriétaire
     */
    public function annulerTrajet(string $id): void
    {
        $trajet = $this->getTrajetOuException($id);
        $this->verifierProprietaire($trajet);

        $trajet->setStatut('annule');
        $trajet->setUpdatedAt(new \DateTimeImmutable());

        // TODO : déclencher le remboursement des passagers réservés
        // $this->reservationService->rembourserPassagers($trajet);

        $this->dm->flush();
    }

    // ─────────────────────────────────────────────────────────────────
    //  4. Mes trajets (conducteur connecté)
    // ─────────────────────────────────────────────────────────────────

    /**
     * @return Trajet[]
     */
    public function getMesTrajets(): array
    {
        /** @var User $user */
        $user = $this->security->getUser();

        return $this->trajetRepo->findByConducteur((string) $user->getId());
    }

    // ─────────────────────────────────────────────────────────────────
    //  5. Rechercher des trajets
    // ─────────────────────────────────────────────────────────────────

    /**
     * Recherche multi-critères avec support géospatial.
     *
     * Clés acceptées dans $filtres :
     *   villeDepart, villeArrivee, date (string Y-m-d),
     *   nbPlaces (int), prixMax (float),
     *   lng + lat + rayon (km) pour recherche géospatiale.
     *
     * @return Trajet[]
     */
    public function rechercherTrajets(array $filtres): array
    {
        // Convertir la date string en objet DateTime si fournie
        if (!empty($filtres['date'])) {
            try {
                $filtres['date'] = new \DateTime($filtres['date']);
            } catch (\Exception) {
                unset($filtres['date']);
            }
        }

        // Construire coordDepart si lng+lat présents
        if (!empty($filtres['lng']) && !empty($filtres['lat'])) {
            $filtres['coordDepart'] = [(float) $filtres['lng'], (float) $filtres['lat']];
        }

        return $this->trajetRepo->findByFilters($filtres);
    }

    // ─────────────────────────────────────────────────────────────────
    //  6. Détail d'un trajet
    // ─────────────────────────────────────────────────────────────────

    /**
     * @throws \RuntimeException si le trajet n'existe pas
     */
    public function getTrajetDetail(string $id): Trajet
    {
        return $this->getTrajetOuException($id);
    }

    // ─────────────────────────────────────────────────────────────────
    //  Helpers privés
    // ─────────────────────────────────────────────────────────────────

    /**
     * Hydrate un Trajet à partir d'un tableau de données.
     * Champs gérés : villeDepart, villeArrivee, coordDepart, coordArrivee,
     *   dateDepart, heureDepart, nbPlacesTotal, prix, preferences, messagePassagers.
     */
    private function hydrateTrajet(Trajet $trajet, array $data): void
    {
        if (isset($data['villeDepart'])) {
            $trajet->setVilleDepart((string) $data['villeDepart']);
        }
        if (isset($data['villeArrivee'])) {
            $trajet->setVilleArrivee((string) $data['villeArrivee']);
        }
        if (isset($data['coordDepart']) && is_array($data['coordDepart'])) {
            // Accepte { type, coordinates } ou [lng, lat] directement
            if (isset($data['coordDepart']['coordinates'])) {
                $trajet->setCoordDepart($data['coordDepart']);
            } else {
                $trajet->setCoordDepart([
                    'type'        => 'Point',
                    'coordinates' => $data['coordDepart'],
                ]);
            }
        }
        if (isset($data['coordArrivee']) && is_array($data['coordArrivee'])) {
            if (isset($data['coordArrivee']['coordinates'])) {
                $trajet->setCoordArrivee($data['coordArrivee']);
            } else {
                $trajet->setCoordArrivee([
                    'type'        => 'Point',
                    'coordinates' => $data['coordArrivee'],
                ]);
            }
        }
        if (isset($data['dateDepart'])) {
            $date = $data['dateDepart'] instanceof \DateTimeInterface
                ? $data['dateDepart']
                : new \DateTime($data['dateDepart']);
            $trajet->setDateDepart($date);
        }
        if (isset($data['heureDepart'])) {
            $trajet->setHeureDepart((string) $data['heureDepart']);
        }
        if (isset($data['nbPlacesTotal'])) {
            $trajet->setNbPlacesTotal((int) $data['nbPlacesTotal']);
        }
        if (isset($data['prix'])) {
            $trajet->setPrix((float) $data['prix']);
        }
        if (isset($data['preferences']) && is_array($data['preferences'])) {
            $trajet->setPreferences($data['preferences']);
        }
        if (array_key_exists('messagePassagers', $data)) {
            $trajet->setMessagePassagers($data['messagePassagers']);
        }
    }

    /**
     * Récupère un trajet par ID ou lève une RuntimeException.
     */
    private function getTrajetOuException(string $id): Trajet
    {
        $trajet = $this->dm->find(Trajet::class, $id);

        if (!$trajet instanceof Trajet) {
            throw new \RuntimeException("Trajet introuvable (id: $id).");
        }

        return $trajet;
    }

    /**
     * Vérifie que le trajet appartient bien au conducteur connecté.
     *
     * @throws AccessDeniedException
     */
    private function verifierProprietaire(Trajet $trajet): void
    {
        /** @var User $user */
        $user = $this->security->getUser();

        if ($trajet->getConducteurId() !== (string) $user->getId()) {
            throw new AccessDeniedException('Accès refusé : vous n\'êtes pas le conducteur de ce trajet.');
        }
    }
}

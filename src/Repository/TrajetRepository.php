<?php

namespace App\Repository;

use App\Document\Trajet;
use Doctrine\Bundle\MongoDBBundle\ManagerRegistry;
use Doctrine\Bundle\MongoDBBundle\Repository\ServiceDocumentRepository;

/**
 * @extends ServiceDocumentRepository<Trajet>
 */
class TrajetRepository extends ServiceDocumentRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Trajet::class);
    }

    /**
     * Recherche multi-critères avec support géospatial.
     *
     * Filtres acceptés :
     *   - villeDepart  (string)   → regex insensible à la casse
     *   - villeArrivee (string)   → regex insensible à la casse
     *   - date         (\DateTimeInterface) → jour exact
     *   - nbPlaces     (int)      → nbPlacesDisponibles >= valeur
     *   - prixMax      (float)    → prix <= valeur
     *   - coordDepart  (array [lng, lat])
     *   - rayon        (float, km) → recherche $near 2dsphere
     *
     * @return Trajet[]
     */
    public function findByFilters(array $filtres): array
    {
        $qb = $this->createQueryBuilder();

        // Toujours : seulement les trajets actifs à venir et non masqués
        $qb->field('statut')->equals('actif');
        $qb->field('dateDepart')->gte(new \DateTime('today'));
        $qb->field('masque')->notEqual(true);

        if (!empty($filtres['villeDepart'])) {
            $qb->field('villeDepart')->equals(
                new \MongoDB\BSON\Regex($filtres['villeDepart'], 'i')
            );
        }

        if (!empty($filtres['villeArrivee'])) {
            $qb->field('villeArrivee')->equals(
                new \MongoDB\BSON\Regex($filtres['villeArrivee'], 'i')
            );
        }

        if (!empty($filtres['date']) && $filtres['date'] instanceof \DateTimeInterface) {
            $dateDebut = \DateTime::createFromInterface($filtres['date'])
                ->setTime(0, 0, 0);
            $dateFin   = (clone $dateDebut)->modify('+1 day');
            $qb->field('dateDepart')->gte($dateDebut)->lt($dateFin);
        }

        if (isset($filtres['nbPlaces']) && $filtres['nbPlaces'] > 0) {
            $qb->field('nbPlacesDisponibles')->gte((int) $filtres['nbPlaces']);
        }

        if (isset($filtres['prix_max']) && $filtres['prix_max'] >= 0) {
            $qb->field('prix')->lte((float) $filtres['prix_max']);
        } elseif (isset($filtres['prixMax']) && $filtres['prixMax'] >= 0) {
            $qb->field('prix')->lte((float) $filtres['prixMax']);
        }

        if (!empty($filtres['heure'])) {
            if ($filtres['heure'] === 'matin') {
                $qb->field('heureDepart')->lt('12:00');
            } elseif ($filtres['heure'] === 'aprem') {
                $qb->field('heureDepart')->gte('12:00');
            }
        }

        if (!empty($filtres['nofumeur'])) {
            $qb->field('preferences.fumeur')->equals(false);
        }

        // Recherche géospatiale $near avec index 2dsphere
        if (!empty($filtres['coordDepart']) && !empty($filtres['rayon'])) {
            [$lng, $lat] = $filtres['coordDepart'];
            $rayonMetres  = (float) $filtres['rayon'] * 1000;

            $qb->field('coordDepart')->geoNear((float) $lng, (float) $lat)
               ->maxDistance($rayonMetres / 6378137); // radians pour $nearSphere
            // Alternative via $near/$geoNear natif (voir findNearby())
        }

        if (!empty($filtres['sort'])) {
            if ($filtres['sort'] === 'prix_asc') {
                $qb->sort('prix', 'ASC');
            } elseif ($filtres['sort'] === 'prix_desc') {
                $qb->sort('prix', 'DESC');
            } elseif ($filtres['sort'] === 'depart') {
                $qb->sort('dateDepart', 'ASC')->sort('heureDepart', 'ASC');
            }
        } else {
            $qb->sort('dateDepart', 'ASC')->sort('heureDepart', 'ASC');
        }

        return $qb->getQuery()->execute()->toArray();
    }

    /**
     * Récupère tous les trajets d'un conducteur triés par date décroissante.
     *
     * @return Trajet[]
     */
    public function findByConducteur(string $conducteurId): array
    {
        return $this->createQueryBuilder()
            ->field('conducteurId')->equals($conducteurId)
            ->sort('dateDepart', 'DESC')
            ->getQuery()
            ->execute()
            ->toArray();
    }

    /**
     * Recherche géospatiale $near : trajets actifs proches d'un point donné.
     *
     * @return Trajet[]
     */
    public function findNearby(float $lng, float $lat, float $rayonKm): array
    {
        $rayonMetres = $rayonKm * 1000;

        return $this->createQueryBuilder()
            ->field('statut')->equals('actif')
            ->field('dateDepart')->gte(new \DateTime('today'))
            ->field('masque')->notEqual(true)
            ->field('coordDepart')->near($lng, $lat)
                ->maxDistance($rayonMetres / 6378137) // distance en radians
            ->sort('dateDepart', 'ASC')
            ->getQuery()
            ->execute()
            ->toArray();
    }

    /**
     * Récupère tous les trajets pour l'administration avec filtres optionnels.
     *
     * @return Trajet[]
     */
    public function findAllForAdmin(array $filtres = []): array
    {
        $qb = $this->createQueryBuilder();

        if (!empty($filtres['statut'])) {
            $qb->field('statut')->equals($filtres['statut']);
        }

        if (isset($filtres['masque'])) {
            $qb->field('masque')->equals((bool) $filtres['masque']);
        }

        if (!empty($filtres['conducteurId'])) {
            $qb->field('conducteurId')->equals($filtres['conducteurId']);
        }

        if (!empty($filtres['villeDepart'])) {
            $qb->field('villeDepart')->equals(
                new \MongoDB\BSON\Regex($filtres['villeDepart'], 'i')
            );
        }

        if (!empty($filtres['villeArrivee'])) {
            $qb->field('villeArrivee')->equals(
                new \MongoDB\BSON\Regex($filtres['villeArrivee'], 'i')
            );
        }

        if (!empty($filtres['dateDepuis']) && $filtres['dateDepuis'] instanceof \DateTimeInterface) {
            $qb->field('dateDepart')->gte($filtres['dateDepuis']);
        }

        $qb->sort('createdAt', 'DESC');

        return $qb->getQuery()->execute()->toArray();
    }
}
 
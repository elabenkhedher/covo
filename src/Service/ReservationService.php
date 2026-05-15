<?php

namespace App\Service;

use App\Document\Reservation;
use App\Document\Trajet;
use Doctrine\ODM\MongoDB\DocumentManager;
use Symfony\Bundle\SecurityBundle\Security;

class ReservationService
{
    public function __construct(
        private readonly DocumentManager $dm,
        private readonly Security $security
    ) {}

    /**
     * Crée une réservation et déduit les places du trajet
     */
    public function reserver(string $trajetId, int $nbPlaces): Reservation
    {
        $trajet = $this->dm->find(Trajet::class, $trajetId);
        if (!$trajet) {
            throw new \RuntimeException("Trajet introuvable.");
        }

        if ($trajet->getStatut() !== 'actif') {
            throw new \RuntimeException("Ce trajet n'est plus actif.");
        }

        if ($trajet->getNbPlacesDisponibles() < $nbPlaces) {
            throw new \RuntimeException("Plus assez de places disponibles.");
        }

        $user = $this->security->getUser();
        if (!$user) {
            throw new \RuntimeException("Utilisateur non authentifié.");
        }

        if ($trajet->getConducteurId() === (string)$user->getId()) {
            throw new \RuntimeException("Vous ne pouvez pas réserver votre propre trajet.");
        }

        // Création de la réservation
        $reservation = new Reservation();
        $reservation->setTrajetId($trajetId);
        $reservation->setPassagerId((string)$user->getId());
        $reservation->setNbPlaces($nbPlaces);
        $reservation->setPrixTotal($nbPlaces * $trajet->getPrix());
        $reservation->setStatut('en_attente_paiement');

        // Mise à jour du trajet
        $trajet->setNbPlacesDisponibles($trajet->getNbPlacesDisponibles() - $nbPlaces);

        $this->dm->persist($reservation);
        $this->dm->flush();

        return $reservation;
    }

    /**
     * Annule une réservation et rend les places au trajet
     */
    public function annuler(string $reservationId): void
    {
        $reservation = $this->dm->find(Reservation::class, $reservationId);
        if (!$reservation) {
            throw new \RuntimeException("Réservation introuvable.");
        }

        $user = $this->security->getUser();
        if ($reservation->getPassagerId() !== (string)$user->getId()) {
            throw new \RuntimeException("Accès refusé.");
        }

        if ($reservation->getStatut() === 'annulé') {
            return;
        }

        $trajet = $this->dm->find(Trajet::class, $reservation->getTrajetId());
        if ($trajet) {
            $trajet->setNbPlacesDisponibles($trajet->getNbPlacesDisponibles() + $reservation->getNbPlaces());
        }

        $reservation->setStatut('annulee');
        $this->dm->flush();
    }

    /**
     * Récupère les réservations de l'utilisateur connecté
     */
    public function getMesReservations(): array
    {
        $user = $this->security->getUser();
        return $this->dm->getRepository(Reservation::class)->findBy(
            ['passagerId' => (string)$user->getId()],
            ['createdAt' => 'DESC']
        );
    }
}

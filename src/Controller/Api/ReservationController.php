<?php

namespace App\Controller\Api;

use App\Service\ReservationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/reservations', name: 'api_reservations_')]
#[IsGranted('ROLE_USER')]
class ReservationController extends AbstractController
{
    public function __construct(
        private readonly ReservationService $reservationService
    ) {}

    #[Route('', name: 'reserver', methods: ['POST'])]
    public function reserver(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['trajetId']) || !isset($data['nbPlaces'])) {
            return $this->json([
                'success' => false,
                'message' => 'Données manquantes (trajetId, nbPlaces).'
            ], 400);
        }

        try {
            $reservation = $this->reservationService->reserver(
                $data['trajetId'],
                (int) $data['nbPlaces']
            );

            return $this->json([
                'success' => true,
                'message' => 'Réservation confirmée.',
                'data' => [
                    'id' => $reservation->getId(),
                    'prixTotal' => $reservation->getPrixTotal()
                ]
            ]);
        } catch (\RuntimeException $e) {
            return $this->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    #[Route('/{id}/annuler', name: 'annuler', methods: ['DELETE'])]
    public function annuler(string $id): JsonResponse
    {
        try {
            $this->reservationService->annuler($id);
            return $this->json([
                'success' => true,
                'message' => 'Réservation annulée.'
            ]);
        } catch (\RuntimeException $e) {
            return $this->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }
}

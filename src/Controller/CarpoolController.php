<?php

namespace App\Controller;

use App\Service\CarpoolingAIService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/carpool', name: 'api_carpool_')]
class CarpoolController extends AbstractController
{
    public function __construct(
        private readonly CarpoolingAIService $aiService
    ) {}

    
    #[Route('/price', name: 'estimate_price', methods: ['POST'])]
    public function estimatePrice(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return $this->json(['error' => 'Corps JSON invalide'], 400);
        }

        $result = $this->aiService->estimatePrice(
            distanceKm:     (float) ($data['distance_km'] ?? 0),
            durationMinutes:(float) ($data['duration_minutes'] ?? 0),
            nbPassengers:   (int)   ($data['nb_passengers'] ?? 1),
            vehicleType:            $data['vehicle_type'] ?? 'berline'
        );

        if (!$result) {
            return $this->json(['error' => 'Service IA indisponible'], 503);
        }

        return $this->json($result);
    }

    
    #[Route('/match', name: 'match_passengers', methods: ['POST'])]
    public function matchPassengers(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['driver'], $data['passengers'])) {
            return $this->json(['error' => "Champs 'driver' et 'passengers' requis"], 400);
        }

        $result = $this->aiService->matchPassengers(
            driver:     $data['driver'],
            passengers: $data['passengers']
        );

        if (!$result) {
            return $this->json(['error' => 'Service IA indisponible'], 503);
        }

        return $this->json($result);
    }

   
    #[Route('/health', name: 'health', methods: ['GET'])]
    public function health(): JsonResponse
    {
        $isHealthy = $this->aiService->isHealthy();
        return $this->json([
            'ai_service' => $isHealthy ? 'ok' : 'unavailable'
        ], $isHealthy ? 200 : 503);
    }
}

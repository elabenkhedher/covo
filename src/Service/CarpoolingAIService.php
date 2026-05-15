<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Psr\Log\LoggerInterface;

class CarpoolingAIService
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        #[Autowire('%env(FLASK_API_URL)%')]
        private readonly string $flaskApiUrl,
        private readonly LoggerInterface $logger
    ) {}

    // Estimation du prix du trajet via Flask
    public function estimatePrice(float $distanceKm, float $durationMinutes, int $nbPassengers, string $vehicleType): ?array
    {
        try {
            $response = $this->httpClient->request('POST', $this->flaskApiUrl . '/api/estimate-price', [
                'json' => compact('distance_km', 'duration_minutes', 'nb_passengers', 'vehicle_type'),
            ]);
            return $response->getStatusCode() === 200 ? $response->toArray() : null;
        } catch (\Exception $e) {
            $this->logger->error('IA Error (Price): ' . $e->getMessage());
            return null;
        }
    }

    // Matching intelligent conducteurs/passagers via Flask
    public function matchPassengers(array $driver, array $passengers): ?array
    {
        try {
            $response = $this->httpClient->request('POST', $this->flaskApiUrl . '/api/match-passengers', [
                'json' => compact('driver', 'passengers'),
            ]);
            return $response->getStatusCode() === 200 ? $response->toArray() : null;
        } catch (\Exception $e) {
            $this->logger->error('IA Error (Match): ' . $e->getMessage());
            return null;
        }
    }

    // Vérifie si le service Flask est opérationnel
    public function isHealthy(): bool
    {
        try {
            return $this->httpClient->request('GET', $this->flaskApiUrl . '/health')->getStatusCode() === 200;
        } catch (\Exception) { return false; }
    }

    // Pose une question à l'assistant IA (RAG) via Flask
    public function askAssistant(string $question, array $context): ?string
    {
        try {
            $response = $this->httpClient->request('POST', $this->flaskApiUrl . '/api/assistant', [
                'json' => compact('question', 'context'),
            ]);
            if ($response->getStatusCode() !== 200) return "Assistant indisponible.";
            return $response->toArray()['answer'] ?? "Pas de réponse.";
        } catch (\Exception $e) {
            $this->logger->error('IA Error (Assistant): ' . $e->getMessage());
            return "Erreur communication IA.";
        }
    }
}
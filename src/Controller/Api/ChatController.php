<?php

namespace App\Controller\Api;

use App\Repository\TrajetRepository;
use App\Service\CarpoolingAIService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api')]
class ChatController extends AbstractController
{
    public function __construct(
        private readonly CarpoolingAIService $aiService,
        private readonly TrajetRepository $trajetRepository
    ) {}

    // Route pour gérer les questions de l'assistant IA
    #[Route('/chat', name: 'api_chat', methods: ['POST'])]
    public function chat(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            $question = $data['message'] ?? '';

            if (empty($question)) {
                return new JsonResponse(['error' => 'Message vide'], 400);
            }

            // Récupère les données du site pour donner du contexte à l'IA
            $context = $this->getSiteContext();

            // Envoie la question et le contexte au service Flask
            $answer = $this->aiService->askAssistant($question, $context);

            return new JsonResponse(['answer' => $answer]);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'Erreur interne', 'details' => $e->getMessage()], 500);
        }
    }

    // Génère un résumé des trajets et statistiques pour l'IA
    private function getSiteContext(): array
    {
        $recentTrajets = $this->trajetRepository->findByFilters(['nbPlaces' => 1]);
        $trajetsSummary = "";
        $villes = [];
        $prices = [];

        foreach (array_slice($recentTrajets, 0, 10) as $t) {
            $trajetsSummary .= sprintf(
                "- %s -> %s (%s à %s, %s places, %.2f€)\n",
                $t->getVilleDepart(), $t->getVilleArrivee(),
                $t->getDateDepart()->format('d/m/Y'), $t->getHeureDepart(),
                $t->getNbPlacesDisponibles(), $t->getPrix()
            );
            $villes[] = $t->getVilleArrivee();
            $prices[] = $t->getPrix();
        }

        return [
            'trajets_summary' => $trajetsSummary ?: "Aucun trajet disponible.",
            'stats' => [
                'total_trajets' => count($recentTrajets),
                'top_villes' => implode(', ', array_slice(array_unique($villes), 0, 5)),
                'avg_price' => round(count($prices) > 0 ? array_sum($prices) / count($prices) : 0, 2)
            ]
        ];
    }
}

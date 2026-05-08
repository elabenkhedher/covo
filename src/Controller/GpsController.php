<?php

namespace App\Controller;

use App\Document\Reservation;
use App\Document\Trajet;
use Doctrine\ODM\MongoDB\DocumentManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class GpsController extends AbstractController
{
    public function __construct(
        private readonly DocumentManager $dm
    ) {}

    // Called by the conductor's browser to send his GPS position
    #[Route('/gps/update', name: 'app_gps_update', methods: ['POST'])]
    #[IsGranted('ROLE_CONDUCTEUR')]
    public function update(Request $request, HubInterface $hub): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['lat'], $data['lng'], $data['trajetId'])) {
            return new JsonResponse(['error' => 'Missing fields'], 400);
        }

        $trajet = $this->dm->find(Trajet::class, $data['trajetId']);
        if (!$trajet || $trajet->getConducteurId() !== (string) $this->getUser()->getId()) {
            return new JsonResponse(['error' => 'Unauthorized'], 403);
        }

        // Save last known position to MongoDB
        $trajet->setCurrentLocation([
            'lat'       => (float) $data['lat'],
            'lng'       => (float) $data['lng'],
            'speed'     => (int) ($data['speed'] ?? 0),
            'updatedAt' => time(),
        ]);
        $this->dm->flush();

        // Also broadcast via Mercure for real-time subscribers
        $update = new Update(
            'gps/trajet/' . $data['trajetId'],
            json_encode([
                'lat'       => (float) $data['lat'],
                'lng'       => (float) $data['lng'],
                'speed'     => (int) ($data['speed'] ?? 0),
                'timestamp' => time(),
            ])
        );
        $hub->publish($update);

        return new JsonResponse(['status' => 'ok']);
    }
}
<?php

namespace App\Controller;

use App\Document\Trajet;
use App\Service\TrajetService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/trajets', name: 'api_trajets_')]
class TrajetController extends AbstractController
{
    public function __construct(
        private readonly TrajetService      $trajetService,
        private readonly ValidatorInterface $validator,
    ) {}

    // ─────────────────────────────────────────────────────────────────
    //  POST /api/trajets  → publier un trajet
    // ─────────────────────────────────────────────────────────────────

    #[Route('', name: 'publier', methods: ['POST'])]
    #[IsGranted('ROLE_CONDUCTEUR')]
    public function publier(Request $request): JsonResponse
    {
        try {
            $data = $this->getJsonBody($request);
        } catch (\JsonException $e) {
            return $this->errorResponse('Corps JSON invalide.', [], Response::HTTP_BAD_REQUEST);
        }

        try {
            $trajet = $this->trajetService->publierTrajet($data);
        } catch (\Symfony\Component\Security\Core\Exception\AccessDeniedException $e) {
            return $this->errorResponse($e->getMessage(), [], Response::HTTP_FORBIDDEN);
        }

        // Validation Symfony Validator
        $erreurs = $this->validerDocument($trajet);
        if ($erreurs) {
            // Rollback : supprimer le document déjà persisté si invalide
            // (En pratique on valide AVANT persist dans le service — ici on laisse le service gérer)
            return $this->errorResponse('Données invalides.', $erreurs, Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->successResponse(
            $trajet->toArray(),
            'Trajet publié avec succès.',
            Response::HTTP_CREATED
        );
    }

    // ─────────────────────────────────────────────────────────────────
    //  GET /api/trajets  → rechercher des trajets (PUBLIC)
    // ─────────────────────────────────────────────────────────────────

    #[Route('', name: 'rechercher', methods: ['GET'])]
    public function rechercher(Request $request): JsonResponse
    {
        $filtres = [
            'villeDepart'  => $request->query->get('villeDepart'),
            'villeArrivee' => $request->query->get('villeArrivee'),
            'date'         => $request->query->get('date'),
            'nbPlaces'     => $request->query->getInt('nbPlaces'),
            'prixMax'      => $request->query->has('prixMax')
                ? (float) $request->query->get('prixMax')
                : null,
            'lng'          => $request->query->get('lng'),
            'lat'          => $request->query->get('lat'),
            'rayon'        => $request->query->get('rayon'),
        ];

        // Nettoyer les filtres null/vides
        $filtres = array_filter($filtres, fn($v) => $v !== null && $v !== '' && $v !== 0);

        $trajets = $this->trajetService->rechercherTrajets($filtres);

        return $this->successResponse(
            array_map(fn(Trajet $t) => $t->toArray(), $trajets),
            sprintf('%d trajet(s) trouvé(s).', count($trajets))
        );
    }

    // ─────────────────────────────────────────────────────────────────
    //  GET /api/trajets/mes-trajets  → trajets du conducteur connecté
    // ─────────────────────────────────────────────────────────────────

    #[Route('/mes-trajets', name: 'mes_trajets', methods: ['GET'])]
    #[IsGranted('ROLE_CONDUCTEUR')]
    public function mesTrajets(): JsonResponse
    {
        $trajets = $this->trajetService->getMesTrajets();

        return $this->successResponse(
            array_map(fn(Trajet $t) => $t->toArray(), $trajets),
            sprintf('%d trajet(s).', count($trajets))
        );
    }

    // ─────────────────────────────────────────────────────────────────
    //  GET /api/trajets/{id}  → détail d'un trajet (PUBLIC)
    // ─────────────────────────────────────────────────────────────────

    #[Route('/{id}', name: 'detail', methods: ['GET'])]
    public function detail(string $id): JsonResponse
    {
        try {
            $trajet = $this->trajetService->getTrajetDetail($id);
        } catch (\RuntimeException) {
            return $this->errorResponse('Trajet introuvable.', [], Response::HTTP_NOT_FOUND);
        }

        return $this->successResponse($trajet->toArray(), 'Trajet trouvé.');
    }

    // ─────────────────────────────────────────────────────────────────
    //  PUT /api/trajets/{id}  → modifier un trajet
    // ─────────────────────────────────────────────────────────────────

    #[Route('/{id}', name: 'modifier', methods: ['PUT'])]
    #[IsGranted('ROLE_CONDUCTEUR')]
    public function modifier(string $id, Request $request): JsonResponse
    {
        try {
            $data = $this->getJsonBody($request);
        } catch (\JsonException $e) {
            return $this->errorResponse('Corps JSON invalide.', [], Response::HTTP_BAD_REQUEST);
        }

        try {
            $trajet = $this->trajetService->modifierTrajet($id, $data);
        } catch (\Symfony\Component\Security\Core\Exception\AccessDeniedException $e) {
            return $this->errorResponse($e->getMessage(), [], Response::HTTP_FORBIDDEN);
        } catch (\RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), [], Response::HTTP_NOT_FOUND);
        } catch (\LogicException $e) {
            return $this->errorResponse($e->getMessage(), [], Response::HTTP_CONFLICT);
        }

        $erreurs = $this->validerDocument($trajet);
        if ($erreurs) {
            return $this->errorResponse('Données invalides.', $erreurs, Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->successResponse($trajet->toArray(), 'Trajet modifié avec succès.');
    }

    // ─────────────────────────────────────────────────────────────────
    //  DELETE /api/trajets/{id}  → annuler un trajet
    // ─────────────────────────────────────────────────────────────────

    #[Route('/{id}', name: 'annuler', methods: ['DELETE'])]
    #[IsGranted('ROLE_CONDUCTEUR')]
    public function annuler(string $id): JsonResponse
    {
        try {
            $this->trajetService->annulerTrajet($id);
        } catch (\Symfony\Component\Security\Core\Exception\AccessDeniedException $e) {
            return $this->errorResponse($e->getMessage(), [], Response::HTTP_FORBIDDEN);
        } catch (\RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), [], Response::HTTP_NOT_FOUND);
        }

        return $this->successResponse(null, 'Trajet annulé.');
    }

    // ─────────────────────────────────────────────────────────────────
    //  Helpers privés
    // ─────────────────────────────────────────────────────────────────

    /**
     * Lit et décode le corps JSON de la requête.
     *
     * @throws \JsonException
     */
    private function getJsonBody(Request $request): array
    {
        $content = $request->getContent();
        if (empty($content)) {
            return [];
        }
        return json_decode($content, true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * Valide un document avec Symfony Validator et retourne les erreurs.
     *
     * @return string[]
     */
    private function validerDocument(object $document): array
    {
        $violations = $this->validator->validate($document);
        if (count($violations) === 0) {
            return [];
        }

        $erreurs = [];
        foreach ($violations as $violation) {
            $erreurs[] = $violation->getPropertyPath() . ' : ' . $violation->getMessage();
        }

        return $erreurs;
    }

    /**
     * Réponse JSON succès uniforme.
     */
    private function successResponse(
        mixed  $data,
        string $message = 'OK',
        int    $status  = Response::HTTP_OK,
    ): JsonResponse {
        return new JsonResponse([
            'success' => true,
            'data'    => $data,
            'message' => $message,
            'errors'  => null,
        ], $status);
    }

    /**
     * Réponse JSON erreur uniforme.
     */
    private function errorResponse(
        string $message,
        array  $errors  = [],
        int    $status  = Response::HTTP_BAD_REQUEST,
    ): JsonResponse {
        return new JsonResponse([
            'success' => false,
            'data'    => null,
            'message' => $message,
            'errors'  => $errors ?: null,
        ], $status);
    }
}

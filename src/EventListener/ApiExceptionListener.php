<?php

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * Intercepte toutes les exceptions lancées dans les routes /api/*
 * et retourne une réponse JSON uniforme :
 *
 * {
 *   "success": false,
 *   "data":    null,
 *   "message": "...",
 *   "errors":  null | ["..."]
 * }
 */
#[AsEventListener(event: 'kernel.exception', priority: 10)]
class ApiExceptionListener
{
    public function __invoke(ExceptionEvent $event): void
    {
        $request   = $event->getRequest();
        $exception = $event->getThrowable();

        // N'intercepter que les routes /api/*
        // (les routes web continuent d'afficher les pages d'erreur Twig)
        if (!str_starts_with($request->getPathInfo(), '/api')) {
            return;
        }

        [$status, $message, $errors] = $this->resolveException($exception);

        $event->setResponse(new JsonResponse([
            'success' => false,
            'data'    => null,
            'message' => $message,
            'errors'  => $errors,
        ], $status));
    }

    /**
     * Résout le code HTTP, le message et la liste d'erreurs à partir de l'exception.
     *
     * @return array{int, string, string[]|null}
     */
    private function resolveException(\Throwable $exception): array
    {
        // 403 – Accès refusé
        if ($exception instanceof AccessDeniedException) {
            return [
                Response::HTTP_FORBIDDEN,
                $exception->getMessage() ?: 'Accès refusé.',
                null,
            ];
        }

        // 404 – Ressource introuvable (Symfony ou RuntimeException métier)
        if (
            $exception instanceof NotFoundHttpException
            || ($exception instanceof \RuntimeException && str_contains($exception->getMessage(), 'introuvable'))
        ) {
            return [
                Response::HTTP_NOT_FOUND,
                $exception->getMessage() ?: 'Ressource introuvable.',
                null,
            ];
        }

        // 422 – Entité non traitable (erreurs de validation)
        if ($exception instanceof \Symfony\Component\Validator\Exception\ValidationFailedException) {
            $errors = [];
            foreach ($exception->getViolations() as $violation) {
                $errors[] = $violation->getPropertyPath() . ' : ' . $violation->getMessage();
            }
            return [
                Response::HTTP_UNPROCESSABLE_ENTITY,
                'Données invalides.',
                $errors,
            ];
        }

        // 409 – Conflit (statut non modifiable)
        if ($exception instanceof \LogicException) {
            return [
                Response::HTTP_CONFLICT,
                $exception->getMessage(),
                null,
            ];
        }

        // HttpExceptionInterface générique (401, 405, etc.)
        if ($exception instanceof HttpExceptionInterface) {
            return [
                $exception->getStatusCode(),
                $exception->getMessage() ?: Response::$statusTexts[$exception->getStatusCode()] ?? 'Erreur HTTP.',
                null,
            ];
        }

        // 500 – Erreur serveur inattendue
        return [
            Response::HTTP_INTERNAL_SERVER_ERROR,
            'Une erreur interne est survenue.',
            null,
        ];
    }
}

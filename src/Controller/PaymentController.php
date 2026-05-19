<?php

namespace App\Controller;

use App\Document\Reservation;
use App\Document\Trajet;
use Doctrine\ODM\MongoDB\DocumentManager;
use App\Service\ReservationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class PaymentController extends AbstractController
{
    private DocumentManager $dm;
    private ReservationService $reservationService;

    public function __construct(DocumentManager $dm, ReservationService $reservationService)
    {
        $this->dm = $dm;
        $this->reservationService = $reservationService;
    }

    /**
     * Algorithme de Luhn pour valider le numéro de carte bancaire
     */
    private function isValidLuhn(string $number): bool
    {
        $sum = 0;
        $length = strlen($number);
        $parity = $length % 2;

        for ($i = $length - 1; $i >= 0; $i--) {
            $digit = (int) $number[$i];

            if ($i % 2 === $parity) {
                $digit *= 2;
                if ($digit > 9) {
                    $digit -= 9;
                }
            }
            $sum += $digit;
        }

        return ($sum % 10 === 0);
    }

    #[Route('/payment-page', name: 'app_payment_page', methods: ['GET', 'POST'])]
    public function paymentPage(Request $request): Response
    {
        $trajetId = $request->query->get('trajetId');
        $nbPlaces = (int) $request->query->get('nbPlaces', 1);

        if (!$trajetId) {
            return new Response('Invalid payment session. Missing trajetId.', 400);
        }

        $trajet = $this->dm->find(Trajet::class, $trajetId);

        if (!$trajet) {
            return new Response('Trajet not found.', 404);
        }

        if ($trajet->getStatut() !== 'actif') {
            return new Response("Ce trajet n'est plus actif.", 400);
        }

        if ($trajet->getNbPlacesDisponibles() < $nbPlaces) {
            return new Response("Plus assez de places disponibles pour ce trajet.", 400);
        }

        $amount = $trajet->getPrix() * $nbPlaces;
        $error = null;

        if ($request->isMethod('POST')) {
            $cardNumber = str_replace(' ', '', $request->request->get('cardNumber', ''));
            $expiry = $request->request->get('expiry');
            $cvv = $request->request->get('cvv');
            $name = $request->request->get('name');

            if (empty($cardNumber) || !is_numeric($cardNumber) || !$this->isValidLuhn($cardNumber)) {
                $error = 'Numéro de carte invalide (Échec de l\'algorithme de Luhn).';
            } elseif (strlen($cvv) < 3 || !is_numeric($cvv)) {
                $error = 'Code de sécurité (CVV) invalide.';
            } elseif (empty($name) || empty($expiry)) {
                $error = 'Veuillez remplir tous les champs.';
            } else {
                try {
                    // Paiement réussi, on crée et confirme la réservation
                    $reservation = $this->reservationService->reserver($trajetId, $nbPlaces);
                    $reservation->setStatut('confirmee');
                    $this->dm->flush();

                    return $this->redirectToRoute('app_payment_success', ['id' => $reservation->getId()]);
                } catch (\Exception $e) {
                    $error = 'Erreur lors de la réservation : ' . $e->getMessage();
                }
            }
        }

        return $this->render('payment/clictopay.html.twig', [
            'trajetId' => $trajetId,
            'nbPlaces' => $nbPlaces,
            'amount' => $amount,
            'currency' => 'TND',
            'error' => $error
        ]);
    }

    #[Route('/payment-page/success', name: 'app_payment_success', methods: ['GET'])]
    public function paymentSuccess(Request $request): Response
    {
        $paymentId = $request->query->get('id');
        return $this->render('payment/success.html.twig', [
            'paymentId' => $paymentId
        ]);
    }
}

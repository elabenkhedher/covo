<?php

namespace App\Controller;

use App\Document\Reservation;
use App\Document\Trajet;
use Doctrine\ODM\MongoDB\DocumentManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class PaymentController extends AbstractController
{
    private DocumentManager $dm;

    public function __construct(DocumentManager $dm)
    {
        $this->dm = $dm;
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
        $reservationId = $request->query->get('reservationId');

        if (!$reservationId) {
            return new Response('Invalid payment session. Missing reservationId.', 400);
        }

        $reservation = $this->dm->find(Reservation::class, $reservationId);

        if (!$reservation) {
            return new Response('Reservation not found.', 404);
        }

        // Si la réservation est déjà payée/confirmée
        if ($reservation->getStatut() === 'confirmee') {
            return $this->render('payment/success.html.twig', [
                'paymentId' => $reservation->getId()
            ]);
        }

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
                // Paiement réussi, on confirme la réservation
                $reservation->setStatut('confirmee');
                $this->dm->flush();

                return $this->redirectToRoute('app_payment_success', ['id' => $reservation->getId()]);
            }
        }

        return $this->render('payment/clictopay.html.twig', [
            'paymentId' => $reservation->getId(),
            'amount' => $reservation->getPrixTotal(),
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

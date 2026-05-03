<?php

namespace App\Controller;

use App\Service\TrajetService;
use Doctrine\ODM\MongoDB\DocumentManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/passager', name: 'passager_')]
#[IsGranted('ROLE_USER')]
class PassagerController extends AbstractController
{
    public function __construct(
        private readonly TrajetService $trajetService,
        private readonly \App\Service\ReservationService $reservationService,
        private readonly DocumentManager $dm
    ) {}

    #[Route('', name: 'dashboard')]
    public function dashboard(): Response
    {
        $suggestions = $this->trajetService->rechercherTrajets([]);

        return $this->render('passager/dashboard.html.twig', [
            'stats' => [
                'trajets_effectues' => 12,
                'reservations_actives' => 2,
                'total_depense' => 145.0,
                'ma_note' => 4.8,
            ],
            'prochains_trajets' => [],
            'trajets_suggeres' => array_slice($suggestions, 0, 5),
        ]);
    }

    #[Route('/tracking', name: 'tracking')]
    public function tracking(\Symfony\Component\HttpFoundation\Request $request): Response
    {
        $reservationId = $request->query->get('reservationId');
        $data = null;

        if ($reservationId) {
            $res = $this->dm->find(\App\Document\Reservation::class, $reservationId);
            if ($res) {
                $trajet = $this->dm->find(\App\Document\Trajet::class, $res->getTrajetId());
                $conducteur = null;
                if ($trajet) {
                    $conducteur = $this->dm->find(\App\Document\User::class, $trajet->getConducteurId());
                }
                $data = [
                    'id' => $res->getId(),
                    'statut' => $res->getStatut(),
                    'nbPlaces' => $res->getNbPlaces(),
                    'trajet' => $trajet,
                    'conducteur' => $conducteur,
                    'reservation' => $res
                ];
            }
        }

        return $this->render('tracking/passager.html.twig', [
            'reservation' => $data
        ]);
    }

    #[Route('/reservations', name: 'reservations')]
    public function reservations(): Response
    {
        $reservations = $this->reservationService->getMesReservations();
        $data = [];

        foreach ($reservations as $res) {
            $trajet = $this->dm->find(\App\Document\Trajet::class, $res->getTrajetId());
            $conducteur = null;
            if ($trajet) {
                $conducteur = $this->dm->find(\App\Document\User::class, $trajet->getConducteurId());
            }
            
            $data[] = [
                'id' => $res->getId(),
                'statut' => $res->getStatut(),
                'nbPlaces' => $res->getNbPlaces(),
                'montant' => $res->getPrixTotal(),
                'trajet' => $trajet,
                'conducteur' => $conducteur,
                'reservation' => $res
            ];
        }

        return $this->render('passager/mes-reservations.html.twig', [
            'reservations' => $data,
        ]);
    }

    #[Route('/profil', name: 'profile')]
    public function profile(\Symfony\Component\HttpFoundation\Request $request, \Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface $hasher): Response
    {
        /** @var \App\Document\User $user */
        $user = $this->getUser();
        $form = $this->createForm(\App\Form\ProfileFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // TODO: Gérer le changement de mot de passe
            $this->dm->flush();
            $this->addFlash('success', 'Profil mis à jour.');
            return $this->redirectToRoute('passager_profile');
        }

        return $this->render('profil/passager.html.twig', [
            'profileForm' => $form->createView(),
            'stats' => [
                'nbTrajets' => 12,
                'noteMoyenne' => 4.8,
                'totalDepense' => 145.0
            ]
        ]);
    }

    #[Route('/notation', name: 'notation')]
    public function notation(): Response
    {
        return $this->render('notation/passager.html.twig', [
            'note_moyenne' => 4.8,
            'avis_recus' => [],
            'a_noter' => []
        ]);
    }

    #[Route('/notation/trajet/{reservationId}', name: 'noter_conducteur')]
    public function noter(string $reservationId): Response
    {
        // TODO: Implémenter la notation
        return $this->redirectToRoute('passager_notation');
    }

    #[Route('/reservation/{id}/annuler', name: 'annuler_reservation', methods: ['POST'])]
    public function annuler(string $id): Response
    {
        // TODO: Implémenter l'annulation
        $this->addFlash('success', 'Réservation annulée.');
        return $this->redirectToRoute('passager_reservations');
    }

    #[Route('/reserver/{trajetId}', name: 'reserver', methods: ['POST'])]
    public function reserver(string $trajetId, \Symfony\Component\HttpFoundation\Request $request): Response
    {
        $nbPlaces = (int) $request->request->get('nbPlaces', 1);

        try {
            $this->reservationService->reserver(
                $trajetId,
                $nbPlaces
            );
            
            $this->addFlash('success', 'Votre réservation a été effectuée avec succès !');
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors de la réservation : ' . $e->getMessage());
        }

        return $this->redirectToRoute('passager_reservations');
    }
}

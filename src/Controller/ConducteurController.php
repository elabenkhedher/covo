<?php

namespace App\Controller;

use App\Document\Trajet;
use App\Service\TrajetService;
use Doctrine\ODM\MongoDB\DocumentManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/conducteur', name: 'conducteur_')]
#[IsGranted('ROLE_CONDUCTEUR')]
class ConducteurController extends AbstractController
{
    public function __construct(
        private readonly TrajetService $trajetService,
        private readonly DocumentManager $dm
    ) {}

    #[Route('', name: 'dashboard')]
    public function dashboard(): Response
    {
        $trajets = $this->trajetService->getMesTrajets();
        
        $stats = [
            'trajets_publies' => count($trajets),
            'revenus_total' => 0.0,
            'passagers_transportes' => 0,
            'note_moyenne' => 4.5, // Mock
        ];
        
        $prochains = [];
        foreach ($trajets as $t) {
            if ($t->getStatut() === 'termine') {
                $p = $t->getNbPlacesTotal() - $t->getNbPlacesDisponibles();
                $stats['passagers_transportes'] += $p;
                $stats['revenus_total'] += $p * $t->getPrix();
            }
            if ($t->getStatut() === 'actif' && $t->getDateDepart() >= new \DateTime('today')) {
                $prochains[] = $t;
            }
        }

        return $this->render('conducteur/dashboard.html.twig', [
            'stats' => $stats,
            'revenus_mois' => [
                'total' => 120.0,
                'trajets' => 8,
                'passagers' => 15,
                'taux_remplissage' => 85
            ],
            'derniers_avis' => [],
            'trajets_a_venir' => $prochains,
        ]);
    }

    #[Route('/tracking', name: 'tracking')]
    public function tracking(\Symfony\Component\HttpFoundation\Request $request): Response
    {
        $trajetId = $request->query->get('trajetId');
        $trajet = null;
        $passagers = [];

        if ($trajetId) {
            $trajet = $this->dm->find(Trajet::class, $trajetId);
            if ($trajet) {
                // Fetch confirmed reservations
                $reservations = $this->dm->getRepository(\App\Document\Reservation::class)
                    ->findBy(['trajetId' => $trajetId, 'statut' => 'confirmee']);
                
                foreach ($reservations as $res) {
                    $u = $this->dm->find(\App\Document\User::class, $res->getPassagerId());
                    if ($u) {
                        $passagers[] = [
                            'initials' => $u->getInitials(),
                            'prenom' => $u->getPrenom(),
                            'nom' => $u->getNom(),
                            'statut' => 'en_attente' // TODO: dynamic
                        ];
                    }
                }
            }
        }

        return $this->render('tracking/conducteur.html.twig', [
            'trajet' => $trajet,
            'passagers' => $passagers
        ]);
    }

    #[Route('/profil', name: 'profile')]
    public function profile(\Symfony\Component\HttpFoundation\Request $request): Response
    {
        /** @var \App\Document\User $user */
        $user = $this->getUser();
        $form = $this->createForm(\App\Form\ProfileFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->dm->flush();
            $this->addFlash('success', 'Profil mis à jour.');
            return $this->redirectToRoute('conducteur_profile');
        }

        return $this->render('profil/conducteur.html.twig', [
            'profileForm' => $form->createView(),
            'stats' => [
                'nbTrajets' => 12,
                'noteMoyenne' => 4.5,
                'nbPassagers' => 48
            ]
        ]);
    }

    #[Route('/notation', name: 'notation')]
    public function notation(): Response
    {
        return $this->render('notation/conducteur.html.twig', [
            'note_moyenne' => 4.5,
            'avis' => []
        ]);
    }

    #[Route('/trajet/{id}/annuler', name: 'annuler_trajet', methods: ['POST'])]
    public function annuler(string $id): Response
    {
        try {
            $this->trajetService->annulerTrajet($id);
            $this->addFlash('success', 'Trajet annulé avec succès. Les passagers seront notifiés.');
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors de l\'annulation : ' . $e->getMessage());
        }

        return $this->redirectToRoute('trajet_mes_trajets');
    }

    #[Route('/trajet/{id}/demarrer', name: 'demarrer_trajet', methods: ['POST'])]
    public function demarrer(string $id): Response
    {
        $trajet = $this->dm->find(Trajet::class, $id);
        if ($trajet && $trajet->getConducteurId() === (string)$this->getUser()->getId()) {
            $trajet->setStatut('en_cours');
            $this->dm->flush();
            $this->addFlash('success', 'Trajet démarré ! Le suivi GPS est actif.');
        }
        
        return $this->redirectToRoute('conducteur_tracking', ['trajetId' => $id]);
    }

    #[Route('/trajet/{id}/terminer', name: 'terminer_trajet', methods: ['POST'])]
    public function terminer(string $id): Response
    {
        $trajet = $this->dm->find(Trajet::class, $id);
        if ($trajet && $trajet->getConducteurId() === (string)$this->getUser()->getId()) {
            $trajet->setStatut('termine');
            $this->dm->flush();
            $this->addFlash('success', 'Trajet terminé avec succès.');
        }
        
        return $this->redirectToRoute('conducteur_dashboard');
    }
}

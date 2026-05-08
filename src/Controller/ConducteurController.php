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
        $user = $this->getUser();
        $trajets = $this->trajetService->getMesTrajets();

        $debutMois = new \DateTime('first day of this month midnight');
        $finMois   = new \DateTime('last day of this month 23:59:59');

        $stats = [
            'trajets_publies'      => count($trajets),
            'revenus_total'        => 0.0,
            'passagers_transportes'=> 0,
            'note_moyenne'         => $user->getNoteMoyenne(),
        ];

        $revenus_mois = [
            'total'           => 0.0,
            'trajets'         => 0,
            'passagers'       => 0,
            'taux_remplissage'=> 0,
        ];

        $prochains = [];
        $totalPlacesTotalesMois   = 0;
        $totalPlacesReserveesMois = 0;

        foreach ($trajets as $t) {
            // Récupérer les réservations confirmées pour ce trajet
            $reservations = $this->dm->getRepository(\App\Document\Reservation::class)
                ->findBy(['trajetId' => $t->getId(), 'statut' => 'confirmee']);
            
            $placesReservees = 0;
            $montantTrajet = 0;
            foreach ($reservations as $r) {
                $placesReservees += $r->getNbPlaces();
                $montantTrajet += $r->getPrixTotal();
            }

            // Cumul total (Historique complet)
            $stats['passagers_transportes'] += $placesReservees;
            $stats['revenus_total']         += $montantTrajet;

            // Stats du mois courant
            $dateDepart = $t->getDateDepart();
            if ($dateDepart && $dateDepart >= $debutMois && $dateDepart <= $finMois) {
                if (in_array($t->getStatut(), ['termine', 'actif', 'en_cours'])) {
                    $revenus_mois['trajets']++;
                    $revenus_mois['passagers']  += $placesReservees;
                    $revenus_mois['total']      += $montantTrajet;
                    $totalPlacesTotalesMois     += $t->getNbPlacesTotal();
                    $totalPlacesReserveesMois   += $placesReservees;
                }
            }

            // Prochains trajets à venir
            if ($t->getStatut() === 'actif' && $dateDepart && $dateDepart >= new \DateTime('today')) {
                $prochains[] = $t;
            }
        }

        // Taux de remplissage moyen du mois
        if ($totalPlacesTotalesMois > 0) {
            $revenus_mois['taux_remplissage'] = (int) round(
                ($totalPlacesReserveesMois / $totalPlacesTotalesMois) * 100
            );
        }

        $stats['note_moyenne'] = $user->getNoteMoyenne() ?? 0.0;

        // Trier les prochains trajets par date
        usort($prochains, fn($a, $b) => $a->getDateDepart() <=> $b->getDateDepart());

        // Récupérer les derniers avis reçus
        $avisDocument = $this->dm->getRepository(\App\Document\Avis::class)
            ->findBy(
                ['destinataireId' => (string)$user->getId(), 'type' => 'passager_vers_conducteur'],
                ['createdAt' => 'DESC'],
                3 // Limiter à 3 avis
            );
            
        $derniersAvis = [];
        foreach ($avisDocument as $avis) {
            $auteur = $this->dm->find(\App\Document\User::class, $avis->getAuteurId());
            if ($auteur) {
                $derniersAvis[] = [
                    'auteur' => $auteur,
                    'note' => $avis->getNote(),
                    'commentaire' => $avis->getCommentaire(),
                    'createdAt' => $avis->getCreatedAt()
                ];
            }
        }

        return $this->render('conducteur/dashboard.html.twig', [
            'stats'         => $stats,
            'revenus_mois'  => $revenus_mois,
            'derniers_avis' => $derniersAvis,
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

        // Stats dynamiques depuis les trajets du conducteur
        $trajets     = $this->trajetService->getMesTrajets();
        $nbPassagers = 0;
        foreach ($trajets as $t) {
            if ($t->getStatut() === 'termine') {
                $nbPassagers += $t->getNbPlacesTotal() - $t->getNbPlacesDisponibles();
            }
        }

        return $this->render('profil/conducteur.html.twig', [
            'profileForm' => $form->createView(),
            'stats' => [
                'nbTrajets'   => count($trajets),
                'noteMoyenne' => $user->getNoteMoyenne(),
                'nbPassagers' => $nbPassagers,
            ],
        ]);
    }

    #[Route('/notation', name: 'notation')]
    public function notation(): Response
    {
        /** @var \App\Document\User $user */
        $user = $this->getUser();
        $userId = (string)$user->getId();

        // 1. Avis reçus (par les passagers)
        $avisRecus = $this->dm->getRepository(\App\Document\Avis::class)
            ->findBy(['destinataireId' => $userId, 'type' => 'passager_vers_conducteur'], ['createdAt' => 'DESC']);

        $avisData = [];
        foreach ($avisRecus as $avis) {
            $auteur = $this->dm->find(\App\Document\User::class, $avis->getAuteurId());
            $trajet = $this->dm->find(\App\Document\Trajet::class, $avis->getTrajetId());
            $avisData[] = [
                'auteur' => $auteur,
                'trajet' => $trajet,
                'note' => $avis->getNote(),
                'commentaire' => $avis->getCommentaire(),
                'createdAt' => $avis->getCreatedAt()
            ];
        }

        // 2. Passagers à noter (trajets terminés dont les passagers n'ont pas été notés)
        $mesTrajetsTermines = $this->dm->getRepository(\App\Document\Trajet::class)
            ->findBy(['conducteurId' => $userId, 'statut' => 'termine']);
            
        $aNoter = [];
        foreach ($mesTrajetsTermines as $trajet) {
            $reservations = $this->dm->getRepository(\App\Document\Reservation::class)
                ->findBy(['trajetId' => $trajet->getId(), 'statut' => 'confirmee']);
                
            foreach ($reservations as $res) {
                // Vérifier si déjà noté
                $dejaNote = $this->dm->getRepository(\App\Document\Avis::class)
                    ->findOneBy(['auteurId' => $userId, 'destinataireId' => $res->getPassagerId(), 'trajetId' => $trajet->getId(), 'type' => 'conducteur_vers_passager']);
                
                if (!$dejaNote) {
                    $passager = $this->dm->find(\App\Document\User::class, $res->getPassagerId());
                    $aNoter[] = [
                        'reservationId' => $res->getId(),
                        'passager' => $passager,
                        'trajet' => $trajet
                    ];
                }
            }
        }

        return $this->render('notation/conducteur.html.twig', [
            'note_moyenne'  => $user->getNoteMoyenne(),
            'nb_notations'  => $user->getNbNotations(),
            'avis'          => $avisData,
            'a_noter'       => $aNoter,
        ]);
    }

    #[Route('/noter-passager/{reservationId}', name: 'noter_passager', methods: ['POST'])]
    public function noterPassager(string $reservationId, \Symfony\Component\HttpFoundation\Request $request): Response
    {
        $res = $this->dm->find(\App\Document\Reservation::class, $reservationId);
        if (!$res) {
            $this->addFlash('error', 'Réservation introuvable.');
            return $this->redirectToRoute('conducteur_notation');
        }

        $trajet = $this->dm->find(\App\Document\Trajet::class, $res->getTrajetId());
        if (!$trajet || $trajet->getConducteurId() !== (string)$this->getUser()->getId()) {
            $this->addFlash('error', 'Accès refusé.');
            return $this->redirectToRoute('conducteur_notation');
        }

        $note = (int)$request->request->get('note', 5);
        $commentaire = $request->request->get('commentaire');

        $avis = new \App\Document\Avis();
        $avis->setAuteurId((string)$this->getUser()->getId());
        $avis->setDestinataireId($res->getPassagerId());
        $avis->setTrajetId($trajet->getId());
        $avis->setNote($note);
        $avis->setCommentaire($commentaire);
        $avis->setType('conducteur_vers_passager');

        $this->dm->persist($avis);

        // Mettre à jour la note du passager
        $passager = $this->dm->find(\App\Document\User::class, $res->getPassagerId());
        if ($passager) {
            $passager->ajouterNotation($note);
        }

        $this->dm->flush();

        $this->addFlash('success', 'Votre avis sur le passager a été enregistré.');
        return $this->redirectToRoute('conducteur_notation');
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

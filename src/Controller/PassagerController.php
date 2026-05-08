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
    ) {
    }

    #[Route('', name: 'dashboard')]
    public function dashboard(): Response
    {
        $user = $this->getUser();
        $suggestions = $this->trajetService->rechercherTrajets([]);
        $reservations = $this->reservationService->getMesReservations();

        /** @var \App\Document\User $user */
        $user = $this->getUser();

        $maintenant = new \DateTime();

        $trajets_effectues = 0;
        $reservations_actives = 0;
        $total_depense = 0.0;
        $prochains_trajets = [];

        foreach ($reservations as $res) {
            // Ne compter que les réservations non annulées
            if ($res->getStatut() === 'annulee') {
                continue;
            }

            $total_depense += $res->getPrixTotal();

            $trajet = $this->dm->find(\App\Document\Trajet::class, $res->getTrajetId());

            if ($trajet) {
                if ($trajet->getStatut() === 'termine') {
                    $trajets_effectues++;
                }

                // Réservation active = trajet à venir (actif ou en_cours)
                if (
                    in_array($trajet->getStatut(), ['actif', 'en_cours'])
                    && $trajet->getDateDepart()
                    && $trajet->getDateDepart() >= $maintenant
                ) {
                    $reservations_actives++;

                    $conducteur = $this->dm->find(\App\Document\User::class, $trajet->getConducteurId());

                    $prochains_trajets[] = [
                        'trajet' => $trajet,
                        'conducteur' => $conducteur,
                        'reservation' => $res,
                        'statut' => $res->getStatut(),
                        'nbPlaces' => $res->getNbPlaces(),
                    ];
                }
            }
        }

        // Trier les prochains trajets par date de départ
        usort(
            $prochains_trajets,
            fn($a, $b) =>
            $a['trajet']->getDateDepart() <=> $b['trajet']->getDateDepart()
        );

        // Récupérer les derniers avis reçus
        $avisDocument = $this->dm->getRepository(\App\Document\Avis::class)
            ->findBy(
                ['destinataireId' => (string) $user->getId(), 'type' => 'conducteur_vers_passager'],
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

        return $this->render('passager/dashboard.html.twig', [
            'stats' => [
                'trajets_effectues' => $trajets_effectues,
                'reservations_actives' => $reservations_actives,
                'total_depense' => round($total_depense, 2),
                'ma_note' => $user->getNoteMoyenne() ?? 0.0,
            ],
            'prochains_trajets' => array_slice($prochains_trajets, 0, 5),
            'trajets_suggeres' => array_slice($suggestions, 0, 5),
            'derniers_avis' => $derniersAvis,
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
                    'etatPassager' => $res->getEtatPassager(),
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

            // Vérifier si déjà noté
            $dejaNote = $this->dm->getRepository(\App\Document\Avis::class)
                ->findOneBy(['auteurId' => (string) $this->getUser()->getId(), 'trajetId' => $res->getTrajetId(), 'type' => 'passager_vers_conducteur']);

            $data[] = [
                'id' => $res->getId(),
                'statut' => $res->getStatut(),
                'nbPlaces' => $res->getNbPlaces(),
                'montant' => $res->getPrixTotal(),
                'trajet' => $trajet,
                'conducteur' => $conducteur,
                'reservation' => $res,
                'dejaNote' => $dejaNote !== null,
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

        // Stats dynamiques depuis les réservations
        $reservations = $this->reservationService->getMesReservations();
        $nbTrajets = 0;
        $totalDepense = 0.0;
        foreach ($reservations as $res) {
            if ($res->getStatut() === 'annulee') {
                continue;
            }
            $totalDepense += $res->getPrixTotal();
            $trajet = $this->dm->find(\App\Document\Trajet::class, $res->getTrajetId());
            if ($trajet && $trajet->getStatut() === 'termine') {
                $nbTrajets++;
            }
        }

        return $this->render('profil/passager.html.twig', [
            'profileForm' => $form->createView(),
            'stats' => [
                'nbTrajets' => $nbTrajets,
                'noteMoyenne' => $user->getNoteMoyenne(),
                'totalDepense' => round($totalDepense, 2),
            ],
        ]);
    }

    #[Route('/notation', name: 'notation')]
    public function notation(): Response
    {
        /** @var \App\Document\User $user */
        $user = $this->getUser();
        $userId = (string) $user->getId();

        // 1. Avis reçus (par les conducteurs)
        $avisRecus = $this->dm->getRepository(\App\Document\Avis::class)
            ->findBy(['destinataireId' => $userId, 'type' => 'conducteur_vers_passager'], ['createdAt' => 'DESC']);

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

        // 2. Trajets à noter (conducteurs non encore notés pour des trajets terminés)
        $reservations = $this->reservationService->getMesReservations();
        $aNoter = [];
        foreach ($reservations as $res) {
            if ($res->getStatut() !== 'confirmee')
                continue;

            $trajet = $this->dm->find(\App\Document\Trajet::class, $res->getTrajetId());
            if ($trajet && $trajet->getStatut() === 'termine') {
                // Vérifier si déjà noté
                $dejaNote = $this->dm->getRepository(\App\Document\Avis::class)
                    ->findOneBy(['auteurId' => $userId, 'trajetId' => $trajet->getId(), 'type' => 'passager_vers_conducteur']);

                if (!$dejaNote) {
                    $conducteur = $this->dm->find(\App\Document\User::class, $trajet->getConducteurId());
                    $aNoter[] = [
                        'id' => $res->getId(),
                        'trajet' => [
                            'id' => $trajet->getId(),
                            'villeDepart' => $trajet->getVilleDepart(),
                            'villeArrivee' => $trajet->getVilleArrivee(),
                            'dateDepart' => $trajet->getDateDepart(),
                            'conducteur' => $conducteur
                        ]
                    ];
                }
            }
        }

        // Récupérer les derniers avis reçus
        $avisDocument = $this->dm->getRepository(\App\Document\Avis::class)
            ->findBy(
                ['destinataireId' => (string) $user->getId(), 'type' => 'conducteur_vers_passager'],
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

        return $this->render('notation/passager.html.twig', [
            'note_moyenne' => $user->getNoteMoyenne(),
            'nb_notations' => $user->getNbNotations(),
            'avis_recus' => $avisData,
            'a_noter' => $aNoter,
        ]);
    }

    #[Route('/notation/trajet/{reservationId}', name: 'noter_conducteur', methods: ['POST'])]
    public function noter(string $reservationId, \Symfony\Component\HttpFoundation\Request $request): Response
    {
        $res = $this->dm->find(\App\Document\Reservation::class, $reservationId);
        if (!$res || $res->getPassagerId() !== (string) $this->getUser()->getId()) {
            $this->addFlash('error', 'Réservation introuvable.');
            return $this->redirectToRoute('passager_notation');
        }

        $trajet = $this->dm->find(\App\Document\Trajet::class, $res->getTrajetId());
        if (!$trajet) {
            $this->addFlash('error', 'Trajet introuvable.');
            return $this->redirectToRoute('passager_notation');
        }

        $note = (int) $request->request->get('note', 5);
        $commentaire = $request->request->get('commentaire');

        $avis = new \App\Document\Avis();
        $avis->setAuteurId((string) $this->getUser()->getId());
        $avis->setDestinataireId($trajet->getConducteurId());
        $avis->setTrajetId($trajet->getId());
        $avis->setNote($note);
        $avis->setCommentaire($commentaire);
        $avis->setType('passager_vers_conducteur');

        $this->dm->persist($avis);

        // Mettre à jour la note du conducteur
        $conducteur = $this->dm->find(\App\Document\User::class, $trajet->getConducteurId());
        if ($conducteur) {
            $conducteur->ajouterNotation($note);
        }

        $this->dm->flush();

        $this->addFlash('success', 'Votre avis a été enregistré. Merci !');
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

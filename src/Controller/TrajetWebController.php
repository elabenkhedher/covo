<?php

namespace App\Controller;

use App\Document\Trajet;
use App\Document\User;
use App\Form\TrajetType;
use App\Service\TrajetService;
use Doctrine\ODM\MongoDB\DocumentManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/trajet', name: 'trajet_')]
class TrajetWebController extends AbstractController
{
    public function __construct(
        private readonly TrajetService $trajetService,
        private readonly DocumentManager $dm
    ) {}

    #[Route('/recherche', name: 'search', methods: ['GET'])]
    public function search(Request $request): Response
    {
        $filtres = array_filter([
            'villeDepart'  => $request->query->get('villeDepart'),
            'villeArrivee' => $request->query->get('villeArrivee'),
            'date'         => $request->query->get('date'),
            'nbPlaces'     => $request->query->getInt('nbPlaces') ?: null,
        ]);

        $trajets = $this->trajetService->rechercherTrajets($filtres);

        $conducteurs = [];
        foreach ($trajets as $trajet) {
            $cid = $trajet->getConducteurId();
            if ($cid && !isset($conducteurs[$cid])) {
                $conducteurs[$cid] = $this->dm->find(User::class, $cid);
            }
        }

        return $this->render('search.html.twig', [
            'trajets' => $trajets,
            'conducteurs' => $conducteurs,
            'filtres' => $filtres,
            'total'   => count($trajets),
        ]);
    }

    #[Route('/publier', name: 'publier', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_CONDUCTEUR')]
    public function publier(Request $request): Response
    {
        $trajet = new Trajet();
        $form = $this->createForm(TrajetType::class, $trajet);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Fusionner dateDepart + heureDepart en un seul DateTime
            $heureDepart = $trajet->getHeureDepart(); // "HH:MM"
            if ($trajet->getDateDepart() && $heureDepart && preg_match('/^\d{2}:\d{2}$/', $heureDepart)) {
                [$h, $m] = explode(':', $heureDepart);
                $date = \DateTime::createFromInterface($trajet->getDateDepart());
                $date->setTime((int)$h, (int)$m, 0);
                $trajet->setDateDepart($date);
            }

            // Extraction coordonnées non-mappées
            $lngD = $form->get('lngDepart')->getData();
            $latD = $form->get('latDepart')->getData();
            if ($lngD && $latD) {
                $trajet->setCoordDepart(['type' => 'Point', 'coordinates' => [(float)$lngD, (float)$latD]]);
            }

            $lngA = $form->get('lngArrivee')->getData();
            $latA = $form->get('latArrivee')->getData();
            if ($lngA && $latA) {
                $trajet->setCoordArrivee(['type' => 'Point', 'coordinates' => [(float)$lngA, (float)$latA]]);
            }

            // Préférences
            $prefs = $request->request->all('preferences');
            $trajet->setPreferences([
                'fumeur'  => isset($prefs['fumeur']),
                'musique' => isset($prefs['musique']),
                'animaux' => isset($prefs['animaux']),
            ]);

            $trajet->setConducteurId((string) $this->getUser()->getId());
            $trajet->setNbPlacesDisponibles($trajet->getNbPlacesTotal());

            try {
                $this->dm->persist($trajet);
                $this->dm->flush();
            } catch (\Exception $e) {
                $this->addFlash('error', 'Erreur lors de la publication : ' . $e->getMessage());
                return $this->render('conducteur/publier-trajet.html.twig', [
                    'trajetForm' => $form->createView(),
                    'vehicules' => [],
                ]);
            }

            $this->addFlash('success', 'Votre trajet a été publié avec succès !');
            return $this->redirectToRoute('trajet_mes_trajets');
        }

        return $this->render('conducteur/publier-trajet.html.twig', [
            'trajetForm' => $form->createView(),
            'vehicules' => [], // TODO: charger depuis VehiculeRepository
        ]);
    }

    #[Route('/mes-trajets', name: 'mes_trajets', methods: ['GET'])]
    #[IsGranted('ROLE_CONDUCTEUR')]
    public function mesTrajets(Request $request): Response
    {
        $allTrajets = $this->trajetService->getMesTrajets();
        $statut = $request->query->get('statut');

        $trajets = $statut
            ? array_filter($allTrajets, fn(Trajet $t) => $t->getStatut() === $statut)
            : $allTrajets;

        $stats = ['total' => count($allTrajets), 'actifs' => 0, 'annules' => 0, 'revenus' => 0.0];
        foreach ($allTrajets as $t) {
            if ($t->getStatut() === 'actif') $stats['actifs']++;
            if ($t->getStatut() === 'annule') $stats['annules']++;
            if ($t->getStatut() === 'termine') {
                $stats['revenus'] += $t->getPrix() * ($t->getNbPlacesTotal() - $t->getNbPlacesDisponibles());
            }
        }

        return $this->render('conducteur/mes-trajets.html.twig', [
            'trajets' => array_values($trajets),
            'stats'   => $stats,
        ]);
    }

    #[Route('/{id}', name: 'detail', methods: ['GET'])]
    public function detail(string $id): Response
    {
        $trajet = $this->trajetService->getTrajetDetail($id);
        $conducteur = $this->dm->find(User::class, $trajet->getConducteurId());

        return $this->render('trajet/detail.html.twig', [
            'trajet' => $trajet,
            'conducteur' => $conducteur,
            'avis' => [], // TODO: charger depuis NotationRepository
        ]);
    }

    #[Route('/{id}/conducteur', name: 'detail_conducteur', methods: ['GET'])]
    #[IsGranted('ROLE_CONDUCTEUR')]
    public function detailConducteur(string $id): Response
    {
        $trajet = $this->trajetService->getTrajetDetail($id);
        if ($trajet->getConducteurId() !== (string)$this->getUser()->getId()) {
            throw $this->createAccessDeniedException();
        }

        // Récupérer les passagers ayant réservé
        $reservations = $this->dm->getRepository(\App\Document\Reservation::class)
            ->findBy(['trajetId' => $id]);
            
        $passagers = [];
        foreach ($reservations as $res) {
            $u = $this->dm->find(User::class, $res->getPassagerId());
            if ($u) {
                // Vérifier si déjà noté par ce conducteur
                $dejaNote = $this->dm->getRepository(\App\Document\Avis::class)
                    ->findOneBy([
                        'auteurId' => (string)$this->getUser()->getId(),
                        'destinataireId' => (string)$u->getId(),
                        'trajetId' => $id,
                        'type' => 'conducteur_vers_passager'
                    ]);

                $passagers[] = [
                    'reservation' => $res,
                    'user' => $u,
                    'dejaNote' => $dejaNote !== null
                ];
            }
        }

        return $this->render('trajet/detail-conducteur.html.twig', [
            'trajet' => $trajet,
            'passagers' => $passagers,
        ]);
    }

    #[Route('/{id}/modifier', name: 'modifier', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_CONDUCTEUR')]
    public function modifier(string $id, Request $request): Response
    {
        $trajet = $this->dm->find(Trajet::class, $id);
        if (!$trajet || $trajet->getConducteurId() !== (string)$this->getUser()->getId()) {
            throw $this->createAccessDeniedException();
        }

        $form = $this->createForm(TrajetType::class, $trajet);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Fusionner dateDepart + heureDepart en un seul DateTime
            $heureDepart = $trajet->getHeureDepart();
            if ($trajet->getDateDepart() && $heureDepart && preg_match('/^\d{2}:\d{2}$/', $heureDepart)) {
                [$h, $m] = explode(':', $heureDepart);
                $date = \DateTime::createFromInterface($trajet->getDateDepart());
                $date->setTime((int)$h, (int)$m, 0);
                $trajet->setDateDepart($date);
            }

            $trajet->setUpdatedAt(new \DateTimeImmutable());
            $this->dm->flush();
            $this->addFlash('success', 'Trajet mis à jour avec succès.');
            return $this->redirectToRoute('trajet_mes_trajets');
        }

        return $this->render('conducteur/publier-trajet.html.twig', [
            'trajetForm' => $form->createView(),
            'trajet'     => $trajet, // permet au template de détecter le mode édition
            'vehicules'  => [],
        ]);
    }
}

<?php

namespace App\Controller\Admin;

use App\Document\Avis;
use App\Document\Reservation;
use App\Document\Trajet;
use App\Document\User;
use Doctrine\ODM\MongoDB\DocumentManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/statistiques')]
#[IsGranted('ROLE_ADMIN')]
class StatistiquesController extends AbstractController
{
    public function __construct(private readonly DocumentManager $dm)
    {
    }

    private function computeStats(): array
    {
        $trajetRepo = $this->dm->getRepository(Trajet::class);
        $reservationRepo = $this->dm->getRepository(Reservation::class);
        $userRepo = $this->dm->getRepository(User::class);
        $avisRepo = $this->dm->getRepository(Avis::class);

        $allTrajets = $trajetRepo->findAll();
        $allReservations = $reservationRepo->findAll();
        $allUsers = $userRepo->findAll();
        $allAvis = $avisRepo->findAll();

        // Trajets stats
        $totalTrajets = count($allTrajets);
        $trajetsTermine = 0;
        $trajetsActif = 0;
        $trajetsAnnule = 0;
        $trajetsEnCours = 0;
        foreach ($allTrajets as $t) {
            match ($t->getStatut()) {
                'termine' => $trajetsTermine++,
                'actif' => $trajetsActif++,
                'annule' => $trajetsAnnule++,
                'en_cours' => $trajetsEnCours++,
                default => null,
            };
        }

        // Reservations stats
        $totalReservations = count($allReservations);
        $reservationsConfirmee = 0;
        $reservationsAnnulee = 0;
        $reservationsTerminee = 0;
        $totalArgent = 0.0;
        foreach ($allReservations as $r) {
            match ($r->getStatut()) {
                'confirmee' => $reservationsConfirmee++,
                'annulee' => $reservationsAnnulee++,
                'terminee' => $reservationsTerminee++,
                default => null,
            };
            $totalArgent += $r->getPrixTotal();
        }

        // Users stats
        $totalUsers = count($allUsers);
        $usersActif = 0;
        $usersSuspendu = 0;
        $usersEnAttente = 0;
        $conducteurs = 0;
        $passagers = 0;
        $admins = 0;
        foreach ($allUsers as $u) {
            match ($u->getStatut()) {
                'actif' => $usersActif++,
                'suspendu' => $usersSuspendu++,
                'en_attente' => $usersEnAttente++,
                default => null,
            };
            $roles = $u->getRoles();
            if (in_array('ROLE_ADMIN', $roles))
                $admins++;
            elseif (in_array('ROLE_CONDUCTEUR', $roles))
                $conducteurs++;
            else
                $passagers++;
        }

        // Avis stats
        $totalAvis = count($allAvis);
        $sommeNotes = 0;
        foreach ($allAvis as $a) {
            $sommeNotes += $a->getNote();
        }
        $noteMoyenne = $totalAvis > 0 ? round($sommeNotes / $totalAvis, 2) : 0;

        return compact(
            'totalTrajets',
            'trajetsTermine',
            'trajetsActif',
            'trajetsAnnule',
            'trajetsEnCours',
            'totalReservations',
            'reservationsConfirmee',
            'reservationsAnnulee',
            'reservationsTerminee',
            'totalArgent',
            'totalUsers',
            'usersActif',
            'usersSuspendu',
            'usersEnAttente',
            'conducteurs',
            'passagers',
            'admins',
            'totalAvis',
            'noteMoyenne'
        );
    }

    #[Route('', name: 'admin_statistiques', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('admin/statistiques.html.twig', $this->computeStats());
    }

    #[Route('/telecharger', name: 'admin_statistiques_telecharger', methods: ['GET'])]
    public function telecharger(): StreamedResponse
    {
        $stats = $this->computeStats();
        $date = (new \DateTimeImmutable())->format('d/m/Y H:i');

        $response = new StreamedResponse(function () use ($stats, $date) {
            $handle = fopen('php://output', 'w');

            // UTF-8 BOM for Excel compatibility
            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));

            fputcsv($handle, ['RAPPORT STATISTIQUES — COVO'], ';');
            fputcsv($handle, ["Généré le : $date"], ';');
            fputcsv($handle, [], ';');

            fputcsv($handle, ['=== TRAJETS ==='], ';');
            fputcsv($handle, ['Total trajets', $stats['totalTrajets']], ';');
            fputcsv($handle, ['Trajets actifs', $stats['trajetsActif']], ';');
            fputcsv($handle, ['Trajets en cours', $stats['trajetsEnCours']], ';');
            fputcsv($handle, ['Trajets terminés', $stats['trajetsTermine']], ';');
            fputcsv($handle, ['Trajets annulés', $stats['trajetsAnnule']], ';');
            fputcsv($handle, [], ';');

            fputcsv($handle, ['=== RÉSERVATIONS ==='], ';');
            fputcsv($handle, ['Total réservations', $stats['totalReservations']], ';');
            fputcsv($handle, ['Confirmées', $stats['reservationsConfirmee']], ';');
            fputcsv($handle, ['Terminées', $stats['reservationsTerminee']], ';');
            fputcsv($handle, ['Annulées', $stats['reservationsAnnulee']], ';');
            fputcsv($handle, ['Montant total reçu (TND)', number_format($stats['totalArgent'], 2, '.', '')], ';');
            fputcsv($handle, [], ';');

            fputcsv($handle, ['=== UTILISATEURS ==='], ';');
            fputcsv($handle, ['Total utilisateurs', $stats['totalUsers']], ';');
            fputcsv($handle, ['Comptes actifs', $stats['usersActif']], ';');
            fputcsv($handle, ['Comptes suspendus', $stats['usersSuspendu']], ';');
            fputcsv($handle, ['En attente validation', $stats['usersEnAttente']], ';');
            fputcsv($handle, ['Conducteurs', $stats['conducteurs']], ';');
            fputcsv($handle, ['Passagers', $stats['passagers']], ';');
            fputcsv($handle, ['Admins', $stats['admins']], ';');
            fputcsv($handle, [], ';');

            fputcsv($handle, ['=== AVIS ==='], ';');
            fputcsv($handle, ['Total avis', $stats['totalAvis']], ';');
            fputcsv($handle, ['Note moyenne globale', $stats['noteMoyenne'] . ' / 5'], ';');

            fclose($handle);
        });

        $filename = 'statistiques_covo_' . (new \DateTimeImmutable())->format('Ymd_His') . '.csv';
        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', "attachment; filename=\"$filename\"");

        return $response;
    }
}
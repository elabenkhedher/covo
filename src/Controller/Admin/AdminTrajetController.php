<?php

namespace App\Controller\Admin;

use App\Document\Trajet;
use App\Repository\TrajetRepository;
use App\Repository\UserRepository;
use Doctrine\ODM\MongoDB\DocumentManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin')]
#[IsGranted('ROLE_ADMIN')]
class AdminTrajetController extends AbstractController
{
    private DocumentManager $dm;
    private TrajetRepository $trajetRepository;
    private UserRepository $userRepository;

    public function __construct(DocumentManager $dm, TrajetRepository $trajetRepository, UserRepository $userRepository)
    {
        $this->dm = $dm;
        $this->trajetRepository = $trajetRepository;
        $this->userRepository = $userRepository;
    }

    #[Route('/trajets', name: 'admin_trajet_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $filtres = [
            'statut' => $request->query->get('statut'),
            'masque' => $request->query->get('masque') !== null && $request->query->get('masque') !== '' ? (bool) $request->query->get('masque') : null,
            'villeDepart' => $request->query->get('villeDepart'),
            'villeArrivee' => $request->query->get('villeArrivee'),
            'conducteurId' => $request->query->get('conducteurId'),
        ];

        $trajets = $this->trajetRepository->findAllForAdmin($filtres);

        $trajetsData = [];
        foreach ($trajets as $trajet) {
            $conducteur = $this->userRepository->find($trajet->getConducteurId());
            $trajetsData[] = [
                'trajet' => $trajet,
                'conducteur' => $conducteur
            ];
        }

        return $this->render('admin/trajet/index.html.twig', [
            'trajetsData' => $trajetsData,
            'filtres' => $filtres,
        ]);
    }

    #[Route('/{id}', name: 'admin_trajet_detail', methods: ['GET'], requirements: ['id' => '[a-fA-F0-9]{24}'])]
    public function detail(string $id): Response
    {
        $trajet = $this->trajetRepository->find($id);
        if (!$trajet) {
            throw $this->createNotFoundException('Trajet non trouvé.');
        }

        $conducteur = $this->userRepository->find($trajet->getConducteurId());

        return $this->render('admin/trajet/detail.html.twig', [
            'trajet' => $trajet,
            'conducteur' => $conducteur,
        ]);
    }

    #[Route('/{id}/masquer', name: 'admin_trajet_masquer', methods: ['POST'], requirements: ['id' => '[a-fA-F0-9]{24}'])]
    public function masquer(string $id, Request $request): Response
    {
        $trajet = $this->trajetRepository->find($id);
        if (!$trajet) {
            throw $this->createNotFoundException('Trajet non trouvé.');
        }

        if (!$this->isCsrfTokenValid('admin_trajet_' . $trajet->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('admin_trajet_detail', ['id' => $id]);
        }

        $trajet->setMasque(true);
        $trajet->setUpdatedAt(new \DateTimeImmutable());

        $this->dm->flush();

        $this->addFlash('success', 'Trajet masqué.');
        return $this->redirectToRoute('admin_trajet_detail', ['id' => $id]);
    }

    #[Route('/{id}/reactiver', name: 'admin_trajet_reactiver', methods: ['POST'], requirements: ['id' => '[a-fA-F0-9]{24}'])]
    public function reactiver(string $id, Request $request): Response
    {
        $trajet = $this->trajetRepository->find($id);
        if (!$trajet) {
            throw $this->createNotFoundException('Trajet non trouvé.');
        }

        if (!$this->isCsrfTokenValid('admin_trajet_' . $trajet->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('admin_trajet_detail', ['id' => $id]);
        }

        $trajet->setMasque(false);
        $trajet->setUpdatedAt(new \DateTimeImmutable());

        $this->dm->flush();

        $this->addFlash('success', 'Trajet réactivé.');
        return $this->redirectToRoute('admin_trajet_detail', ['id' => $id]);
    }

    #[Route('/{id}/noter', name: 'admin_trajet_noter', methods: ['POST'], requirements: ['id' => '[a-fA-F0-9]{24}'])]
    public function noterModeration(string $id, Request $request): Response
    {
        $trajet = $this->trajetRepository->find($id);
        if (!$trajet) {
            throw $this->createNotFoundException('Trajet non trouvé.');
        }

        if (!$this->isCsrfTokenValid('admin_trajet_note_' . $trajet->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('admin_trajet_detail', ['id' => $id]);
        }

        $note = $request->request->get('note');
        $trajet->setNoteModeration($note);
        $trajet->setUpdatedAt(new \DateTimeImmutable());

        $this->dm->flush();

        $this->addFlash('success', 'Note enregistrée.');
        return $this->redirectToRoute('admin_trajet_detail', ['id' => $id]);
    }
}

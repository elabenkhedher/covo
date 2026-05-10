<?php
namespace App\Controller;

use App\Document\User;
use App\Repository\UserRepository;
use Doctrine\ODM\MongoDB\DocumentManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin')]
#[IsGranted('ROLE_ADMIN')]
class AdminController extends AbstractController
{
    public function __construct(
        private readonly DocumentManager $dm,
        private readonly UserRepository  $userRepo,
    ) {}

    #[Route('/users', name: 'admin_users', methods: ['GET'])]
    public function users(Request $request): Response
    {
        $role   = $request->query->get('role');
        $statut = $request->query->get('statut');
        $users  = $this->userRepo->findWithFilters(
            in_array($role,   ['conducteur','passager','admin'], true) ? $role   : null,
            in_array($statut, ['actif','suspendu','en_attente'], true) ? $statut : null,
        );
        return $this->render('admin/users.html.twig', [
            'users'         => $users,
            'filter_role'   => $role,
            'filter_statut' => $statut,
        ]);
    }

    #[Route('/users/{id}/valider', name: 'admin_user_valider', methods: ['POST'])]
    public function valider(string $id): Response
    {
        $user = $this->dm->find(User::class, $id);
        if (!$user) throw $this->createNotFoundException();
        $user->setStatut('actif');
        $this->dm->flush();
        $this->addFlash('success', "Compte de {$user->getFullName()} validé.");
        return $this->redirectToRoute('admin_users');
    }

    #[Route('/users/{id}/toggle-suspension', name: 'admin_user_toggle_suspension', methods: ['POST'])]
    public function toggleSuspension(string $id): Response
    {
        $user = $this->dm->find(User::class, $id);
        if (!$user) throw $this->createNotFoundException();
        if ($user === $this->getUser()) {
            $this->addFlash('error', 'Impossible de suspendre votre propre compte.');
            return $this->redirectToRoute('admin_users');
        }
        if ($user->getStatut() === 'suspendu') {
            $user->setStatut('actif');
            $this->addFlash('success', "Compte de {$user->getFullName()} réactivé.");
        } else {
            $user->setStatut('suspendu');
            $this->addFlash('warning', "Compte de {$user->getFullName()} suspendu.");
        }
        $this->dm->flush();
        return $this->redirectToRoute('admin_users');
    }

    #[Route('/users/{id}/supprimer', name: 'admin_user_supprimer', methods: ['POST'])]
    public function supprimer(string $id, Request $request): Response
    {
        $user = $this->dm->find(User::class, $id);
        if (!$user) throw $this->createNotFoundException();
        if (!$this->isCsrfTokenValid('delete_user_'.$id, $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('admin_users');
        }
        if ($user === $this->getUser()) {
            $this->addFlash('error', 'Impossible de supprimer votre propre compte.');
            return $this->redirectToRoute('admin_users');
        }
        $nom = $user->getFullName();
        $this->dm->remove($user);
        $this->dm->flush();
        $this->addFlash('success', "Compte de {$nom} supprimé définitivement.");
        return $this->redirectToRoute('admin_users');
    }

    #[Route('/users/{id}/historique', name: 'admin_user_historique', methods: ['GET'])]
    public function historique(string $id): Response
    {
        $user = $this->dm->find(User::class, $id);
        if (!$user) throw $this->createNotFoundException();
        return $this->render('admin/user_historique.html.twig', [
            'user'            => $user,
            'nb_trajets'      => $this->userRepo->countTrajetsForUser($id),
            'nb_reservations' => $this->userRepo->countReservationsForUser($id),
        ]);
    }
}
?>

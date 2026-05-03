<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(): Response
    {
        if ($this->getUser()) {
            if ($this->isGranted('ROLE_CONDUCTEUR')) {
                return $this->redirectToRoute('conducteur_dashboard');
            }
            if ($this->isGranted('ROLE_PASSAGER')) {
                return $this->redirectToRoute('passager_dashboard');
            }
        }

        return $this->render('index.html.twig');
    }

    #[Route('/compte/supprimer', name: 'app_delete_account', methods: ['POST'])]
    public function deleteAccount(): Response
    {
        // TODO: Implémenter la suppression
        $this->addFlash('success', 'Votre compte a été supprimé.');
        return $this->redirectToRoute('app_logout');
    }
}

<?php

namespace App\Controller;

use App\Document\User;
use App\Form\RegistrationFormType;
use App\Security\AppAuthAuthenticator;
use Doctrine\ODM\MongoDB\DocumentManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

class RegistrationController extends AbstractController
{
    #[Route('/register', name: 'app_register')]
    public function register(Request $request, UserPasswordHasherInterface $userPasswordHasher, Security $security, DocumentManager $documentManager): Response
    {
        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var string $plainPassword */
            $plainPassword = $form->get('plainPassword')->getData();

            // encode the plain password
            $user->setPassword($userPasswordHasher->hashPassword($user, $plainPassword));

            // assign chosen role (passager or conducteur); ROLE_USER is added automatically
            $chosenRole = $form->get('roleChoice')->getData();
            $user->setRoles([$chosenRole]);

            $documentManager->persist($user);
            $documentManager->flush();

            // do anything else you need here, like send an email

            return $security->login($user, AppAuthAuthenticator::class, 'main');
        }

        return $this->render('register.html.twig', [
            'registrationForm' => $form,
        ]);
    }
}

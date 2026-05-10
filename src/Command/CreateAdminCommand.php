<?php

namespace App\Command;

use App\Document\User;
use Doctrine\ODM\MongoDB\DocumentManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:create-admin',
    description: 'Crée un utilisateur administrateur.',
)]
class CreateAdminCommand extends Command
{
    private DocumentManager $dm;
    private UserPasswordHasherInterface $passwordHasher;

    public function __construct(DocumentManager $dm, UserPasswordHasherInterface $passwordHasher)
    {
        parent::__construct();
        $this->dm = $dm;
        $this->passwordHasher = $passwordHasher;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $email = $io->ask('Email de l\'administrateur', 'admin@covo.tn');
        
        $user = $this->dm->getRepository(User::class)->findOneBy(['email' => $email]);
        $isNew = false;

        if (!$user) {
            $user = new User();
            $user->setEmail($email);
            $isNew = true;
        } else {
            $io->note(sprintf('L\'utilisateur %s existe déjà. Mise à jour de ses informations.', $email));
        }

        $password = $io->askHidden('Mot de passe (laisser vide pour ne pas changer)');
        $prenom = $io->ask('Prénom', $user->getPrenom() ?? 'Admin');
        $nom = $io->ask('Nom', $user->getNom() ?? 'Covo');

        $user->setPrenom($prenom);
        $user->setNom($nom);
        $user->setRoles(['ROLE_ADMIN']);
        
        if ($isNew || !empty($user->getTelephone())) {
             $user->setTelephone($user->getTelephone() ?? '00000000');
        }

        if (!empty($password)) {
            $hashedPassword = $this->passwordHasher->hashPassword($user, $password);
            $user->setPassword($hashedPassword);
        }

        if ($isNew) {
            $this->dm->persist($user);
        }
        
        $this->dm->flush();

        $io->success(sprintf('L\'administrateur %s a été %s avec succès.', $email, $isNew ? 'créé' : 'mis à jour'));

        return Command::SUCCESS;
    }
}

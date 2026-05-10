<?php

require_once __DIR__ . '/vendor/autoload.php';

use App\Kernel;
use App\Document\User;
use Symfony\Component\Dotenv\Dotenv;

$dotenv = new Dotenv();
$dotenv->loadEnv(__DIR__ . '/.env');

$kernel = new Kernel($_SERVER['APP_ENV'] ?? 'dev', (bool) ($_SERVER['APP_DEBUG'] ?? true));
$kernel->boot();

$dm = $kernel->getContainer()->get('doctrine_mongodb')->getManager();
$passwordHasher = $kernel->getContainer()->get('security.user_password_hasher');

$email = 'admin@covo.tn';
$user = new User();
$user->setEmail($email);
$user->setPrenom('Admin');
$user->setNom('Covo');
$user->setRoles(['ROLE_ADMIN']);
$user->setTelephone('00000000');

$hashedPassword = $passwordHasher->hashPassword($user, 'password');
$user->setPassword($hashedPassword);

$dm->persist($user);
$dm->flush();

echo "User $email created successfully with ROLE_ADMIN.\n";

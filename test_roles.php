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

$email = 'test_roles_' . time() . '@test.com';
$user = new User();
$user->setEmail($email);
$user->setPrenom('Test');
$user->setNom('Roles');
$user->setRoles(['ROLE_ADMIN', 'ROLE_MODO']);
$user->setTelephone('12345678');
$user->setPassword('test');

$dm->persist($user);
$dm->flush();
$dm->clear();

$userFound = $dm->getRepository(User::class)->findOneBy(['email' => $email]);

echo "User created: $email\n";
echo "Roles from DB: " . json_encode($userFound->getRoles()) . "\n";

$reflection = new \ReflectionClass($userFound);
$property = $reflection->getProperty('roles');
$property->setAccessible(true);
echo "Internal \$roles property from DB: " . json_encode($property->getValue($userFound)) . "\n";

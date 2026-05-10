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
$repository = $dm->getRepository(User::class);

$email = 'admin@covo.tn'; // Default email used in the command
$user = $repository->findOneBy(['email' => $email]);

if (!$user) {
    echo "User not found: $email\n";
    exit(1);
}

echo "User found: " . $user->getEmail() . "\n";
echo "Roles: " . implode(', ', $user->getRoles()) . "\n";
echo "Raw Roles in document: " . json_encode($user->getRoles()) . "\n";

// Accessing private property via reflection to see the exact state of $roles
$reflection = new \ReflectionClass($user);
$property = $reflection->getProperty('roles');
$property->setAccessible(true);
echo "Internal \$roles property: " . json_encode($property->getValue($user)) . "\n";

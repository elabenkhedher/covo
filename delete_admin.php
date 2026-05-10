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
$user = $dm->getRepository(User::class)->findOneBy(['email' => 'admin@covo.tn']);

if ($user) {
    $dm->remove($user);
    $dm->flush();
    echo "User admin@covo.tn deleted.\n";
} else {
    echo "User not found.\n";
}

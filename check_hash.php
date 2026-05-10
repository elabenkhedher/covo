<?php
require_once __DIR__ . '/vendor/autoload.php';
use App\Kernel;
use App\Document\User;
use Symfony\Component\Dotenv\Dotenv;

(new Dotenv())->loadEnv(__DIR__ . '/.env');
$kernel = new Kernel('dev', true);
$kernel->boot();

$dm = $kernel->getContainer()->get('doctrine_mongodb')->getManager();
$user = $dm->getRepository(User::class)->findOneBy(['email' => 'admin@covo.tn']);

if ($user) {
    echo "User found: " . $user->getEmail() . "\n";
    echo "Password hash: " . $user->getPassword() . "\n";
    echo "Roles: " . json_encode($user->getRoles()) . "\n";
} else {
    echo "User admin@covo.tn not found.\n";
}

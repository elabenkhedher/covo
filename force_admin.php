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
    $dm->remove($user);
    $dm->flush();
}

$user = new User();
$user->setEmail('admin@covo.tn');
$user->setPrenom('Admin');
$user->setNom('Covo');
$user->setRoles(['ROLE_ADMIN']); // Forçage du rôle admin
$user->setTelephone('00000000');
// admin123
$user->setPassword(password_hash('admin123', PASSWORD_BCRYPT));

$dm->persist($user);
$dm->flush();

echo "User admin@covo.tn recreated with roles: " . json_encode($user->getRoles()) . "\n";

<?php
// recreate_admin_raw.php
require_once __DIR__ . '/vendor/autoload.php';
use App\Kernel;
use App\Document\User;
use Symfony\Component\Dotenv\Dotenv;

(new Dotenv())->loadEnv(__DIR__ . '/.env');
$kernel = new Kernel('dev', true);
$kernel->boot();

$dm = $kernel->getContainer()->get('doctrine_mongodb')->getManager();

$email = 'admin@covo.tn';
$user = new User();
$user->setEmail($email);
$user->setPrenom('Admin');
$user->setNom('Covo');
$user->setRoles(['ROLE_ADMIN']);
$user->setTelephone('00000000');

// Génération manuelle du hash Bcrypt pour 'admin123'
$hashedPassword = password_hash('admin123', PASSWORD_BCRYPT);
$user->setPassword($hashedPassword);

$dm->persist($user);
$dm->flush();

echo "User $email recreated successfully with manual Bcrypt hash.\n";
echo "Hash: " . $hashedPassword . "\n";

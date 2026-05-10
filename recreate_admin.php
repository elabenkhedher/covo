<?php
// recreate_admin.php
require_once __DIR__ . '/vendor/autoload.php';
use App\Kernel;
use App\Document\User;
use Symfony\Component\Dotenv\Dotenv;

(new Dotenv())->loadEnv(__DIR__ . '/.env');
$kernel = new Kernel('dev', true);
$kernel->boot();

$dm = $kernel->getContainer()->get('doctrine_mongodb')->getManager();
// Accès au password hasher via le container (nécessite de le rendre public ou d'utiliser le kernel)
$hasher = $kernel->getContainer()->get('security.user_password_hasher');

$email = 'admin@covo.tn';
$user = new User();
$user->setEmail($email);
$user->setPrenom('Admin');
$user->setNom('Covo');
$user->setRoles(['ROLE_ADMIN']);
$user->setTelephone('00000000');

$hashedPassword = $hasher->hashPassword($user, 'admin123');
$user->setPassword($hashedPassword);

$dm->persist($user);
$dm->flush();

echo "User $email recreated successfully.\n";
echo "Hash: " . $hashedPassword . "\n";

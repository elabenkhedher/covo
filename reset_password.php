<?php
// reset_password.php
require_once __DIR__ . '/vendor/autoload.php';
use App\Kernel;
use App\Document\User;
use Symfony\Component\Dotenv\Dotenv;

(new Dotenv())->loadEnv(__DIR__ . '/.env');
$kernel = new Kernel($_SERVER['APP_ENV'] ?? 'dev', (bool) ($_SERVER['APP_DEBUG'] ?? true));
$kernel->boot();

$dm = $kernel->getContainer()->get('doctrine_mongodb')->getManager();
// Utilisation d'une astuce pour récupérer le service s'il n'est pas public
$hasher = $kernel->getContainer()->get('security.user_password_hasher');

$email = 'admin@covo.tn';
$user = $dm->getRepository(User::class)->findOneBy(['email' => $email]);

if ($user) {
    $user->setPassword($hasher->hashPassword($user, 'admin123'));
    $dm->flush();
    echo "Succès : Le mot de passe de $email est maintenant 'admin123'\n";
} else {
    echo "Erreur : Utilisateur $email non trouvé.\n";
}

<?php
// Script de débogage - à supprimer après usage
require __DIR__.'/vendor/autoload.php';

$kernel = new \App\Kernel('dev', true);
$kernel->boot();
$container = $kernel->getContainer();
$dm = $container->get('doctrine_mongodb.odm.document_manager');

$trajets = $dm->getRepository(\App\Document\Trajet::class)->findAll();
echo "Total trajets dans la collection: " . count($trajets) . PHP_EOL;

foreach ($trajets as $t) {
    echo "ID: " . $t->getId() . " | conducteurId: " . $t->getConducteurId() . " | statut: " . $t->getStatut() . " | ville: " . $t->getVilleDepart() . " -> " . $t->getVilleArrivee() . PHP_EOL;
}

$user = $dm->getRepository(\App\Document\User::class)->findAll();
echo "\nTotal users: " . count($user) . PHP_EOL;
foreach ($user as $u) {
    echo "User ID: " . $u->getId() . " | email: " . $u->getEmail() . " | roles: " . implode(', ', $u->getRoles()) . PHP_EOL;
}

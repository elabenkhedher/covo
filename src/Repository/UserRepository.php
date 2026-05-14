<?php

namespace App\Repository;

use App\Document\User;
use Doctrine\Bundle\MongoDBBundle\Repository\ServiceDocumentRepository;
use Doctrine\Bundle\MongoDBBundle\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends ServiceDocumentRepository<User>
 */
class UserRepository extends ServiceDocumentRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    /**
     * Used to upgrade (rehash) the user's password automatically over time.
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setPassword($newHashedPassword);
        $this->getDocumentManager()->persist($user);
        $this->getDocumentManager()->flush();
    }
    public function findWithFilters(?string $role = null, ?string $statut = null): array
    {
        $qb = $this->createQueryBuilder();
        if ($role) {
            $map = ['conducteur' => 'ROLE_CONDUCTEUR', 'passager' => 'ROLE_PASSAGER', 'admin' => 'ROLE_ADMIN'];
            if (isset($map[$role])) { $qb->field('roles')->equals($map[$role]); }
        }
        if ($statut) { $qb->field('statut')->equals($statut); }
        return $qb->sort('nom', 'asc')->getQuery()->execute()->toArray();
    }

    public function countTrajetsForUser(string $userId): int
    {
        $col = $this->getDocumentManager()->getDocumentCollection(\App\Document\Trajet::class);
        return (int) $col->countDocuments(['conducteurId' => $userId]);
    }

    public function countReservationsForUser(string $userId): int
    {
        $col = $this->getDocumentManager()->getDocumentCollection(\App\Document\Reservation::class);
        return (int) $col->countDocuments(['passagerId' => $userId]);
    }
}
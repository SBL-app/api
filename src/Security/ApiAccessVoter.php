<?php

namespace App\Security;

use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class ApiAccessVoter extends Voter
{
    const WRITE = 'WRITE';
    const READ = 'READ';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::WRITE, self::READ]);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        // Si l'utilisateur n'est pas connecté, on refuse l'accès aux opérations d'écriture
        if (!$user instanceof User) {
            return $attribute === self::READ;
        }

        // Vérifier si l'utilisateur est actif
        if (!$user->isActive()) {
            return false;
        }

        return match ($attribute) {
            self::READ => true, // Tous les utilisateurs authentifiés peuvent lire
            self::WRITE => $this->canWrite($user),
            default => false
        };
    }

    private function canWrite(User $user): bool
    {
        // Vérifier si l'utilisateur a le rôle API ou admin
        return in_array('ROLE_API', $user->getRoles()) ||
            in_array('ROLE_ADMIN', $user->getRoles());
    }
}

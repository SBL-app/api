<?php

namespace App\Security;

use App\Entity\User;
use App\Service\AuthenticationService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * Trait pour ajouter des méthodes de sécurité aux contrôleurs
 */
trait SecuredControllerTrait
{
    /**
     * Vérifie si l'utilisateur actuel peut effectuer des opérations de modification
     */
    protected function checkModificationPermissions(): void
    {
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user instanceof User) {
            throw new AccessDeniedException('Authentication required');
        }

        if (!$this->authService->canUserModifyData($user)) {
            throw new AccessDeniedException('Insufficient permissions for data modification');
        }
    }

    /**
     * Vérifie si l'utilisateur actuel a un rôle spécifique
     */
    protected function checkUserRole(string $role): void
    {
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user instanceof User) {
            throw new AccessDeniedException('Authentication required');
        }

        if (!$this->authService->userHasRole($user, $role)) {
            throw new AccessDeniedException("Role $role required");
        }
    }

    /**
     * Retourne l'utilisateur actuel ou lance une exception
     */
    protected function getAuthenticatedUser(): User
    {
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user instanceof User) {
            throw new AccessDeniedException('Authentication required');
        }

        return $user;
    }

    /**
     * Retourne une réponse d'erreur standardisée pour les permissions
     */
    protected function permissionDeniedResponse(string $message = 'Insufficient permissions'): JsonResponse
    {
        return $this->json(['error' => $message], 403);
    }

    /**
     * Retourne une réponse d'erreur standardisée pour l'authentification
     */
    protected function authenticationRequiredResponse(string $message = 'Authentication required'): JsonResponse
    {
        return $this->json(['error' => $message], 401);
    }
}

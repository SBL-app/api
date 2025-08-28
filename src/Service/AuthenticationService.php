<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\UserRepository;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Service pour la gestion des tokens JWT et l'authentification
 */
class AuthenticationService
{
    public function __construct(
        private JWTTokenManagerInterface $jwtManager,
        private UserRepository $userRepository
    ) {}

    /**
     * Extrait le token JWT de la requête
     */
    public function extractTokenFromRequest(Request $request): ?string
    {
        $authHeader = $request->headers->get('Authorization');

        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return null;
        }

        return substr($authHeader, 7);
    }

    /**
     * Vérifie si un token JWT est valide
     */
    public function isTokenValid(string $token): bool
    {
        try {
            $payload = $this->jwtManager->parse($token);
            return isset($payload['username']) && isset($payload['exp']) && $payload['exp'] > time();
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Vérifie si un token JWT peut être rafraîchi (pas trop ancien)
     */
    public function canTokenBeRefreshed(string $token, int $maxRefreshHours = 24): bool
    {
        try {
            $payload = $this->jwtManager->parse($token);
            $expiration = $payload['exp'] ?? 0;
            $maxRefreshTime = $maxRefreshHours * 60 * 60;

            return (time() - $expiration) <= $maxRefreshTime;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Récupère l'utilisateur à partir d'un token JWT
     */
    public function getUserFromToken(string $token): ?User
    {
        try {
            $payload = $this->jwtManager->parse($token);
            $username = $payload['username'] ?? null;

            if (!$username) {
                return null;
            }

            $user = $this->userRepository->findOneBy(['username' => $username]);

            if (!$user || !$user->isActive()) {
                return null;
            }

            return $user;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Crée un nouveau token JWT pour un utilisateur
     */
    public function createTokenForUser(User $user): string
    {
        return $this->jwtManager->create($user);
    }

    /**
     * Valide une clé API
     */
    public function validateApiKey(string $apiKey): ?User
    {
        $user = $this->userRepository->findOneBy(['apiKey' => $apiKey]);

        if (!$user || !$user->isActive()) {
            return null;
        }

        if (!in_array('ROLE_API', $user->getRoles())) {
            return null;
        }

        return $user;
    }

    /**
     * Vérifie si un utilisateur a le rôle requis
     */
    public function userHasRole(User $user, string $role): bool
    {
        return in_array($role, $user->getRoles());
    }

    /**
     * Vérifie si un utilisateur peut effectuer des opérations de modification
     */
    public function canUserModifyData(User $user): bool
    {
        return $this->userHasRole($user, 'ROLE_API') || $this->userHasRole($user, 'ROLE_ADMIN');
    }

    /**
     * Formate les informations utilisateur pour les réponses API
     */
    public function formatUserResponse(User $user): array
    {
        return [
            'id' => $user->getId(),
            'username' => $user->getUsername(),
            'roles' => $user->getRoles(),
            'last_login' => $user->getLastLogin()?->format('Y-m-d H:i:s'),
            'is_active' => $user->isActive()
        ];
    }
}

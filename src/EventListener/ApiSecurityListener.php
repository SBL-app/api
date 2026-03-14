<?php

namespace App\EventListener;

use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

#[AsEventListener(event: KernelEvents::REQUEST, priority: 0)]
class ApiSecurityListener
{
    private const PROTECTED_METHODS = ['POST', 'PUT', 'PATCH', 'DELETE'];
    private const EXCLUDED_PATHS = [
        '/api/auth/login',
        '/api/auth/login-api-key'
    ];
    private const ROLE_USER_PREFIXES = [
        '/api/users/me',
        '/api/push/subscribe',
    ];

    public function __construct(private Security $security) {}

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();
        $path = $request->getPathInfo();
        $method = $request->getMethod();

        // Ne pas traiter les routes non-API
        if (!str_starts_with($path, '/api')) {
            return;
        }

        // Ne pas traiter les routes exclues
        if (in_array($path, self::EXCLUDED_PATHS)) {
            return;
        }

        // Ne protéger que les méthodes de modification pour maintenant
        if (!in_array($method, self::PROTECTED_METHODS)) {
            return;
        }

        // Routes de gestion d'équipe accessibles à ROLE_USER (pas besoin de ROLE_API)
        if ($this->isUserAccessPath($path)) {
            return;
        }

        $user = $this->security->getUser();

        if (!$user) {
            $response = new JsonResponse([
                'error' => 'Authentication required for this operation',
                'message' => 'Please authenticate using /api/auth/login to perform write operations'
            ], 401);

            $event->setResponse($response);
            return;
        }

        // Vérifier que l'utilisateur a les bonnes permissions
        if (!$this->security->isGranted('ROLE_API')) {
            $response = new JsonResponse([
                'error' => 'Insufficient permissions',
                'message' => 'API access role required for this operation'
            ], 403);

            $event->setResponse($response);
            return;
        }
    }

    private function isUserAccessPath(string $path): bool
    {
        foreach (self::ROLE_USER_PREFIXES as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return true;
            }
        }

        // Match /api/teams/{id}/members and /api/teams/{id}/members/role
        if (preg_match('#^/api/teams/\d+/members(/role)?$#', $path)) {
            return true;
        }

        return false;
    }
}

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
        '/api/auth/login-api-key',
        '/api/auth/create-user'
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
}

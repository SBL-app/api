<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\HttpFoundation\Request;

class TestController extends AbstractController
{
    private RouterInterface $router;

    public function __construct(RouterInterface $router)
    {
        $this->router = $router;
    }

    #[Route('/', name: 'app_root', methods: ['GET'])]
    public function index(): JsonResponse
    {
        // Récupérer toutes les routes
        $routeCollection = $this->router->getRouteCollection();
        $availableRoutes = [];

        foreach ($routeCollection as $routeName => $route) {
            $methods = $route->getMethods();
            $path = $route->getPath();
            
            // Filtrer seulement les routes GET et exclure les routes internes de Symfony
            if ((empty($methods) || in_array('GET', $methods)) && 
                !str_starts_with($routeName, '_') && 
                !str_contains($path, '/_')) {
                
                $methodsStr = empty($methods) ? 'GET' : implode(', ', $methods);
                $availableRoutes[$methodsStr . ' ' . $path] = $routeName;
            }
        }

        // Trier les routes par chemin
        ksort($availableRoutes);

        return $this->json([
            'message' => 'API fonctionnelle !',
            'version' => '1.0',
            'timestamp' => date('Y-m-d H:i:s'),
            'available_routes' => $availableRoutes
        ]);
    }

    #[Route('/health', name: 'app_health', methods: ['GET'])]
    public function health(): JsonResponse
    {
        return $this->json([
            'status' => 'OK',
            'environment' => $_ENV['APP_ENV'] ?? 'unknown',
            'php_version' => PHP_VERSION
        ]);
    }

    #[Route('/cors-test', name: 'app_cors_test', methods: ['GET', 'POST', 'OPTIONS'])]
    public function corsTest(Request $request): JsonResponse
    {
        $response = $this->json([
            'message' => 'CORS test endpoint',
            'method' => $request->getMethod(),
            'origin' => $request->headers->get('Origin'),
            'timestamp' => date('Y-m-d H:i:s')
        ]);

        // Ajouter des en-têtes CORS explicites pour ce endpoint de test
        $response->headers->set('Access-Control-Allow-Origin', '*');
        $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, Accept, Origin, X-Requested-With');

        return $response;
    }
}

<?php

namespace App\Controller;

use App\Service\AdminDashboardService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/admin')]
class AdminDashboardController extends BaseController
{
    protected function formatEntityData($entity): array
    {
        throw new \LogicException('Not supported: this controller exposes aggregates only.');
    }

    /**
     * GET /api/admin/dashboard - Agrégats du dashboard admin (lecture seule, ROLE_ADMIN).
     */
    #[Route('/dashboard', name: 'api_admin_dashboard', methods: ['GET'])]
    public function dashboard(AdminDashboardService $service): JsonResponse
    {
        $this->checkUserRole('ROLE_ADMIN');

        return $this->json($service->getAggregates());
    }
}

<?php

namespace App\Controller;

use Doctrine\DBAL\Connection;
use Doctrine\Migrations\DependencyFactory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Sonde de supervision consommée par Uptime Kuma et par le test de fumée du
 * déploiement continu.
 *
 * Le contrat est volontairement stable : `status`, `version` et `commit` à la
 * racine, le détail par sonde sous `checks`. Un code HTTP 503 signale qu'au
 * moins une sonde critique est en défaut — c'est ce que la supervision
 * interprète comme une indisponibilité.
 *
 * L'endpoint n'expose rien de sensible : ni DSN, ni identifiants, ni détail
 * d'exception. Un échec de connexion remonte comme `"status": "error"` sans le
 * message du driver, qui contient l'URL de la base.
 */
#[Route('/api')]
class HealthController extends AbstractController
{
    public function __construct(
        private readonly Connection $connection,
        // Le bundle n'expose pas DependencyFactory à l'autowiring : le service
        // doit être désigné par son identifiant.
        #[Autowire(service: 'doctrine.migrations.dependency_factory')]
        private readonly DependencyFactory $migrations,
        #[Autowire('%app.version%')]
        private readonly string $version,
        #[Autowire('%app.commit%')]
        private readonly string $commit,
    ) {
    }

    // `app_health` est déjà pris par TestController::health(), une sonde
    // superficielle exposée sur /health. Celle-ci ne la remplace pas d'office :
    // la retirer est une décision à part, quelque chose peut la surveiller.
    #[Route('/health', name: 'app_api_health', methods: ['GET'])]
    public function health(): JsonResponse
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'migrations' => $this->checkMigrations(),
        ];

        // Le code HTTP traduit la disponibilité, le champ `status` la santé.
        // Une base injoignable rend l'API inutilisable : 503. Des migrations en
        // attente signalent une dérive de schéma, réelle mais non bloquante —
        // et un 503 sur ce motif ferait osciller la supervision pendant un
        // déploiement. L'état est donc « degraded » avec un 200, et une sonde
        // Uptime Kuma peut alerter dessus en cherchant le mot-clé
        // `"status":"ok"` dans le corps de la réponse.
        $available = Response::HTTP_OK === $checks['database']['status_code'];
        $degraded = false;
        foreach ($checks as $check) {
            if ('ok' !== $check['status']) {
                $degraded = true;
            }
        }

        foreach ($checks as $name => $check) {
            unset($checks[$name]['status_code']);
        }

        $status = 'ok';
        if (!$available) {
            $status = 'error';
        } elseif ($degraded) {
            $status = 'degraded';
        }

        return new JsonResponse(
            [
                'status' => $status,
                'version' => $this->version,
                'commit' => $this->commit,
                'timestamp' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
                'checks' => $checks,
            ],
            $available ? Response::HTTP_OK : Response::HTTP_SERVICE_UNAVAILABLE
        );
    }

    /**
     * Connectivité PostgreSQL et latence de l'aller-retour.
     */
    private function checkDatabase(): array
    {
        $start = microtime(true);

        try {
            $this->connection->executeQuery('SELECT 1');
        } catch (\Throwable) {
            // Le message du driver contient le DSN : on ne le propage pas.
            return [
                'status' => 'error',
                'error' => 'connexion impossible',
                'status_code' => Response::HTTP_SERVICE_UNAVAILABLE,
            ];
        }

        return [
            'status' => 'ok',
            'latency_ms' => round((microtime(true) - $start) * 1000, 2),
            'status_code' => Response::HTTP_OK,
        ];
    }

    /**
     * État des migrations Doctrine : une migration non appliquée signifie que
     * le schéma ne correspond pas au code déployé.
     */
    private function checkMigrations(): array
    {
        try {
            $this->migrations->getMetadataStorage()->ensureInitialized();

            $available = $this->migrations->getMigrationPlanCalculator()->getMigrations();
            $executed = $this->migrations->getMetadataStorage()->getExecutedMigrations();
            $pending = $this->migrations->getMigrationStatusCalculator()->getNewMigrations();
        } catch (\Throwable) {
            return ['status' => 'error', 'error' => 'état des migrations indisponible'];
        }

        return [
            'status' => 0 === count($pending) ? 'ok' : 'pending',
            'available' => count($available),
            'executed' => count($executed),
            'pending' => count($pending),
        ];
    }
}

<?php

namespace App\Controller;

use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Reçoit les erreurs JavaScript du front et les écrit dans le canal Monolog
 * `client`, pour qu'elles apparaissent à côté des logs serveur.
 *
 * L'endpoint est public (le navigateur n'est pas toujours authentifié quand
 * une erreur survient) : il est donc limité en débit par IP, borne la taille
 * de chaque champ et n'écrit que des données fournies par le client, jamais
 * interprétées. Aucun champ n'est repris tel quel comme niveau de log.
 */
#[Route('/api')]
class ClientLogController extends AbstractController
{
    private const LIMITS = [
        'message' => 1000,
        'stack' => 4000,
        'url' => 500,
        'route' => 200,
        'source' => 50,
    ];

    public function __construct(
        #[Autowire(service: 'monolog.logger.client')]
        private readonly LoggerInterface $logger,
        private readonly RateLimiterFactory $clientLogsLimiter,
    ) {
    }

    #[Route('/client-logs', name: 'app_api_client_logs', methods: ['POST'])]
    public function collect(Request $request): JsonResponse
    {
        $limiter = $this->clientLogsLimiter->create($request->getClientIp());
        if (!$limiter->consume(1)->isAccepted()) {
            return new JsonResponse(['error' => 'Trop de requêtes'], Response::HTTP_TOO_MANY_REQUESTS);
        }

        $data = json_decode($request->getContent(), true);
        if (!is_array($data) || !isset($data['message']) || !is_string($data['message']) || '' === trim($data['message'])) {
            return new JsonResponse(['error' => 'Le champ « message » est requis'], Response::HTTP_BAD_REQUEST);
        }

        $context = ['user_agent' => mb_substr((string) $request->headers->get('User-Agent', ''), 0, 300)];
        foreach (self::LIMITS as $field => $max) {
            if (isset($data[$field]) && is_string($data[$field])) {
                $context[$field] = mb_substr($data[$field], 0, $max);
            }
        }
        unset($context['message']);

        $this->logger->error('Erreur front : '.mb_substr($data['message'], 0, self::LIMITS['message']), $context);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}

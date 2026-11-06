<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\PushNotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Envoi de Web Push à un utilisateur, appelé par le bot (service à service).
 * Protégé par une clé de service (en-tête X-Service-Key), pas par un JWT.
 */
class PushSendController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PushNotificationService $pushService,
        #[Autowire(env: 'PUSH_SERVICE_KEY')] private readonly string $serviceKey,
    ) {}

    #[Route('/api/push/send', name: 'app_push_send', methods: ['POST'])]
    public function send(Request $request): JsonResponse
    {
        $provided = (string) $request->headers->get('X-Service-Key', '');
        if ($this->serviceKey === '' || !hash_equals($this->serviceKey, $provided)) {
            return new JsonResponse(['error' => 'Invalid service key'], 401);
        }

        $data = json_decode($request->getContent(), true);
        if (!is_array($data) || !isset($data['userId'], $data['title'], $data['body'])) {
            return new JsonResponse(['error' => 'userId, title and body are required'], 422);
        }

        $user = $this->entityManager->getRepository(User::class)->find($data['userId']);
        if ($user === null) {
            return new JsonResponse(['error' => 'User not found'], 404);
        }

        $this->pushService->sendToUser($user, (string) $data['title'], (string) $data['body'], (string) ($data['url'] ?? '/'));

        return new JsonResponse(['status' => 'ok']);
    }
}

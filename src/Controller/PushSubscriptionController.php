<?php

namespace App\Controller;

use App\Entity\PushSubscription;
use App\Exception\ApiProblemException;
use App\Repository\PushSubscriptionRepository;
use App\Service\AuthenticationService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/push')]
class PushSubscriptionController extends BaseController
{
    public function __construct(
        EntityManagerInterface $entityManager,
        AuthenticationService $authService,
        LoggerInterface $logger,
        #[Autowire(env: 'VAPID_PUBLIC_KEY')] private readonly string $vapidPublicKey,
    ) {
        parent::__construct($entityManager, $authService, $logger);
    }

    protected function formatEntityData($entity): array
    {
        return ['id' => $entity->getId()];
    }

    #[Route('/vapid-public-key', name: 'app_push_vapid_public_key', methods: ['GET'])]
    public function getVapidPublicKey(): JsonResponse
    {
        return $this->json(['publicKey' => $this->vapidPublicKey]);
    }

    #[Route('/subscribe', name: 'app_push_subscribe', methods: ['POST'])]
    public function subscribe(Request $request, PushSubscriptionRepository $subscriptionRepository): JsonResponse
    {
        $user = $this->getAuthenticatedUser();
        $data = $this->getRequestData($request);

        if (!isset($data['endpoint'], $data['keys']['p256dh'], $data['keys']['auth'])) {
            throw ApiProblemException::validationError('Missing required fields', [
                ['field' => 'endpoint', 'message' => 'endpoint, keys.p256dh and keys.auth are required'],
            ]);
        }

        $subscription = $subscriptionRepository->findByEndpoint($data['endpoint']);
        $statusCode = 200;

        if ($subscription === null) {
            $subscription = new PushSubscription();
            $subscription->setUser($user);
            $statusCode = 201;
        } elseif ($subscription->getUser()?->getId() !== $user->getId()) {
            throw ApiProblemException::forbidden('This subscription belongs to another user');
        }

        $subscription->setEndpoint($data['endpoint']);
        $subscription->setP256dhKey($data['keys']['p256dh']);
        $subscription->setAuthToken($data['keys']['auth']);

        $this->saveEntity($subscription);

        return $this->json(['status' => 'ok'], $statusCode);
    }

    #[Route('/subscribe', name: 'app_push_unsubscribe', methods: ['DELETE'])]
    public function unsubscribe(Request $request, PushSubscriptionRepository $subscriptionRepository): JsonResponse
    {
        $user = $this->getAuthenticatedUser();
        $data = $this->getRequestData($request);

        if (!isset($data['endpoint'])) {
            throw ApiProblemException::validationError('Missing endpoint', [
                ['field' => 'endpoint', 'message' => 'endpoint is required'],
            ]);
        }

        $subscription = $subscriptionRepository->findByEndpoint($data['endpoint']);

        if ($subscription === null || $subscription->getUser()?->getId() !== $user->getId()) {
            throw ApiProblemException::notFound('Subscription not found');
        }

        $this->deleteEntity($subscription);

        return new JsonResponse(null, 204);
    }
}

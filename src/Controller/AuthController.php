<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\AuthenticationService;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;

#[Route('/api/auth')]
class AuthController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
        private JWTTokenManagerInterface $jwtManager,
        private UserRepository $userRepository,
        private AuthenticationService $authService
    ) {}

    #[Route('/login', name: 'api_login', methods: ['POST'])]
    public function login(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['username']) || !isset($data['password'])) {
            return $this->json([
                'error' => 'Username and password are required'
            ], 400);
        }

        $user = $this->userRepository->findByUsernameOrApiKey($data['username']);

        if (!$user || !$user->isActive()) {
            throw new BadCredentialsException('Invalid credentials');
        }

        if (!$this->passwordHasher->isPasswordValid($user, $data['password'])) {
            throw new BadCredentialsException('Invalid credentials');
        }

        // Mettre à jour la dernière connexion
        $user->setLastLogin(new \DateTime());
        $this->entityManager->flush();

        $token = $this->jwtManager->create($user);

        return $this->json([
            'token' => $token,
            'user' => $this->authService->formatUserResponse($user),
            'expires_in' => 3600 // 1 heure
        ]);
    }

    #[Route('/refresh', name: 'api_refresh_token', methods: ['POST'])]
    public function refreshToken(Request $request): JsonResponse
    {
        $token = $this->authService->extractTokenFromRequest($request);

        if (!$token) {
            return $this->json(['error' => 'Missing or invalid Authorization header'], 401);
        }

        if (!$this->authService->canTokenBeRefreshed($token)) {
            return $this->json(['error' => 'Token is too old to refresh. Please login again.'], 401);
        }

        $user = $this->authService->getUserFromToken($token);

        if (!$user) {
            return $this->json(['error' => 'Invalid token or user not found'], 401);
        }

        $newToken = $this->authService->createTokenForUser($user);

        return $this->json([
            'token' => $newToken,
            'user' => $this->authService->formatUserResponse($user),
            'expires_in' => 3600
        ]);
    }

    #[Route('/verify', name: 'api_verify_token', methods: ['POST'])]
    public function verifyToken(Request $request): JsonResponse
    {
        $token = $this->authService->extractTokenFromRequest($request);

        if (!$token) {
            return $this->json(['error' => 'Missing or invalid Authorization header'], 401);
        }

        if (!$this->authService->isTokenValid($token)) {
            return $this->json([
                'valid' => false,
                'error' => 'Invalid or expired token'
            ], 401);
        }

        $user = $this->authService->getUserFromToken($token);

        if (!$user) {
            return $this->json([
                'valid' => false,
                'error' => 'User not found or inactive'
            ], 401);
        }

        try {
            $payload = $this->jwtManager->parse($token);
            return $this->json([
                'valid' => true,
                'user' => $this->authService->formatUserResponse($user),
                'expires_at' => date('Y-m-d H:i:s', $payload['exp'])
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'valid' => false,
                'error' => 'Token parsing error'
            ], 401);
        }
    }

    #[Route('/login-api-key', name: 'api_login_api_key', methods: ['POST'])]
    public function loginWithApiKey(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['api_key'])) {
            return $this->json([
                'error' => 'API key is required'
            ], 400);
        }

        $user = $this->authService->validateApiKey($data['api_key']);

        if (!$user) {
            return $this->json(['error' => 'Invalid API key or access not authorized'], 401);
        }

        // Mettre à jour la dernière connexion
        $user->setLastLogin(new \DateTime());
        $this->entityManager->flush();

        $token = $this->authService->createTokenForUser($user);

        return $this->json([
            'token' => $token,
            'user' => $this->authService->formatUserResponse($user),
            'expires_in' => 3600,
            'login_method' => 'api_key'
        ]);
    }

    #[Route('/me', name: 'api_user_profile', methods: ['GET'])]
    public function getProfile(): JsonResponse
    {
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->json(['error' => 'User not authenticated'], 401);
        }

        return $this->json([
            'id' => $user->getId(),
            'username' => $user->getUsername(),
            'roles' => $user->getRoles(),
            'last_login' => $user->getLastLogin()?->format('Y-m-d H:i:s'),
            'is_active' => $user->isActive()
        ]);
    }

    #[Route('/create-user', name: 'api_create_user', methods: ['POST'])]
    public function createUser(Request $request): JsonResponse
    {
        // Cette route est temporaire pour créer des utilisateurs - à sécuriser en production
        $data = json_decode($request->getContent(), true);

        if (!isset($data['username']) || !isset($data['password'])) {
            return $this->json([
                'error' => 'Username and password are required'
            ], 400);
        }

        // Vérifier si l'utilisateur existe déjà
        $existingUser = $this->userRepository->findOneBy(['username' => $data['username']]);
        if ($existingUser) {
            return $this->json(['error' => 'User already exists'], 409);
        }

        $user = new User();
        $user->setUsername($data['username']);
        $user->setPassword($this->passwordHasher->hashPassword($user, $data['password']));

        // Définir les rôles
        $roles = $data['roles'] ?? ['ROLE_API'];
        $user->setRoles($roles);

        // Générer une clé API optionnelle
        if (isset($data['generate_api_key']) && $data['generate_api_key']) {
            $apiKey = bin2hex(random_bytes(32));
            $user->setApiKey($apiKey);
        }

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $this->json([
            'message' => 'User created successfully',
            'user' => [
                'id' => $user->getId(),
                'username' => $user->getUsername(),
                'roles' => $user->getRoles(),
                'api_key' => $user->getApiKey()
            ]
        ], 201);
    }
}

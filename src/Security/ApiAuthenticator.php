<?php

namespace App\Security;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

/**
 * Authenticateur personnalisé pour les clés API
 */
class ApiAuthenticator extends AbstractAuthenticator
{
    public function __construct(
        private UserRepository $userRepository,
        private EntityManagerInterface $entityManager
    ) {}

    public function supports(Request $request): ?bool
    {
        // Supporte les requêtes avec une clé API dans les headers
        return $request->headers->has('X-API-KEY');
    }

    public function authenticate(Request $request): Passport
    {
        $apiKey = $request->headers->get('X-API-KEY');

        if (null === $apiKey) {
            throw new CustomUserMessageAuthenticationException('No API key provided');
        }

        return new SelfValidatingPassport(
            new UserBadge($apiKey, function ($apiKey) {
                $user = $this->userRepository->findOneBy(['apiKey' => $apiKey]);

                if (!$user) {
                    throw new CustomUserMessageAuthenticationException('Invalid API key');
                }

                if (!$user->isActive()) {
                    throw new CustomUserMessageAuthenticationException('User account is disabled');
                }

                if (!in_array('ROLE_API', $user->getRoles())) {
                    throw new CustomUserMessageAuthenticationException('API access not authorized');
                }

                if ($user->isApiKeyExpired()) {
                    throw new CustomUserMessageAuthenticationException('API key has expired');
                }

                // Mettre à jour la dernière connexion
                $user->setLastLogin(new \DateTime());
                $this->entityManager->flush();

                return $user;
            })
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        // Retourner null permet de continuer la requête normalement
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return new Response(
            json_encode(['error' => 'Authentication failed: ' . $exception->getMessage()]),
            Response::HTTP_UNAUTHORIZED,
            ['Content-Type' => 'application/json']
        );
    }
}

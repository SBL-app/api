<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Ajoute des en-têtes de sécurité HTTP à toutes les réponses.
 *
 * OWASP A05 (Security Misconfiguration) : durcit les réponses de l'API contre
 * le MIME-sniffing, le clickjacking et les fuites de référent.
 */
final class SecurityHeadersSubscriber implements EventSubscriberInterface
{
    /**
     * @return array<string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => 'onKernelResponse',
        ];
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        // N'agit que sur la requête principale (pas les sous-requêtes).
        if (!$event->isMainRequest()) {
            return;
        }

        $headers = $event->getResponse()->headers;
        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('X-Frame-Options', 'DENY');
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $headers->set('Permissions-Policy', 'geolocation=(), microphone=(), camera=()');
        // API JSON : aucune ressource active n'est servie, CSP très restrictive.
        $headers->set('Content-Security-Policy', "default-src 'none'; frame-ancestors 'none'");
    }
}

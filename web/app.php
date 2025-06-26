<?php

// Redirection vers la nouvelle structure Symfony avec le dossier public
$newPath = '../public/index.php';

if (file_exists($newPath)) {
    // Rediriger vers la nouvelle structure
    $requestUri = $_SERVER['REQUEST_URI'];
    $redirectPath = str_replace('/web', '', $requestUri);
    if ($redirectPath === '' || $redirectPath === '/') {
        $redirectPath = '/';
    }
    
    // Configuration des headers pour redirection permanente
    header('HTTP/1.1 301 Moved Permanently');
    header('Location: ' . $redirectPath);
    exit;
}

// Fallback vers l'ancienne méthode si le nouveau système n'est pas disponible
use Symfony\Component\HttpFoundation\Request;

// Supprimer temporairement les warnings sur E_STRICT qui est déprécié
error_reporting(error_reporting() & ~E_DEPRECATED);

require __DIR__.'/../vendor/autoload.php';
if (PHP_VERSION_ID < 70000) {
    include_once __DIR__.'/../var/bootstrap.php.cache';
}

$kernel = new AppKernel('prod', false);
if (PHP_VERSION_ID < 70000) {
    $kernel->loadClassCache();
}

$request = Request::createFromGlobals();
$response = $kernel->handle($request);
$response->send();
$kernel->terminate($request, $response);

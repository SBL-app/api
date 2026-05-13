<?php

use Symfony\Component\Dotenv\Dotenv;

$loader = require dirname(__DIR__).'/vendor/autoload.php';

// In git worktrees, vendor/ is symlinked from the parent repo and its autoload
// maps App\ to the parent src/. Override to use this worktree's src/.
$worktreeDir = dirname(__DIR__);
$vendorRealParent = dirname((string) realpath($worktreeDir . '/vendor'));
if ($vendorRealParent !== $worktreeDir) {
    $loader->setPsr4('App\\', [$worktreeDir . '/src', $worktreeDir . '/tests']);
    $loader->register(true);
}

if (method_exists(Dotenv::class, 'bootEnv')) {
    (new Dotenv())->bootEnv(dirname(__DIR__).'/.env');
}

if ($_SERVER['APP_DEBUG']) {
    umask(0000);
}

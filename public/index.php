<?php

use App\Kernel;

// Supprimer temporairement les warnings sur E_STRICT qui est déprécié
error_reporting(error_reporting() & ~E_DEPRECATED);

require_once dirname(__DIR__).'/vendor/autoload_runtime.php';

return function (array $context) {
    return new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);
};

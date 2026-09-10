<?php

declare(strict_types=1);

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

new Dotenv()->bootEnv(dirname(__DIR__).'/.env');

if ($_SERVER['APP_DEBUG']) {
    umask(0o000);
}

// Keep the test database schema in sync with the mapped entities.
passthru(sprintf(
    'php "%s/../bin/console" doctrine:schema:update --force --complete --env=test --quiet',
    __DIR__
));

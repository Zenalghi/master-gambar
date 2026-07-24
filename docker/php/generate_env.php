<?php

/**
 * Generate .env file from Docker environment variables.
 * Called by docker-entrypoint.sh on first run.
 * Reads from env vars set by docker-compose env_file (.env.docker on host).
 */

$keys = [
    'APP_NAME',
    'APP_ENV',
    'APP_DEBUG',
    'APP_URL',
    'APP_LOCALE',
    'APP_FALLBACK_LOCALE',
    'APP_FAKER_LOCALE',
    'APP_MAINTENANCE_DRIVER',
    'PHP_CLI_SERVER_WORKERS',
    'BCRYPT_ROUNDS',
    'LOG_CHANNEL',
    'LOG_STACK',
    'LOG_DEPRECATIONS_CHANNEL',
    'LOG_LEVEL',
    'DB_CONNECTION',
    'DB_HOST',
    'DB_PORT',
    'DB_DATABASE',
    'DB_USERNAME',
    'DB_PASSWORD',
    'SESSION_DRIVER',
    'SESSION_LIFETIME',
    'SESSION_ENCRYPT',
    'SESSION_PATH',
    'SESSION_DOMAIN',
    'BROADCAST_CONNECTION',
    'FILESYSTEM_DISK',
    'QUEUE_CONNECTION',
    'CACHE_STORE',
    'REDIS_CLIENT',
    'REDIS_HOST',
    'REDIS_PASSWORD',
    'REDIS_PORT',
    'MAIL_MAILER',
    'MAIL_SCHEME',
    'MAIL_HOST',
    'MAIL_PORT',
    'MAIL_USERNAME',
    'MAIL_PASSWORD',
    'MAIL_FROM_ADDRESS',
    'MAIL_FROM_NAME',
    'AWS_ACCESS_KEY_ID',
    'AWS_SECRET_ACCESS_KEY',
    'AWS_DEFAULT_REGION',
    'AWS_BUCKET',
    'AWS_USE_PATH_STYLE_ENDPOINT',
    'VITE_APP_NAME',
    'MYSQL_ROOT_PASSWORD',
    'MYSQL_DATABASE',
    'MYSQL_USER',
    'MYSQL_PASSWORD',
];

$lines = [];
foreach ($keys as $k) {
    $v = getenv($k);
    if ($v === false) continue;
    if (preg_match('/[\s"\']/', $v)) {
        $v = '"' . str_replace('\"', '\\"', $v) . '"';
    }
    $lines[] = $k . '=' . $v;
}

// Always include APP_KEY (even if empty, for key:generate to work)
$hasAppKey = false;
foreach ($lines as $l) {
    if (strpos($l, 'APP_KEY=') === 0) {
        $hasAppKey = true;
        break;
    }
}
if (!$hasAppKey) {
    $lines[] = 'APP_KEY=';
}

file_put_contents('/var/www/html/.env', implode("\n", $lines) . "\n");
echo "Generated .env from " . count($lines) . " environment variables\n";

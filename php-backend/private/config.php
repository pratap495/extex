<?php
declare(strict_types=1);

function database_config(): array
{
    $localPath = __DIR__ . '/config.local.php';
    $local = is_file($localPath) ? require $localPath : [];
    $read = static function (string $name, ?string $default = null) use ($local): string {
        $value = array_key_exists($name, $local) ? $local[$name] : getenv($name);
        if ($value === false || $value === null || $value === '') {
            if (array_key_exists($name, $local) && $value === '') return '';
            if ($default !== null) return $default;
            throw new RuntimeException('Missing server configuration: ' . $name);
        }
        return (string)$value;
    };
    return [
        'host' => $read('DB_HOST', 'auth-db1555.hstgr.io'),
        'name' => $read('DB_NAME', 'u667941815_project'),
        'user' => $read('DB_USER', 'u667941815_project'),
        'password' => $read('DB_PASS'),
    ];
}

function app_base_path(): string
{
    $script = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
    $base = str_replace('\\', '/', dirname($script));
    $base = preg_replace('~/php-backend$~', '', $base) ?? $base;
    return $base === '/' || $base === '.' ? '' : rtrim($base, '/');
}

function app_url(string $path = '/'): string
{
    return app_base_path() . '/' . ltrim($path, '/');
}

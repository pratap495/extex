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

function smtp_config(): array
{
    $localPath = __DIR__ . '/config.local.php';
    $local = is_file($localPath) ? require $localPath : [];
    $value = static function (string $key, string $default = '') use ($local): string {
        $envValue = getenv($key);
        if ($envValue !== false && $envValue !== '') return (string)$envValue;
        $localValue = $local[$key] ?? null;
        return (string)($localValue !== null && $localValue !== '' ? $localValue : $default);
    };
    return [
        'host' => $value('SMTP_HOST', 'smtp.hostinger.com'),
        'port' => max(1, min(65535, (int)$value('SMTP_PORT', '587'))),
        'encryption' => strtolower($value('SMTP_ENCRYPTION', 'tls')),
        'username' => $value('SMTP_USER'),
        'password' => $value('SMTP_PASS'),
        'from' => $value('SMTP_FROM'),
    ];
}

function app_base_path(): string
{
    $script = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
    if (str_starts_with($script, '/') && !preg_match('~^[A-Za-z]:/~', $script)) {
        $base = dirname($script);
        $base = preg_replace('~/php-backend$~', '', $base) ?? $base;
        return $base === '/' || $base === '.' ? '' : rtrim($base, '/');
    }

    // Some local Apache configurations expose SCRIPT_NAME as a Windows path.
    // Derive its public URL path from the project directory under DOCUMENT_ROOT.
    $documentRoot = realpath((string)($_SERVER['DOCUMENT_ROOT'] ?? ''));
    $projectRoot = realpath(dirname(__DIR__, 2));
    if ($documentRoot !== false && $projectRoot !== false) {
        $documentRoot = rtrim(str_replace('\\', '/', $documentRoot), '/');
        $projectRoot = str_replace('\\', '/', $projectRoot);
        if (strncasecmp($projectRoot, $documentRoot . '/', strlen($documentRoot) + 1) === 0) {
            $relative = substr($projectRoot, strlen($documentRoot) + 1);
            return '/' . trim(str_replace('\\', '/', $relative), '/');
        }
        if (strcasecmp($projectRoot, $documentRoot) === 0) return '';
    }
    return '';
}

function app_url(string $path = '/'): string
{
    return app_base_path() . '/' . ltrim($path, '/');
}

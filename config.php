<?php
declare(strict_types=1);

const APP_NAME = 'MeeleKompass';
const DATA_DIR = __DIR__ . '/data';
const CHECKINS_FILE = DATA_DIR . '/checkins.json';
const TICKETS_FILE = DATA_DIR . '/tickets.json';
const ADMIN_PASSWORD_HASH = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.';

function app_start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    session_name('meelekompass_session');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();

    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
}

function app_csrf_token(): string
{
    app_start_session();
    return (string) $_SESSION['csrf_token'];
}

function app_html(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function app_read_json(string $file): array
{
    $handle = fopen($file, 'c+');
    if ($handle === false) {
        throw new RuntimeException('Andmefaili avamine ebaõnnestus.');
    }

    try {
        if (!flock($handle, LOCK_SH)) {
            throw new RuntimeException('Andmefaili lukustamine ebaõnnestus.');
        }
        rewind($handle);
        $contents = stream_get_contents($handle);
        $data = $contents === false || trim($contents) === '' ? [] : json_decode($contents, true);
        if (!is_array($data)) {
            throw new RuntimeException('Andmefaili sisu ei ole korrektne JSON.');
        }
        flock($handle, LOCK_UN);
        return $data;
    } finally {
        fclose($handle);
    }
}

function app_update_json(string $file, callable $callback): mixed
{
    $handle = fopen($file, 'c+');
    if ($handle === false) {
        throw new RuntimeException('Andmefaili avamine ebaõnnestus.');
    }

    try {
        if (!flock($handle, LOCK_EX)) {
            throw new RuntimeException('Andmefaili lukustamine ebaõnnestus.');
        }
        rewind($handle);
        $contents = stream_get_contents($handle);
        $data = $contents === false || trim($contents) === '' ? [] : json_decode($contents, true);
        if (!is_array($data)) {
            throw new RuntimeException('Andmefaili sisu ei ole korrektne JSON.');
        }

        $result = $callback($data);
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new RuntimeException('Andmete kodeerimine ebaõnnestus.');
        }

        rewind($handle);
        if (!ftruncate($handle, 0) || fwrite($handle, $json . PHP_EOL) === false || !fflush($handle)) {
            throw new RuntimeException('Andmete salvestamine ebaõnnestus.');
        }
        flock($handle, LOCK_UN);
        return $result;
    } finally {
        fclose($handle);
    }
}

function app_rate_limit(string $key, int $maximum, int $seconds): void
{
    $now = time();
    $events = $_SESSION['rate_limits'][$key] ?? [];
    $events = array_values(array_filter(
        $events,
        static fn ($timestamp): bool => is_int($timestamp) && $timestamp > $now - $seconds
    ));

    if (count($events) >= $maximum) {
        throw new DomainException('Oled seda tegevust lühikese aja jooksul liiga palju teinud. Proovi hiljem uuesti.');
    }

    $events[] = $now;
    $_SESSION['rate_limits'][$key] = $events;
}

function app_is_admin(): bool
{
    app_start_session();
    return ($_SESSION['is_admin'] ?? false) === true;
}

function app_data_directory_ready(): void
{
    if (!is_dir(DATA_DIR) || !is_writable(DATA_DIR)) {
        throw new RuntimeException('Andmekaust puudub või PHP-l puudub sinna kirjutamise õigus.');
    }
}

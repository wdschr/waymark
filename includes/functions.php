<?php
declare(strict_types=1);

function h(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function wf_base_url(array $config): string
{
    return rtrim($config['base_url'] ?? '', '/');
}

function wf_redirect(string $path): void
{
    header('Location: ' . $path);
    exit;
}

function wf_flash_set(string $message, string $type = 'info'): void
{
    $_SESSION['flash'] = ['message' => $message, 'type' => $type];
}

function wf_flash_take(): ?array
{
    if (empty($_SESSION['flash'])) {
        return null;
    }
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $flash;
}

function wf_post_string(string $key, string $default = ''): string
{
    $value = $_POST[$key] ?? $default;
    return is_string($value) ? trim($value) : $default;
}

function wf_int_or_null($value): ?int
{
    if ($value === null || $value === '' || $value === '0') {
        return null;
    }
    $int = filter_var($value, FILTER_VALIDATE_INT);
    return $int === false ? null : $int;
}

/**
 * Splits a freeform comma-separated tag string into a clean, deduped list.
 */
function wf_parse_tags(string $raw): array
{
    $parts = array_map('trim', explode(',', $raw));
    $parts = array_filter($parts, static fn($t) => $t !== '');
    $parts = array_map(static fn($t) => mb_substr($t, 0, 100), $parts);
    return array_values(array_unique($parts));
}

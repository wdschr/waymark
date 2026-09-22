<?php
declare(strict_types=1);

function wf_csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function wf_csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . h(wf_csrf_token()) . '">';
}

function wf_csrf_verify(): void
{
    $token = $_POST['csrf'] ?? '';
    $expected = $_SESSION['csrf_token'] ?? '';
    if (!is_string($token) || $expected === '' || !hash_equals($expected, $token)) {
        http_response_code(400);
        die('Invalid or expired form submission. Please go back and try again.');
    }
}

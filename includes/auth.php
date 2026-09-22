<?php
declare(strict_types=1);

function wf_current_user(PDO $pdo): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    static $cached = null;
    if ($cached !== null && $cached['id'] === $_SESSION['user_id']) {
        return $cached;
    }
    $stmt = $pdo->prepare('SELECT id, email, bookmarklet_token, created_at FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    if (!$user) {
        unset($_SESSION['user_id']);
        return null;
    }
    $cached = $user;
    return $user;
}

function wf_require_login(PDO $pdo): array
{
    $user = wf_current_user($pdo);
    if (!$user) {
        wf_redirect('login.php');
    }
    return $user;
}

function wf_register(PDO $pdo, string $email, string $password): array
{
    $email = mb_strtolower(trim($email));

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return [false, 'Enter a valid email address.'];
    }
    if (strlen($password) < 8) {
        return [false, 'Password must be at least 8 characters.'];
    }

    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        return [false, 'An account with that email already exists.'];
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $token = bin2hex(random_bytes(24));

    $stmt = $pdo->prepare(
        'INSERT INTO users (email, password_hash, bookmarklet_token) VALUES (?, ?, ?)'
    );
    $stmt->execute([$email, $hash, $token]);

    return [true, (int) $pdo->lastInsertId()];
}

function wf_login(PDO $pdo, string $email, string $password): bool
{
    $email = mb_strtolower(trim($email));
    $stmt = $pdo->prepare('SELECT id, password_hash FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        return false;
    }

    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['id'];
    return true;
}

function wf_change_password(PDO $pdo, int $userId, string $currentPassword, string $newPassword): array
{
    $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($currentPassword, $user['password_hash'])) {
        return [false, 'Current password is incorrect.'];
    }
    if (strlen($newPassword) < 8) {
        return [false, 'New password must be at least 8 characters.'];
    }

    $hash = password_hash($newPassword, PASSWORD_DEFAULT);
    $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([$hash, $userId]);

    return [true, null];
}

function wf_logout(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}

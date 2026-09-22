<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

$user = wf_require_login($pdo);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    wf_redirect('bookmarklet.php');
}
wf_csrf_verify();

$newToken = bin2hex(random_bytes(24));
$pdo->prepare('UPDATE users SET bookmarklet_token = ? WHERE id = ?')->execute([$newToken, $user['id']]);

wf_flash_set('Bookmarklet token regenerated. Update your bookmarklet with the new one below.', 'success');
wf_redirect('bookmarklet.php');

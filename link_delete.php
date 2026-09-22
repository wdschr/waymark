<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

$user = wf_require_login($pdo);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    wf_redirect('index.php');
}
wf_csrf_verify();

$id = wf_int_or_null($_POST['id'] ?? null);
if ($id) {
    wf_delete_link($pdo, $user['id'], $id);
    wf_flash_set('Link deleted.', 'success');
}
wf_redirect('index.php');

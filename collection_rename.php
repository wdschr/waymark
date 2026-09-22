<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

$user = wf_require_login($pdo);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    wf_redirect('collections.php');
}
wf_csrf_verify();

$id = wf_int_or_null($_POST['id'] ?? null);
if (!$id) {
    wf_redirect('collections.php');
}

[$ok, $error] = wf_rename_collection($pdo, $user['id'], $id, wf_post_string('name'));
wf_flash_set($ok ? 'Collection renamed.' : $error, $ok ? 'success' : 'error');
wf_redirect('collections.php');

<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

$user = wf_require_login($pdo);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    wf_redirect('collections.php');
}
wf_csrf_verify();

$id = wf_int_or_null($_POST['id'] ?? null);
$enabled = ($_POST['enabled'] ?? '0') === '1';

if ($id && wf_get_collection($pdo, $user['id'], $id)) {
    wf_set_collection_share($pdo, $user['id'], $id, $enabled);
    wf_flash_set($enabled ? 'Sharing enabled.' : 'Sharing disabled.', 'success');
}
wf_redirect('collections.php');

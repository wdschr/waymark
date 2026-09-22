<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

$user = wf_require_login($pdo);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    wf_redirect('collections.php');
}
wf_csrf_verify();

$id = wf_int_or_null($_POST['id'] ?? null);
if ($id) {
    wf_delete_collection($pdo, $user['id'], $id);
    wf_flash_set('Collection deleted.', 'success');
}
wf_redirect('collections.php');

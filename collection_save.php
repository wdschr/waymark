<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

$user = wf_require_login($pdo);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    wf_redirect('collections.php');
}
wf_csrf_verify();

[$ok, $result] = wf_create_collection($pdo, $user['id'], wf_post_string('name'));
wf_flash_set($ok ? 'Collection created.' : $result, $ok ? 'success' : 'error');
wf_redirect('collections.php');

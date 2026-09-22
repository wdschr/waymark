<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

$user = wf_require_login($pdo);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    wf_redirect('index.php');
}
wf_csrf_verify();

$id = wf_int_or_null($_POST['id'] ?? null);
$link = $id ? wf_get_link($pdo, $user['id'], $id) : null;

if (!$link) {
    wf_flash_set('Link not found.', 'error');
    wf_redirect('index.php');
}

$meta = wf_fetch_metadata($link['url']);

$stmt = $pdo->prepare(
    'UPDATE links SET title = ?, description = ?, image_url = ?, is_broken = 0, last_checked_at = NOW()
     WHERE id = ? AND user_id = ?'
);
$stmt->execute([
    $meta['title'] !== '' ? mb_substr($meta['title'], 0, 512) : $link['title'],
    $meta['description'] !== '' ? mb_substr($meta['description'], 0, 2000) : $link['description'],
    $meta['image_url'],
    $id,
    $user['id'],
]);

if ($meta['title'] === '' && $meta['description'] === '' && $meta['image_url'] === '') {
    wf_flash_set('Could not fetch metadata for this URL.', 'error');
} else {
    wf_flash_set('Metadata refreshed.', 'success');
}
wf_redirect('index.php');

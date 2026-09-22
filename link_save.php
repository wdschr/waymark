<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

$user = wf_require_login($pdo);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    wf_redirect('index.php');
}
wf_csrf_verify();

$id = wf_int_or_null($_POST['id'] ?? null);
$url = wf_post_string('url');
$titleInput = wf_post_string('title');
$descInput = wf_post_string('description');
$tags = wf_parse_tags(wf_post_string('tags'));
$collectionId = wf_int_or_null($_POST['collection_id'] ?? null);

if ($collectionId !== null && !wf_get_collection($pdo, $user['id'], $collectionId)) {
    $collectionId = null;
}

if (!preg_match('#^https?://#i', $url)) {
    wf_flash_set('Please enter a valid http:// or https:// URL.', 'error');
    wf_redirect($id ? 'link_edit.php?id=' . $id : 'index.php');
}

if ($id) {
    $existing = wf_get_link($pdo, $user['id'], $id);
    if (!$existing) {
        wf_flash_set('Link not found.', 'error');
        wf_redirect('index.php');
    }

    wf_update_link($pdo, $user['id'], $id, [
        'collection_id' => $collectionId,
        'url'           => mb_substr($url, 0, 2048),
        'title'         => mb_substr($titleInput !== '' ? $titleInput : $existing['title'], 0, 512),
        'description'   => mb_substr($descInput, 0, 2000),
        'image_url'     => $existing['image_url'],
        'tags'          => $tags,
    ]);
    wf_flash_set('Link updated.', 'success');
    wf_redirect('index.php');
}

// Create flow: fetch metadata best-effort, within the request's time budget.
$meta = wf_fetch_metadata($url);

$title = $titleInput !== '' ? $titleInput : ($meta['title'] !== '' ? $meta['title'] : $url);
$description = $descInput !== '' ? $descInput : $meta['description'];

wf_create_link($pdo, $user['id'], [
    'collection_id' => $collectionId,
    'url'           => mb_substr($url, 0, 2048),
    'title'         => mb_substr($title, 0, 512),
    'description'   => mb_substr($description, 0, 2000),
    'image_url'     => $meta['image_url'],
    'tags'          => $tags,
]);

if ($meta['title'] === '' && $meta['description'] === '') {
    wf_flash_set('Link saved. We could not fetch its page metadata automatically - edit it to add a title or description.', 'info');
} else {
    wf_flash_set('Link saved.', 'success');
}
wf_redirect('index.php');

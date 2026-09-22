<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

// This endpoint is deliberately NOT protected by the session CSRF token:
// the bookmarklet submits a normal cross-site form POST (that's what lets
// it work from any page without a browser extension or CORS setup), so a
// session-bound CSRF token would never survive the trip. Instead it's
// authenticated by the user's personal bookmarklet_token, which acts as
// the anti-forgery credential here - see bookmarklet.php.

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die('Method not allowed.');
}

$pageTitle = 'Quick add - Waymark';
$user = null;

$token = wf_post_string('token');
$url = wf_post_string('url');
$titleInput = wf_post_string('title');

$stmt = $pdo->prepare('SELECT id FROM users WHERE bookmarklet_token = ?');
$stmt->execute([$token]);
$row = $stmt->fetch();

if (!$row) {
    http_response_code(403);
    require __DIR__ . '/partials/header.php';
    echo '<div class="panel" style="max-width:400px;margin:40px auto;text-align:center;"><p>Invalid or revoked bookmarklet link.</p></div>';
    require __DIR__ . '/partials/footer.php';
    exit;
}

if (!preg_match('#^https?://#i', $url)) {
    require __DIR__ . '/partials/header.php';
    echo '<div class="panel" style="max-width:400px;margin:40px auto;text-align:center;"><p>That does not look like a valid URL.</p></div>';
    require __DIR__ . '/partials/footer.php';
    exit;
}

$userId = (int) $row['id'];
$meta = wf_fetch_metadata($url);
$title = $titleInput !== '' ? $titleInput : ($meta['title'] !== '' ? $meta['title'] : $url);

wf_create_link($pdo, $userId, [
    'collection_id' => null,
    'url'           => mb_substr($url, 0, 2048),
    'title'         => mb_substr($title, 0, 512),
    'description'   => mb_substr($meta['description'], 0, 2000),
    'image_url'     => $meta['image_url'],
    'tags'          => [],
]);

require __DIR__ . '/partials/header.php';
?>
<div class="panel" style="max-width:400px;margin:40px auto;text-align:center;">
    <h1>Saved!</h1>
    <p><strong><?= h($title) ?></strong></p>
    <p style="color:var(--muted);">You can close this tab.</p>
</div>
<?php require __DIR__ . '/partials/footer.php'; ?>

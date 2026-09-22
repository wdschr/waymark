<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

$token = trim((string) ($_GET['token'] ?? ''));
$collection = $token !== '' ? wf_get_collection_by_share_token($pdo, $token) : null;

if (!$collection) {
    http_response_code(404);
    $pageTitle = 'Not found - Waymark';
    $user = null;
    require __DIR__ . '/partials/header.php';
    echo '<div class="empty-state">This shared collection does not exist or is no longer shared.</div>';
    require __DIR__ . '/partials/footer.php';
    exit;
}

$stmt = $pdo->prepare('SELECT * FROM links WHERE collection_id = ? ORDER BY created_at DESC LIMIT 500');
$stmt->execute([$collection['id']]);
$links = $stmt->fetchAll();

$pageTitle = h($collection['name']) . ' - Shared collection';
$user = null;
require __DIR__ . '/partials/header.php';
?>
<div class="panel">
    <h1><?= h($collection['name']) ?></h1>
    <p style="color:var(--muted);">A shared, read-only collection.</p>
</div>

<div class="link-list">
<?php if (!$links): ?>
    <p class="empty-state">No links in this collection yet.</p>
<?php endif; ?>
<?php foreach ($links as $link): ?>
    <div class="link-card">
        <?php if (!empty($link['image_url'])): ?>
            <img class="thumb" src="<?= h($link['image_url']) ?>" alt="" loading="lazy" onerror="this.remove()">
        <?php endif; ?>
        <div class="body">
            <h3><a href="<?= h($link['url']) ?>" target="_blank" rel="noopener noreferrer"><?= h($link['title'] ?: $link['url']) ?></a></h3>
            <div class="url"><?= h($link['url']) ?></div>
            <?php if (!empty($link['description'])): ?>
                <p class="desc"><?= h($link['description']) ?></p>
            <?php endif; ?>
            <?php if (!empty($link['tags_cache'])): ?>
                <div class="tags">
                    <?php foreach (explode(',', $link['tags_cache']) as $t): ?>
                        <span class="tag">#<?= h($t) ?></span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php endforeach; ?>
</div>
<?php require __DIR__ . '/partials/footer.php'; ?>

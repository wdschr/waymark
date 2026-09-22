<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

$user = wf_require_login($pdo);

$q = trim((string) ($_GET['q'] ?? ''));
$tag = trim((string) ($_GET['tag'] ?? ''));
$collectionId = wf_int_or_null($_GET['collection_id'] ?? null);

$links = wf_search_links($pdo, $user['id'], [
    'q'             => $q,
    'tag'           => $tag,
    'collection_id' => $collectionId,
]);

$collections = wf_get_collections($pdo, $user['id']);
$tags = wf_tags_for_user($pdo, $user['id']);

$pageTitle = 'Your links - Waymark';
require __DIR__ . '/partials/header.php';
?>
<div class="panel">
    <h1>Save a link</h1>
    <form method="post" action="link_save.php">
        <?= wf_csrf_field() ?>
        <label for="url">URL</label>
        <input type="url" id="url" name="url" placeholder="https://example.com/article" required>

        <div class="form-row">
            <div>
                <label for="tags">Tags (comma-separated, optional)</label>
                <input type="text" id="tags" name="tags" placeholder="reading, php">
            </div>
            <div>
                <label for="collection_id">Collection (optional)</label>
                <select id="collection_id" name="collection_id">
                    <option value="">None</option>
                    <?php foreach ($collections as $c): ?>
                        <option value="<?= (int) $c['id'] ?>"><?= h($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <button type="submit">Save link</button>
    </form>
</div>

<form method="get" action="index.php" class="search-bar">
    <input type="text" name="q" placeholder="Search your links..." value="<?= h($q) ?>">
    <button type="submit">Search</button>
    <?php if ($q !== '' || $tag !== '' || $collectionId): ?>
        <a href="index.php" class="btn secondary">Clear</a>
    <?php endif; ?>
</form>

<?php if ($collections || $tags): ?>
<div class="filters">
    <?php foreach ($collections as $c): ?>
        <a href="index.php?collection_id=<?= (int) $c['id'] ?>" class="<?= $collectionId === (int) $c['id'] ? 'active' : '' ?>">
            <?= h($c['name']) ?>
        </a>
    <?php endforeach; ?>
    <?php foreach ($tags as $t): ?>
        <a href="index.php?tag=<?= urlencode($t['name']) ?>" class="<?= $tag === $t['name'] ? 'active' : '' ?>">
            #<?= h($t['name']) ?>
        </a>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="link-list">
<?php if (!$links): ?>
    <p class="empty-state">No links yet. Save your first one above.</p>
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
            <div class="meta">
                <span><?= h(date('j M Y', strtotime($link['created_at']))) ?></span>
                <?php if ($link['is_broken']): ?><span class="badge-broken">Broken link</span><?php endif; ?>
            </div>
            <?php if (!empty($link['tags_cache'])): ?>
                <div class="tags">
                    <?php foreach (explode(',', $link['tags_cache']) as $t): ?>
                        <span class="tag">#<?= h($t) ?></span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <div class="actions">
            <a class="btn secondary" href="link_edit.php?id=<?= (int) $link['id'] ?>">Edit</a>
            <form method="post" action="link_delete.php" data-confirm="Delete this link?">
                <?= wf_csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int) $link['id'] ?>">
                <button type="submit" class="danger">Delete</button>
            </form>
        </div>
    </div>
<?php endforeach; ?>
</div>
<?php require __DIR__ . '/partials/footer.php'; ?>

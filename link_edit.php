<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

$user = wf_require_login($pdo);

$id = wf_int_or_null($_GET['id'] ?? null);
$link = $id ? wf_get_link($pdo, $user['id'], $id) : null;

if (!$link) {
    wf_flash_set('Link not found.', 'error');
    wf_redirect('index.php');
}

$collections = wf_get_collections($pdo, $user['id']);
$pageTitle = 'Edit link - Waymark';
require __DIR__ . '/partials/header.php';
?>
<div class="panel">
    <h1>Edit link</h1>
    <form method="post" action="link_save.php">
        <?= wf_csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int) $link['id'] ?>">

        <label for="url">URL</label>
        <input type="url" id="url" name="url" required value="<?= h($link['url']) ?>">

        <label for="title">Title</label>
        <input type="text" id="title" name="title" value="<?= h($link['title']) ?>">

        <label for="description">Description</label>
        <textarea id="description" name="description"><?= h($link['description'] ?? '') ?></textarea>

        <label for="tags">Tags (comma-separated)</label>
        <input type="text" id="tags" name="tags" value="<?= h($link['tags_cache'] ?? '') ?>">

        <label for="collection_id">Collection</label>
        <select id="collection_id" name="collection_id">
            <option value="">None</option>
            <?php foreach ($collections as $c): ?>
                <option value="<?= (int) $c['id'] ?>" <?= (int) $c['id'] === (int) $link['collection_id'] ? 'selected' : '' ?>>
                    <?= h($c['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <button type="submit">Save changes</button>
        <a href="index.php" class="btn secondary">Cancel</a>
    </form>
</div>

<div class="panel">
    <h2>Metadata</h2>
    <p style="color:var(--muted);font-size:0.9rem;">Re-fetch the title, description and preview image from the live page.</p>
    <form method="post" action="link_refresh.php">
        <?= wf_csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int) $link['id'] ?>">
        <button type="submit" class="secondary">Refresh metadata from URL</button>
    </form>
</div>
<?php require __DIR__ . '/partials/footer.php'; ?>

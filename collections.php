<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

$user = wf_require_login($pdo);

$collections = wf_get_collections($pdo, $user['id']);

$pageTitle = 'Collections - Waymark';
require __DIR__ . '/partials/header.php';
?>
<div class="panel">
    <h1>New collection</h1>
    <form method="post" action="collection_save.php">
        <?= wf_csrf_field() ?>
        <label for="name">Name</label>
        <input type="text" id="name" name="name" required maxlength="255">
        <button type="submit">Create</button>
    </form>
</div>

<div class="panel">
    <h2>Your collections</h2>
    <?php if (!$collections): ?>
        <p class="empty-state">No collections yet.</p>
    <?php else: ?>
    <ul class="collection-list">
        <?php foreach ($collections as $c): ?>
            <li>
                <div>
                    <a href="index.php?collection_id=<?= (int) $c['id'] ?>"><?= h($c['name']) ?></a>
                    <?php if (!empty($c['share_token'])): ?>
                        <div class="share-box" style="margin-top:6px;">
                            <span id="share-url-<?= (int) $c['id'] ?>"><?= h(wf_base_url($config) . '/share.php?token=' . $c['share_token']) ?></span>
                            <button type="button" class="secondary" data-copy="#share-url-<?= (int) $c['id'] ?>" style="margin-left:8px;">Copy</button>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="actions">
                    <form method="post" action="collection_rename.php" style="display:flex;gap:6px;">
                        <?= wf_csrf_field() ?>
                        <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                        <input type="text" name="name" value="<?= h($c['name']) ?>" style="margin-bottom:0;">
                        <button type="submit" class="secondary">Rename</button>
                    </form>
                    <form method="post" action="collection_share.php">
                        <?= wf_csrf_field() ?>
                        <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                        <input type="hidden" name="enabled" value="<?= empty($c['share_token']) ? '1' : '0' ?>">
                        <button type="submit" class="secondary"><?= empty($c['share_token']) ? 'Enable sharing' : 'Disable sharing' ?></button>
                    </form>
                    <form method="post" action="collection_delete.php" data-confirm="Delete this collection? Links inside it will become uncategorized.">
                        <?= wf_csrf_field() ?>
                        <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                        <button type="submit" class="danger">Delete</button>
                    </form>
                </div>
            </li>
        <?php endforeach; ?>
    </ul>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/partials/footer.php'; ?>

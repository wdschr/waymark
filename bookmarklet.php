<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

$user = wf_require_login($pdo);

$base = wf_base_url($config);
$bookmarkletJs = "javascript:(function(){"
    . "var f=document.createElement('form');"
    . "f.method='POST';"
    . "f.action='" . $base . "/quickadd.php';"
    . "f.target='_blank';"
    . "function a(n,v){var i=document.createElement('input');i.type='hidden';i.name=n;i.value=v;f.appendChild(i);}"
    . "a('url',location.href);"
    . "a('title',document.title);"
    . "a('token','" . $user['bookmarklet_token'] . "');"
    . "document.body.appendChild(f);"
    . "f.submit();"
    . "})();";

$pageTitle = 'Bookmarklet - Waymark';
require __DIR__ . '/partials/header.php';
?>
<div class="panel">
    <h1>Bookmarklet</h1>
    <p>Drag this button to your browser's bookmarks bar. Click it on any page to save that page to Waymark, no extension required.</p>
    <p><a class="bookmarklet-link" href="<?= h($bookmarkletJs) ?>" onclick="return false;">+ Save to Waymark</a></p>

    <p style="color:var(--muted);font-size:0.85rem;margin-top:20px;">
        If your browser won't let you drag it, right-click your bookmarks bar, choose "Add page" (or similar),
        and paste this into the URL field:
    </p>
    <pre id="bookmarklet-code"><?= h($bookmarkletJs) ?></pre>
    <button type="button" class="secondary" data-copy="#bookmarklet-code">Copy code</button>

    <p style="color:var(--danger);font-size:0.85rem;margin-top:20px;">
        This link contains a personal token. Anyone who has it can add links to your account -
        keep it private. If it leaks, regenerate it below.
    </p>
    <form method="post" action="bookmarklet_regenerate.php" data-confirm="Regenerate your bookmarklet token? Your existing bookmarklet will stop working.">
        <?= wf_csrf_field() ?>
        <button type="submit" class="danger">Regenerate token</button>
    </form>
</div>
<?php require __DIR__ . '/partials/footer.php'; ?>

<?php
/** @var array|null $user */
/** @var string $pageTitle */
$flash = wf_flash_take();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($pageTitle ?? 'Waymark') ?></title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body>
<header class="topbar">
    <a class="brand" href="index.php">Waymark</a>
    <?php if (!empty($user)): ?>
    <nav>
        <a href="index.php">Links</a>
        <a href="collections.php">Collections</a>
        <a href="import.php">Import</a>
        <a href="export.php">Export</a>
        <a href="bookmarklet.php">Bookmarklet</a>
    </nav>
    <form method="post" action="logout.php" class="logout-form">
        <?= wf_csrf_field() ?>
        <a href="account.php" class="user-email"><?= h($user['email']) ?></a>
        <button type="submit">Log out</button>
    </form>
    <?php endif; ?>
</header>
<main>
<?php if ($flash): ?>
    <div class="flash flash-<?= h($flash['type']) ?>"><?= h($flash['message']) ?></div>
<?php endif; ?>

<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

$user = wf_require_login($pdo);
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    wf_csrf_verify();

    $current = (string) ($_POST['current_password'] ?? '');
    $new = (string) ($_POST['new_password'] ?? '');
    $confirm = (string) ($_POST['new_password_confirm'] ?? '');

    if ($new !== $confirm) {
        $error = 'New passwords do not match.';
    } else {
        [$ok, $result] = wf_change_password($pdo, $user['id'], $current, $new);
        if ($ok) {
            wf_flash_set('Password changed.', 'success');
            wf_redirect('account.php');
        }
        $error = $result;
    }
}

$pageTitle = 'Account - Waymark';
require __DIR__ . '/partials/header.php';
?>
<div class="panel" style="max-width:420px;margin:24px auto;">
    <h1>Your account</h1>
    <p style="color:var(--muted);"><?= h($user['email']) ?></p>
</div>

<div class="panel" style="max-width:420px;margin:24px auto;">
    <h2>Change password</h2>
    <?php if ($error): ?><div class="flash flash-error"><?= h($error) ?></div><?php endif; ?>
    <form method="post" action="account.php">
        <?= wf_csrf_field() ?>
        <label for="current_password">Current password</label>
        <input type="password" id="current_password" name="current_password" required>

        <label for="new_password">New password</label>
        <input type="password" id="new_password" name="new_password" required minlength="8">

        <label for="new_password_confirm">Confirm new password</label>
        <input type="password" id="new_password_confirm" name="new_password_confirm" required minlength="8">

        <button type="submit">Change password</button>
    </form>
</div>
<?php require __DIR__ . '/partials/footer.php'; ?>

<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

if (wf_current_user($pdo)) {
    wf_redirect('index.php');
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    wf_csrf_verify();

    $email = wf_post_string('email');
    $password = $_POST['password'] ?? '';

    if (wf_login($pdo, $email, $password)) {
        wf_redirect('index.php');
    }
    $error = 'Incorrect email or password.';
}

$pageTitle = 'Log in - Waymark';
$user = null;
require __DIR__ . '/partials/header.php';
?>
<div class="panel" style="max-width:400px;margin:40px auto;">
    <h1>Log in</h1>
    <?php if ($error): ?><div class="flash flash-error"><?= h($error) ?></div><?php endif; ?>
    <form method="post" action="login.php">
        <?= wf_csrf_field() ?>
        <label for="email">Email</label>
        <input type="email" id="email" name="email" required value="<?= h($_POST['email'] ?? '') ?>">

        <label for="password">Password</label>
        <input type="password" id="password" name="password" required>

        <button type="submit">Log in</button>
    </form>
    <p style="margin-top:16px;"><a href="register.php">Need an account? Register</a></p>
</div>
<?php require __DIR__ . '/partials/footer.php'; ?>

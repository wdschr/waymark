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
    $confirm = $_POST['password_confirm'] ?? '';

    if ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        [$ok, $result] = wf_register($pdo, $email, $password);
        if ($ok) {
            wf_login($pdo, $email, $password);
            wf_flash_set('Welcome to Waymark!', 'success');
            wf_redirect('index.php');
        }
        $error = $result;
    }
}

$pageTitle = 'Register - Waymark';
$user = null;
require __DIR__ . '/partials/header.php';
?>
<div class="panel" style="max-width:400px;margin:40px auto;">
    <h1>Create an account</h1>
    <?php if ($error): ?><div class="flash flash-error"><?= h($error) ?></div><?php endif; ?>
    <form method="post" action="register.php">
        <?= wf_csrf_field() ?>
        <label for="email">Email</label>
        <input type="email" id="email" name="email" required value="<?= h($_POST['email'] ?? '') ?>">

        <label for="password">Password</label>
        <input type="password" id="password" name="password" required minlength="8">

        <label for="password_confirm">Confirm password</label>
        <input type="password" id="password_confirm" name="password_confirm" required minlength="8">

        <button type="submit">Register</button>
    </form>
    <p style="margin-top:16px;"><a href="login.php">Already have an account? Log in</a></p>
</div>
<?php require __DIR__ . '/partials/footer.php'; ?>

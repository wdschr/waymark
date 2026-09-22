<?php
declare(strict_types=1);

/**
 * Web-based setup wizard, in the same spirit as Roundcube's installer/:
 * check requirements, collect and test DB credentials, import the schema,
 * write config.php, and create the first account - all through the
 * browser, since many budget shared-hosting accounts don't reliably give
 * you SSH or Composer to do this from a terminal.
 *
 * DELETE THIS DIRECTORY (or at least install/index.php) once setup is
 * done. Left in place, it lets anyone who finds it reconfigure your
 * database or create new accounts.
 */

require __DIR__ . '/../includes/functions.php';
require __DIR__ . '/../includes/csrf.php';
require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/auth.php';

$configFile = __DIR__ . '/../config.php';

// Same session name/cookie params bootstrap.php uses, so a session started
// here (and an auto-login in the last step) carries over into the app.
session_name('wf_session');
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'secure'   => !empty($_SERVER['HTTPS']),
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

if (!isset($_SESSION['install'])) {
    $_SESSION['install'] = [];
}
$state = &$_SESSION['install'];

if (($_GET['force'] ?? '') === '1') {
    $state['forced'] = true;
}

$steps = ['welcome', 'database', 'schema', 'config', 'account', 'done'];
$step = $_GET['step'] ?? 'welcome';
if (!in_array($step, $steps, true)) {
    $step = 'welcome';
}

function install_render_start(string $title): void
{
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($title) ?> - Waymark setup</title>
<link rel="stylesheet" href="../assets/style.css">
</head>
<body>
<header class="topbar">
    <span class="brand">Waymark setup</span>
</header>
<main>
<div class="panel" style="max-width:560px;margin:24px auto;">
    <?php
}

function install_render_end(): void
{
    ?>
</div>
</main>
</body>
</html>
    <?php
    exit;
}

function install_step_indicator(array $steps, string $current): string
{
    $labels = [
        'welcome'  => 'Requirements',
        'database' => 'Database',
        'schema'   => 'Schema',
        'config'   => 'Configuration',
        'account'  => 'Your account',
        'done'     => 'Done',
    ];
    $out = '<p style="color:var(--muted);font-size:0.85rem;margin-top:0;">';
    $parts = [];
    foreach ($steps as $s) {
        $label = h($labels[$s] ?? $s);
        $parts[] = $s === $current ? "<strong>{$label}</strong>" : $label;
    }
    return $out . implode(' &rarr; ', $parts) . '</p>';
}

function install_pdo_from(array $db): PDO
{
    return wf_db(['db' => $db]);
}

/**
 * Splits schema.sql into individual statements. Safe here because the
 * file is entirely our own controlled SQL with no semicolons inside
 * string literals or comments other than as statement terminators.
 */
function install_split_sql(string $sql): array
{
    $lines = explode("\n", $sql);
    $lines = array_filter($lines, static fn($l) => !str_starts_with(ltrim($l), '--'));
    $sql = implode("\n", $lines);

    $statements = array_map('trim', explode(';', $sql));
    return array_values(array_filter($statements, static fn($s) => $s !== ''));
}

// Refuse to run once already installed, unless explicitly forced.
if (is_file($configFile) && empty($state['forced'])) {
    install_render_start('Already installed');
    ?>
    <h1>Waymark is already configured</h1>
    <p><code>config.php</code> already exists, which usually means setup has
       already run. Continuing could overwrite your existing configuration.</p>
    <p>If you're sure you want to redo setup (for example, to fix a broken
       config), you can continue anyway:</p>
    <p><a class="btn danger" href="?step=welcome&amp;force=1">Continue anyway</a></p>
    <p style="margin-top:20px;"><a href="../login.php">Go to login instead</a></p>
    <?php
    install_render_end();
}

switch ($step) {
    case 'welcome':
        $phpOk = PHP_VERSION_ID >= 80100;
        $extensions = ['pdo_mysql', 'curl', 'dom', 'mbstring', 'session'];
        $extResults = [];
        $allExtOk = true;
        foreach ($extensions as $ext) {
            $ok = extension_loaded($ext);
            $extResults[$ext] = $ok;
            if (!$ok) {
                $allExtOk = false;
            }
        }
        $configDirWritable = is_writable(dirname($configFile));
        $canProceed = $phpOk && $allExtOk;

        install_render_start('Requirements');
        echo install_step_indicator($steps, 'welcome');
        ?>
        <h1>Welcome to Waymark</h1>
        <p>This wizard will check your environment, connect to your MySQL
           database, install the schema, write <code>config.php</code>, and
           create your first account.</p>

        <h2>Environment check</h2>
        <ul>
            <li><?= $phpOk ? '&check;' : '&cross;' ?> PHP version
                (<?= h(PHP_VERSION) ?>, need 8.1+)</li>
            <?php foreach ($extResults as $ext => $ok): ?>
                <li><?= $ok ? '&check;' : '&cross;' ?> <?= h($ext) ?> extension</li>
            <?php endforeach; ?>
            <li><?= $configDirWritable ? '&check;' : '&#9888;' ?>
                <?= h(dirname($configFile)) ?> is
                <?= $configDirWritable ? 'writable' : 'not writable (config.php can still be downloaded to save manually)' ?></li>
        </ul>

        <?php if (!$canProceed): ?>
            <p style="color:var(--danger);">Your environment doesn't meet the
               minimum requirements above. Fix these (or ask your hosting
               provider) before continuing.</p>
        <?php else: ?>
            <p><a class="btn" href="?step=database<?= !empty($state['forced']) ? '&force=1' : '' ?>">Continue</a></p>
        <?php endif; ?>
        <?php
        install_render_end();
        break;

    case 'database':
        $error = null;
        $values = $state['db'] ?? ['host' => 'localhost', 'port' => '', 'name' => '', 'user' => '', 'pass' => ''];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            wf_csrf_verify();

            $values = [
                'host' => wf_post_string('host'),
                'port' => wf_post_string('port'),
                'name' => wf_post_string('name'),
                'user' => wf_post_string('user'),
                'pass' => (string) ($_POST['pass'] ?? ''),
            ];

            $db = [
                'host'    => $values['host'],
                'name'    => $values['name'],
                'user'    => $values['user'],
                'pass'    => $values['pass'],
                'charset' => 'utf8mb4',
            ];
            if ($values['port'] !== '') {
                $db['port'] = (int) $values['port'];
            }

            try {
                install_pdo_from($db);
                $state['db'] = $db;
                // Proving you know the real DB credentials is itself enough
                // to keep going even if config.php already exists (e.g. it
                // was written moments ago by this very run) - otherwise the
                // "already installed" guard below would lock the wizard out
                // of its own later steps.
                $state['forced'] = true;
                wf_redirect('?step=schema&force=1');
            } catch (PDOException $e) {
                $error = 'Could not connect: ' . $e->getMessage();
            }
        }

        install_render_start('Database');
        echo install_step_indicator($steps, 'database');
        ?>
        <h1>Database connection</h1>
        <p>Enter the database your hosting control panel created for
           you. On many shared hosts the database name and username are
           the same value, and the exact host is shown wherever your
           panel lists your databases - it isn't always <code>localhost</code>.</p>
        <?php if ($error): ?><div class="flash flash-error"><?= h($error) ?></div><?php endif; ?>
        <form method="post" action="?step=database">
            <?= wf_csrf_field() ?>
            <label for="host">Database host</label>
            <input type="text" id="host" name="host" required value="<?= h($values['host']) ?>">

            <label for="port">Port (leave blank for default)</label>
            <input type="text" id="port" name="port" value="<?= h($values['port']) ?>">

            <label for="name">Database name</label>
            <input type="text" id="name" name="name" required value="<?= h($values['name']) ?>">

            <label for="user">Database username</label>
            <input type="text" id="user" name="user" required value="<?= h($values['user']) ?>">

            <label for="pass">Database password</label>
            <input type="password" id="pass" name="pass" required>

            <button type="submit">Test connection &amp; continue</button>
        </form>
        <?php
        install_render_end();
        break;

    case 'schema':
        if (empty($state['db'])) {
            wf_redirect('?step=database' . (!empty($state['forced']) ? '&force=1' : ''));
        }

        $error = null;
        $result = null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            wf_csrf_verify();

            try {
                $pdo = install_pdo_from($state['db']);
                $existing = $pdo->query("SHOW TABLES LIKE 'users'")->fetch();

                if ($existing) {
                    $result = 'The `users` table already exists - schema import skipped.';
                } else {
                    $sql = file_get_contents(__DIR__ . '/../schema.sql');
                    if ($sql === false) {
                        throw new RuntimeException('Could not read schema.sql - is it still in the repo root?');
                    }
                    foreach (install_split_sql($sql) as $statement) {
                        $pdo->exec($statement);
                    }
                    $result = 'Schema imported successfully.';
                }

                $state['schema_ok'] = true;
            } catch (Throwable $e) {
                $error = 'Schema import failed: ' . $e->getMessage();
            }
        }

        install_render_start('Schema');
        echo install_step_indicator($steps, 'schema');
        ?>
        <h1>Install the schema</h1>
        <p>This creates the <code>users</code>, <code>collections</code>,
           <code>links</code>, <code>tags</code> and <code>link_tags</code>
           tables from <code>schema.sql</code>. Safe to re-run - it's
           skipped automatically if the tables already exist.</p>
        <?php if ($error): ?><div class="flash flash-error"><?= h($error) ?></div><?php endif; ?>
        <?php if ($result): ?><div class="flash flash-success"><?= h($result) ?></div><?php endif; ?>

        <?php if (!empty($state['schema_ok'])): ?>
            <p><a class="btn" href="?step=config<?= !empty($state['forced']) ? '&force=1' : '' ?>">Continue</a></p>
        <?php else: ?>
            <form method="post" action="?step=schema">
                <?= wf_csrf_field() ?>
                <button type="submit">Import schema</button>
            </form>
            <p style="color:var(--muted);font-size:0.85rem;margin-top:16px;">
                If this fails, you can always import <code>schema.sql</code>
                manually via phpMyAdmin instead - see the README.
            </p>
        <?php endif; ?>
        <?php
        install_render_end();
        break;

    case 'config':
        if (empty($state['db']) || empty($state['schema_ok'])) {
            wf_redirect('?step=database' . (!empty($state['forced']) ? '&force=1' : ''));
        }

        $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/install/index.php'));
        $appPath = rtrim(dirname($scriptDir), '/');
        $scheme = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http';
        $defaultBaseUrl = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'example.com') . $appPath;

        $error = null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            wf_csrf_verify();

            $baseUrl = rtrim(wf_post_string('base_url'), '/');
            if (!preg_match('#^https?://#i', $baseUrl)) {
                $error = 'Enter a valid http(s) base URL.';
            } else {
                $state['session_secret'] = $state['session_secret'] ?? bin2hex(random_bytes(32));
                $state['cron_secret'] = $state['cron_secret'] ?? bin2hex(random_bytes(32));
                $state['base_url'] = $baseUrl;

                $content = install_build_config_php($state);
                $written = @file_put_contents($configFile, $content);

                $state['config_written'] = $written !== false;
                $state['config_content'] = $content;

                wf_redirect('?step=account' . (!empty($state['forced']) ? '&force=1' : ''));
            }
        }

        install_render_start('Configuration');
        echo install_step_indicator($steps, 'config');
        ?>
        <h1>Configuration</h1>
        <p>A session secret and cron secret will be generated automatically
           - you don't need to type or remember these. Just confirm the URL
           where this install is reachable:</p>
        <?php if ($error): ?><div class="flash flash-error"><?= h($error) ?></div><?php endif; ?>
        <form method="post" action="?step=config">
            <?= wf_csrf_field() ?>
            <label for="base_url">Base URL (no trailing slash)</label>
            <input type="url" id="base_url" name="base_url" required value="<?= h($defaultBaseUrl) ?>">
            <p style="color:var(--muted);font-size:0.85rem;">
                Used to build the bookmarklet and shared-collection links -
                get it right.
            </p>
            <button type="submit">Write config.php &amp; continue</button>
        </form>
        <?php
        install_render_end();
        break;

    case 'account':
        if (empty($state['db']) || empty($state['schema_ok']) || empty($state['base_url'])) {
            wf_redirect('?step=database' . (!empty($state['forced']) ? '&force=1' : ''));
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
                $pdo = install_pdo_from($state['db']);
                [$ok, $result] = wf_register($pdo, $email, $password);
                if ($ok) {
                    wf_login($pdo, $email, $password);
                    $state['account_done'] = true;
                    wf_redirect('?step=done' . (!empty($state['forced']) ? '&force=1' : ''));
                }
                $error = $result;
            }
        }

        install_render_start('Your account');
        echo install_step_indicator($steps, 'account');
        ?>
        <h1>Create your account</h1>
        <?php if (!empty($state['config_written'])): ?>
            <div class="flash flash-success">config.php was written successfully.</div>
        <?php elseif (isset($state['config_written'])): ?>
            <div class="flash flash-error">
                config.php could not be written automatically (the
                directory isn't writable). Copy the content below into a
                file named <code>config.php</code> in the app root via File
                Manager, then continue.
            </div>
            <pre><?= h($state['config_content'] ?? '') ?></pre>
        <?php endif; ?>
        <?php if ($error): ?><div class="flash flash-error"><?= h($error) ?></div><?php endif; ?>
        <form method="post" action="?step=account">
            <?= wf_csrf_field() ?>
            <label for="email">Email</label>
            <input type="email" id="email" name="email" required value="<?= h($_POST['email'] ?? '') ?>">

            <label for="password">Password</label>
            <input type="password" id="password" name="password" required minlength="8">

            <label for="password_confirm">Confirm password</label>
            <input type="password" id="password_confirm" name="password_confirm" required minlength="8">

            <button type="submit">Create account</button>
        </form>
        <?php
        install_render_end();
        break;

    case 'done':
        if (empty($state['account_done'])) {
            wf_redirect('?step=database' . (!empty($state['forced']) ? '&force=1' : ''));
        }

        $baseUrl = $state['base_url'] ?? '..';
        unset($_SESSION['install']);

        install_render_start('Done');
        ?>
        <h1>Setup complete</h1>
        <p>Your account was created and you're already logged in.</p>

        <div class="flash flash-error">
            <strong>Delete the <code>install/</code> directory now</strong>
            (or at least this file) via File Manager or FTP. Left in place,
            anyone who finds it can reconfigure your database or create new
            accounts.
        </div>

        <p><a class="btn" href="<?= h(rtrim($baseUrl, '/')) ?>/index.php">Go to your links</a></p>
        <?php
        install_render_end();
        break;
}

function install_build_config_php(array $state): string
{
    $db = $state['db'];
    $lines = [];
    $lines[] = '<?php';
    $lines[] = 'return [';
    $lines[] = "    'db' => [";
    $lines[] = "        'host'    => " . var_export($db['host'], true) . ',';
    if (!empty($db['port'])) {
        $lines[] = "        'port'    => " . var_export((int) $db['port'], true) . ',';
    }
    $lines[] = "        'name'    => " . var_export($db['name'], true) . ',';
    $lines[] = "        'user'    => " . var_export($db['user'], true) . ',';
    $lines[] = "        'pass'    => " . var_export($db['pass'], true) . ',';
    $lines[] = "        'charset' => 'utf8mb4',";
    $lines[] = '    ],';
    $lines[] = '';
    $lines[] = "    'session_secret' => " . var_export($state['session_secret'], true) . ',';
    $lines[] = "    'cron_secret'    => " . var_export($state['cron_secret'], true) . ',';
    $lines[] = "    'base_url'       => " . var_export($state['base_url'], true) . ',';
    $lines[] = '];';
    $lines[] = '';

    return implode("\n", $lines);
}

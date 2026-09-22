<?php
declare(strict_types=1);

/**
 * Dead-link checker, meant to run from a scheduled task (cron) every
 * 5-15 minutes.
 * It is NOT something a user waits on in a browser request.
 *
 * Resource-limit design notes:
 *  - Shared hosting caps execution time (~30-60s) and gives no background
 *    workers, so this can't just "check every link" in one run. Instead it
 *    processes a small batch (BATCH_SIZE) ordered oldest-checked-first, so
 *    each run picks up where the last one left off - fully resumable
 *    without any job-tracking table.
 *  - It also tracks wall-clock time and bails out early if a run of slow
 *    HEAD requests threatens to eat the whole execution-time budget,
 *    leaving the rest for the next cron tick rather than risking a fatal
 *    timeout mid-batch.
 *  - Every fetch goes through the same SSRF-guarded wf_safe_fetch() used
 *    for metadata fetching on save.
 *
 * Can be run two ways:
 *   php /path/to/cron/check_links.php                 (PHP CLI, preferred)
 *   curl "https://yoursite.com/cron/check_links.php?token=YOUR_CRON_SECRET"
 */

set_time_limit(50);

$configFile = __DIR__ . '/../config.php';
if (!is_file($configFile)) {
    fwrite(STDERR, "Missing config.php\n");
    exit(1);
}
$config = require $configFile;

require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/http_fetch.php';

$isCli = PHP_SAPI === 'cli';

if (!$isCli) {
    $token = (string) ($_GET['token'] ?? '');
    $expected = (string) ($config['cron_secret'] ?? '');
    if ($expected === '' || !hash_equals($expected, $token)) {
        http_response_code(403);
        die('Forbidden.');
    }
    header('Content-Type: text/plain; charset=UTF-8');
}

const WF_CRON_BATCH_SIZE = 50;
const WF_CRON_TIME_BUDGET_SECONDS = 40; // stay well under typical 30-60s hosting limits

$pdo = wf_db($config);
$startedAt = microtime(true);

$stmt = $pdo->query(
    'SELECT id, url FROM links
     ORDER BY (last_checked_at IS NOT NULL), last_checked_at ASC
     LIMIT ' . WF_CRON_BATCH_SIZE
);
$links = $stmt->fetchAll();

$update = $pdo->prepare('UPDATE links SET is_broken = ?, last_checked_at = NOW() WHERE id = ?');

$checked = 0;
$broken = 0;
$stoppedEarly = false;

foreach ($links as $link) {
    if (microtime(true) - $startedAt > WF_CRON_TIME_BUDGET_SECONDS) {
        $stoppedEarly = true;
        break;
    }

    $response = wf_safe_fetch($link['url'], 'HEAD');

    // Some servers don't support HEAD; fall back to GET before giving up.
    if (!$response || in_array($response['status'], [405, 501], true)) {
        $response = wf_safe_fetch($link['url'], 'GET');
    }

    $isBroken = !$response || $response['status'] < 200 || $response['status'] >= 400;

    $update->execute([$isBroken ? 1 : 0, $link['id']]);
    $checked++;
    if ($isBroken) {
        $broken++;
    }
}

$total = count($links);
echo "Waymark dead-link check: checked {$checked}/{$total} link(s), {$broken} broken.\n";
if ($stoppedEarly) {
    echo "Stopped early to stay within the execution time budget - remaining links will be picked up on the next run.\n";
}

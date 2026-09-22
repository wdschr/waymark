<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

$user = wf_require_login($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    wf_csrf_verify();

    if (empty($_FILES['bookmarks_file']) || $_FILES['bookmarks_file']['error'] !== UPLOAD_ERR_OK) {
        wf_flash_set('Please choose a bookmarks HTML file to upload.', 'error');
        wf_redirect('import.php');
    }

    $maxBytes = 8 * 1024 * 1024;
    if ($_FILES['bookmarks_file']['size'] > $maxBytes) {
        wf_flash_set('That file is too large to import in one request (max 8MB). Split it into smaller exports.', 'error');
        wf_redirect('import.php');
    }

    $html = file_get_contents($_FILES['bookmarks_file']['tmp_name']);
    $parsed = wf_parse_netscape_bookmarks($html ?: '');

    // Hard cap so a single request can't outrun shared-hosting execution
    // time limits. We also skip metadata fetching entirely during import
    // (see note below) - the cap is a second line of defence for very
    // large exports.
    $maxLinks = 3000;
    $parsed = array_slice($parsed, 0, $maxLinks);

    $collectionCache = [];
    foreach (wf_get_collections($pdo, $user['id']) as $c) {
        $collectionCache[$c['name']] = (int) $c['id'];
    }

    $imported = 0;
    $skipped = 0;

    $pdo->beginTransaction();
    try {
        foreach ($parsed as $item) {
            if (!preg_match('#^https?://#i', $item['url'])) {
                $skipped++;
                continue;
            }

            $collectionId = null;
            if ($item['collection']) {
                if (!array_key_exists($item['collection'], $collectionCache)) {
                    [$ok, $idOrError] = wf_create_collection($pdo, $user['id'], $item['collection']);
                    $collectionCache[$item['collection']] = $ok ? $idOrError : null;
                }
                $collectionId = $collectionCache[$item['collection']];
            }

            wf_create_link($pdo, $user['id'], [
                'collection_id' => $collectionId,
                'url'           => $item['url'],
                'title'         => $item['title'],
                'description'   => $item['description'],
                'image_url'     => '',
                'tags'          => $item['tags'],
            ]);
            $imported++;
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        wf_flash_set('Import failed, no links were added: ' . $e->getMessage(), 'error');
        wf_redirect('import.php');
    }

    $message = "Imported {$imported} links";
    if ($skipped) {
        $message .= ", skipped {$skipped} invalid entries";
    }
    wf_flash_set($message . '.', 'success');
    wf_redirect('index.php');
}

$pageTitle = 'Import - Waymark';
require __DIR__ . '/partials/header.php';
?>
<div class="panel" style="max-width:500px;">
    <h1>Import bookmarks</h1>
    <p>Upload the HTML bookmarks file exported by your browser. Chrome, Firefox, Safari and Edge all use the same
       Netscape Bookmark format.</p>
    <form method="post" action="import.php" enctype="multipart/form-data">
        <?= wf_csrf_field() ?>
        <label for="bookmarks_file">Bookmarks HTML file</label>
        <input type="file" id="bookmarks_file" name="bookmarks_file" accept=".html,.htm" required>
        <button type="submit">Import</button>
    </form>
    <p style="color:var(--muted);font-size:0.85rem;margin-top:16px;">
        Page previews (title, description, image) are <strong>not</strong> fetched automatically during import.
        Fetching a live page for every one of potentially thousands of imported bookmarks would blow past
        shared hosting's per-request execution time limit. Imported links keep the title your browser already
        had for them; use "Refresh metadata" on an individual link afterwards if you want its description
        and preview image filled in.
    </p>
</div>
<?php require __DIR__ . '/partials/footer.php'; ?>

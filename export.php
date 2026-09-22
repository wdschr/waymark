<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

$user = wf_require_login($pdo);

$collections = wf_get_collections($pdo, $user['id']);

$stmt = $pdo->prepare('SELECT * FROM links WHERE user_id = ? AND collection_id IS NOT NULL ORDER BY created_at');
$stmt->execute([$user['id']]);
$byCollection = [];
foreach ($stmt->fetchAll() as $link) {
    $byCollection[$link['collection_id']][] = $link;
}

$stmt = $pdo->prepare('SELECT * FROM links WHERE user_id = ? AND collection_id IS NULL ORDER BY created_at');
$stmt->execute([$user['id']]);
$uncategorized = $stmt->fetchAll();

$html = wf_generate_netscape_html($collections, $byCollection, $uncategorized);

header('Content-Type: text/html; charset=UTF-8');
header('Content-Disposition: attachment; filename="waymark-bookmarks-' . date('Y-m-d') . '.html"');
header('Content-Length: ' . (string) strlen($html));
echo $html;

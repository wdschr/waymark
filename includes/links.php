<?php
declare(strict_types=1);

function wf_upsert_tag(PDO $pdo, int $userId, string $name): int
{
    $stmt = $pdo->prepare(
        'INSERT INTO tags (user_id, name) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)'
    );
    $stmt->execute([$userId, $name]);
    return (int) $pdo->lastInsertId();
}

function wf_set_link_tags(PDO $pdo, int $userId, int $linkId, array $tagNames): void
{
    $pdo->prepare('DELETE FROM link_tags WHERE link_id = ?')->execute([$linkId]);

    $tagIds = [];
    foreach ($tagNames as $name) {
        $tagIds[] = wf_upsert_tag($pdo, $userId, $name);
    }

    if ($tagIds) {
        $stmt = $pdo->prepare('INSERT IGNORE INTO link_tags (link_id, tag_id) VALUES (?, ?)');
        foreach ($tagIds as $tagId) {
            $stmt->execute([$linkId, $tagId]);
        }
    }

    $pdo->prepare('UPDATE links SET tags_cache = ? WHERE id = ?')
        ->execute([implode(',', $tagNames), $linkId]);
}

function wf_create_link(PDO $pdo, int $userId, array $data): int
{
    $stmt = $pdo->prepare(
        'INSERT INTO links (user_id, collection_id, url, title, description, image_url)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $userId,
        $data['collection_id'],
        $data['url'],
        $data['title'],
        $data['description'],
        $data['image_url'],
    ]);
    $linkId = (int) $pdo->lastInsertId();

    wf_set_link_tags($pdo, $userId, $linkId, $data['tags'] ?? []);

    return $linkId;
}

function wf_update_link(PDO $pdo, int $userId, int $linkId, array $data): void
{
    $stmt = $pdo->prepare(
        'UPDATE links SET collection_id = ?, url = ?, title = ?, description = ?, image_url = ?
         WHERE id = ? AND user_id = ?'
    );
    $stmt->execute([
        $data['collection_id'],
        $data['url'],
        $data['title'],
        $data['description'],
        $data['image_url'],
        $linkId,
        $userId,
    ]);

    wf_set_link_tags($pdo, $userId, $linkId, $data['tags'] ?? []);
}

function wf_delete_link(PDO $pdo, int $userId, int $linkId): void
{
    $pdo->prepare('DELETE FROM links WHERE id = ? AND user_id = ?')->execute([$linkId, $userId]);
}

function wf_get_link(PDO $pdo, int $userId, int $linkId): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM links WHERE id = ? AND user_id = ?');
    $stmt->execute([$linkId, $userId]);
    $link = $stmt->fetch();
    return $link ?: null;
}

/**
 * Searches/filters a user's links. $filters may contain 'q' (fulltext
 * search string), 'collection_id' and/or 'tag'.
 */
function wf_search_links(PDO $pdo, int $userId, array $filters): array
{
    $where = ['links.user_id = ?'];
    $params = [$userId];
    $join = '';

    $q = trim((string) ($filters['q'] ?? ''));
    if ($q !== '') {
        $where[] = 'MATCH(links.title, links.description, links.url, links.tags_cache) AGAINST (? IN NATURAL LANGUAGE MODE)';
        $params[] = $q;
    }

    if (!empty($filters['collection_id'])) {
        $where[] = 'links.collection_id = ?';
        $params[] = (int) $filters['collection_id'];
    }

    if (!empty($filters['tag'])) {
        $join = ' JOIN link_tags lt ON lt.link_id = links.id JOIN tags t ON t.id = lt.tag_id ';
        $where[] = 't.user_id = ? AND t.name = ?';
        $params[] = $userId;
        $params[] = $filters['tag'];
    }

    $sql = 'SELECT DISTINCT links.* FROM links' . $join . ' WHERE ' . implode(' AND ', $where)
        . ' ORDER BY links.created_at DESC LIMIT 200';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function wf_tags_for_user(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare('SELECT id, name FROM tags WHERE user_id = ? ORDER BY name');
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

<?php
declare(strict_types=1);

function wf_get_collections(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare('SELECT * FROM collections WHERE user_id = ? ORDER BY name');
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

function wf_get_collection(PDO $pdo, int $userId, int $collectionId): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM collections WHERE id = ? AND user_id = ?');
    $stmt->execute([$collectionId, $userId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function wf_create_collection(PDO $pdo, int $userId, string $name): array
{
    $name = trim($name);
    if ($name === '') {
        return [false, 'Collection name cannot be empty.'];
    }
    try {
        $stmt = $pdo->prepare('INSERT INTO collections (user_id, name) VALUES (?, ?)');
        $stmt->execute([$userId, mb_substr($name, 0, 255)]);
        return [true, (int) $pdo->lastInsertId()];
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') {
            return [false, 'You already have a collection with that name.'];
        }
        throw $e;
    }
}

function wf_rename_collection(PDO $pdo, int $userId, int $collectionId, string $name): array
{
    $name = trim($name);
    if ($name === '') {
        return [false, 'Collection name cannot be empty.'];
    }
    try {
        $stmt = $pdo->prepare('UPDATE collections SET name = ? WHERE id = ? AND user_id = ?');
        $stmt->execute([mb_substr($name, 0, 255), $collectionId, $userId]);
        return [true, null];
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') {
            return [false, 'You already have a collection with that name.'];
        }
        throw $e;
    }
}

function wf_delete_collection(PDO $pdo, int $userId, int $collectionId): void
{
    $pdo->prepare('DELETE FROM collections WHERE id = ? AND user_id = ?')->execute([$collectionId, $userId]);
}

function wf_set_collection_share(PDO $pdo, int $userId, int $collectionId, bool $enabled): ?string
{
    if ($enabled) {
        $token = bin2hex(random_bytes(24));
        $pdo->prepare('UPDATE collections SET share_token = ? WHERE id = ? AND user_id = ?')
            ->execute([$token, $collectionId, $userId]);
        return $token;
    }
    $pdo->prepare('UPDATE collections SET share_token = NULL WHERE id = ? AND user_id = ?')
        ->execute([$collectionId, $userId]);
    return null;
}

function wf_get_collection_by_share_token(PDO $pdo, string $token): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM collections WHERE share_token = ?');
    $stmt->execute([$token]);
    $row = $stmt->fetch();
    return $row ?: null;
}

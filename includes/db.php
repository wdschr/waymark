<?php
declare(strict_types=1);

function wf_db(array $config): PDO
{
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    $db = $config['db'];

    if (!empty($db['unix_socket'])) {
        $dsn = sprintf(
            'mysql:unix_socket=%s;dbname=%s;charset=%s',
            $db['unix_socket'],
            $db['name'],
            $db['charset'] ?? 'utf8mb4'
        );
    } else {
        $dsn = sprintf(
            'mysql:host=%s%s;dbname=%s;charset=%s',
            $db['host'],
            !empty($db['port']) ? ';port=' . $db['port'] : '',
            $db['name'],
            $db['charset'] ?? 'utf8mb4'
        );
    }

    $pdo = new PDO($dsn, $db['user'], $db['pass'], [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);

    return $pdo;
}

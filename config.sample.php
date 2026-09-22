<?php
// Copy this file to config.php and fill in your real values.
// config.php holds credentials - it is gitignored and must never be committed.

return [
    'db' => [
        // On Heart Internet's eXtend control panel, the database and its
        // user are created together as one username (Web Tools > MySQL
        // Databases) - the database gets the same name as that username.
        // The exact host to use is shown on that same page after creation;
        // it is not always "localhost".
        'host'    => 'localhost',
        // 'port' => 3306,          // uncomment if your host uses a non-default port
        // 'unix_socket' => '/path/to/mysql.sock', // uncomment to connect via socket instead of host/port
        'name'    => 'yourdbuser',
        'user'    => 'yourdbuser',
        'pass'    => 'change-me',
        'charset' => 'utf8mb4',
    ],

    // Random secret used to sign the CSRF token. Generate with:
    // php -r "echo bin2hex(random_bytes(32));"
    'session_secret' => 'change-me-to-a-random-64-char-hex-string',

    // Shared secret for triggering the dead-link cron over plain HTTP.
    // Only needed if your cron job calls a URL instead of the PHP CLI.
    'cron_secret' => 'change-me-too',

    // Full base URL of your install, no trailing slash.
    // Used to build absolute links for sharing and the bookmarklet.
    'base_url' => 'https://example.com/waymark',
];

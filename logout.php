<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    wf_csrf_verify();
    wf_logout();
}

wf_redirect('login.php');

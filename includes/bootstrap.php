<?php
declare(strict_types=1);

if (!is_file(__DIR__ . '/config.php')) {
    http_response_code(503);
    exit('Konfigurasi belum ada: salin includes/config.example.php menjadi includes/config.php, lalu isi data database.');
}
require __DIR__ . '/config.php';
require __DIR__ . '/functions.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
    session_start();
}
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(16));
}

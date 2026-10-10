<?php
declare(strict_types=1);
require __DIR__ . '/_init.php';
unset($_SESSION['admin'], $_SESSION['admin_seen']);
session_regenerate_id(true);
redirect('login.php');

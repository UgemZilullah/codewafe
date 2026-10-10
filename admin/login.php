<?php
declare(strict_types=1);
require __DIR__ . '/_init.php';

if (admin()) {
    redirect('index.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = trim((string) ($_POST['username'] ?? ''));
    $pass = (string) ($_POST['password'] ?? '');
    $_SESSION['login_fail'] = $_SESSION['login_fail'] ?? 0;

    if (!csrf_ok()) {
        $error = 'Sesi kedaluwarsa. Muat ulang halaman lalu coba lagi.';
    } elseif ($_SESSION['login_fail'] >= 5 && time() - ($_SESSION['login_fail_at'] ?? 0) < 300) {
        $error = 'Terlalu banyak percobaan gagal. Tunggu 5 menit.';
    } else {
        $st = db()->prepare('SELECT * FROM admins WHERE username = ?');
        $st->execute([$user]);
        $row = $st->fetch();
        if ($row && password_verify($pass, $row['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['admin'] = ['id' => (int) $row['id'], 'username' => $row['username']];
            $_SESSION['admin_seen'] = time();
            $_SESSION['login_fail'] = 0;
            db()->prepare('UPDATE admins SET last_login = NOW() WHERE id = ?')->execute([$row['id']]);
            redirect('index.php');
        }
        $_SESSION['login_fail']++;
        $_SESSION['login_fail_at'] = time();
        $error = 'Username atau password salah.';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Masuk | Admin CodeWave</title>
    <link rel="icon" type="image/png" href="../assets/img/icon-codewave.png">
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body class="login">
<form class="card login__card" method="post">
    <img src="../assets/img/logo-codewave.png" alt="CodeWave" class="login__logo">
    <h1>Masuk ke panel admin</h1>
    <?php if (isset($_GET['expired'])): ?><p class="flash flash--err">Sesi berakhir. Silakan masuk lagi.</p><?php endif; ?>
    <?php if ($error): ?><p class="flash flash--err" role="alert"><?= e($error) ?></p><?php endif; ?>
    <?= csrf_field() ?>
    <label for="username">Username</label>
    <input id="username" name="username" autocomplete="username" required autofocus>
    <label for="password">Password</label>
    <input id="password" name="password" type="password" autocomplete="current-password" required>
    <button class="btn">Masuk</button>
</form>
</body>
</html>

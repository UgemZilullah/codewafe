<?php
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';

function admin(): ?array
{
    return $_SESSION['admin'] ?? null;
}

function require_login(): void
{
    if (!admin()) {
        header('Location: login.php');
        exit;
    }
    // sesi admin kedaluwarsa setelah 2 jam tidak aktif
    if (time() - ($_SESSION['admin_seen'] ?? 0) > 7200) {
        unset($_SESSION['admin']);
        header('Location: login.php?expired=1');
        exit;
    }
    $_SESSION['admin_seen'] = time();
}

function flash(?string $msg = null, string $type = 'ok'): ?array
{
    if ($msg !== null) {
        $_SESSION['flash'] = ['msg' => $msg, 'type' => $type];
        return null;
    }
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}

function redirect(string $to): never
{
    header('Location: ' . $to, true, 303);
    exit;
}

/** Tolak POST tanpa token CSRF yang valid. */
function guard_post(): void
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !csrf_ok()) {
        flash('Sesi kedaluwarsa, silakan ulangi.', 'err');
        redirect($_SERVER['REQUEST_URI']);
    }
}

function admin_header(string $title, string $active): void
{
    $menu = [
        'index'      => 'Pesan masuk',
        'paket'      => 'Paket harga',
        'faq'        => 'FAQ',
        'pengaturan' => 'Pengaturan',
    ];
    $new = (int) db()->query("SELECT COUNT(*) FROM leads WHERE status = 'baru'")->fetchColumn();
    ?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?= e($title) ?> | Admin CodeWave</title>
    <link rel="icon" type="image/png" href="../assets/img/icon-codewave.png">
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>
<header class="abar">
    <a href="index.php" class="abar__brand"><img src="../assets/img/logo-codewave.png" alt="CodeWave" height="34"><span>Admin</span></a>
    <nav class="abar__nav">
        <?php foreach ($menu as $file => $label): ?>
            <a href="<?= $file ?>.php" <?= $active === $file ? 'aria-current="page"' : '' ?>>
                <?= e($label) ?><?php if ($file === 'index' && $new): ?> <b class="pill"><?= $new ?></b><?php endif; ?>
            </a>
        <?php endforeach; ?>
    </nav>
    <div class="abar__user">
        <a href="../" target="_blank" rel="noopener">Lihat website</a>
        <a href="logout.php">Keluar (<?= e(admin()['username']) ?>)</a>
    </div>
</header>
<main class="amain">
    <h1><?= e($title) ?></h1>
    <?php if ($f = flash()): ?>
        <p class="flash flash--<?= e($f['type']) ?>" role="status"><?= e($f['msg']) ?></p>
    <?php endif;
}

function admin_footer(): void
{
    echo "</main>\n</body>\n</html>";
}

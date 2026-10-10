<?php
declare(strict_types=1);
require __DIR__ . '/_init.php';
require_login();
guard_post();

$rows   = db()->query('SELECT setting_key, setting_value, label FROM settings ORDER BY FIELD(setting_key,"site_name","tagline","about","wa","email","location","hours","instagram","facebook","tiktok"), setting_key')->fetchAll();
$errors = [];
$pwErrors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'settings') {
    $input = array_map(fn($v) => trim((string) $v), (array) ($_POST['s'] ?? []));
    if (isset($input['wa'])) {
        $input['wa'] = preg_replace('/\D/', '', $input['wa']);
        if (str_starts_with($input['wa'], '0')) {
            $input['wa'] = '62' . substr($input['wa'], 1);
        }
        if (!preg_match('/^62\d{8,13}$/', $input['wa'])) {
            $errors['wa'] = 'Nomor WhatsApp tidak valid. Contoh: 6281234567890.';
        }
    }
    if (isset($input['email']) && !filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Format email tidak valid.';
    }
    foreach (['instagram', 'facebook', 'tiktok'] as $sk) {
        if (($input[$sk] ?? '') !== '' && !preg_match('#^https?://#i', $input[$sk])) {
            $errors[$sk] = 'Tulis link lengkap, diawali https://';
        }
    }
    if (($input['site_name'] ?? 'x') === '') {
        $errors['site_name'] = 'Nama situs wajib diisi.';
    }
    if (!$errors) {
        $st = db()->prepare('UPDATE settings SET setting_value = ? WHERE setting_key = ?');
        foreach ($rows as $r) {
            if (array_key_exists($r['setting_key'], $input)) {
                $st->execute([$input[$r['setting_key']], $r['setting_key']]);
            }
        }
        flash('Pengaturan tersimpan dan langsung tampil di website.');
        redirect('pengaturan.php');
    }
    foreach ($rows as &$r) {
        $r['setting_value'] = $input[$r['setting_key']] ?? $r['setting_value'];
    }
    unset($r);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'password') {
    $st = db()->prepare('SELECT password_hash FROM admins WHERE id = ?');
    $st->execute([admin()['id']]);
    $hash = (string) $st->fetchColumn();
    $new  = (string) ($_POST['new'] ?? '');

    if (!password_verify((string) ($_POST['current'] ?? ''), $hash)) $pwErrors['current'] = 'Password lama salah.';
    if (strlen($new) < 8)                                          $pwErrors['new'] = 'Password baru minimal 8 karakter.';
    elseif ($new !== ($_POST['confirm'] ?? ''))                    $pwErrors['confirm'] = 'Konfirmasi tidak sama dengan password baru.';

    if (!$pwErrors) {
        db()->prepare('UPDATE admins SET password_hash = ? WHERE id = ?')
            ->execute([password_hash($new, PASSWORD_DEFAULT), admin()['id']]);
        flash('Password berhasil diganti.');
        redirect('pengaturan.php');
    }
}

$usingDefault = password_verify('codewave123', (string) db()->query('SELECT password_hash FROM admins WHERE id = ' . (int) admin()['id'])->fetchColumn());

admin_header('Pengaturan', 'pengaturan');
?>

<?php if ($usingDefault): ?>
    <p class="flash flash--err">Anda masih memakai password bawaan. Ganti sekarang di bagian bawah halaman ini.</p>
<?php endif; ?>

<form method="post" class="card editor">
    <h2>Info bisnis</h2>
    <p class="muted">Dipakai di tombol WhatsApp, bagian kontak, dan footer website.</p>
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="settings">
    <?php foreach ($rows as $r): $k = $r['setting_key']; ?>
        <label for="s-<?= e($k) ?>"><?= e($r['label']) ?></label>
        <?php if ($k === 'about'): ?>
        <textarea id="s-about" name="s[about]" rows="4"><?= e($r['setting_value']) ?></textarea>
        <?php else: ?>
        <input id="s-<?= e($k) ?>" name="s[<?= e($k) ?>]" value="<?= e($r['setting_value']) ?>"
               <?= $k === 'wa' ? 'inputmode="tel"' : '' ?><?= $k === 'email' ? ' type="email"' : '' ?><?= in_array($k, ['instagram', 'facebook', 'tiktok'], true) ? ' type="url" placeholder="https://"' : '' ?>
               <?= isset($errors[$k]) ? 'aria-invalid="true" aria-describedby="err-' . e($k) . '"' : '' ?>>
        <?php endif; ?>
        <?php if (isset($errors[$k])): ?><small class="err" id="err-<?= e($k) ?>"><?= e($errors[$k]) ?></small><?php endif; ?>
    <?php endforeach; ?>
    <div class="actions"><button class="btn">Simpan pengaturan</button></div>
</form>

<form method="post" class="card editor">
    <h2>Ganti password admin</h2>
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="password">
    <?php foreach (['current' => ['Password lama', 'current-password'], 'new' => ['Password baru (min. 8 karakter)', 'new-password'], 'confirm' => ['Ulangi password baru', 'new-password']] as $k => [$label, $ac]): ?>
        <label for="pw-<?= $k ?>"><?= $label ?></label>
        <input id="pw-<?= $k ?>" name="<?= $k ?>" type="password" autocomplete="<?= $ac ?>" <?= isset($pwErrors[$k]) ? 'aria-invalid="true" aria-describedby="perr-' . $k . '"' : '' ?>>
        <?php if (isset($pwErrors[$k])): ?><small class="err" id="perr-<?= $k ?>"><?= e($pwErrors[$k]) ?></small><?php endif; ?>
    <?php endforeach; ?>
    <div class="actions"><button class="btn">Ganti password</button></div>
</form>

<?php admin_footer(); ?>

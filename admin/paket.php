<?php
declare(strict_types=1);
require __DIR__ . '/_init.php';
require_login();
guard_post();

$editId = isset($_GET['edit']) ? (int) $_GET['edit'] : null;   // 0 = paket baru
$errors = [];
$form   = null;

/* ---------- Aksi ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id     = (int) ($_POST['id'] ?? 0);

    if ($action === 'toggle') {
        db()->prepare('UPDATE packages SET is_active = 1 - is_active WHERE id = ?')->execute([$id]);
        flash('Status tampil paket diperbarui.');
        redirect('paket.php');
    }
    if ($action === 'delete') {
        db()->prepare('DELETE FROM packages WHERE id = ?')->execute([$id]);
        flash('Paket dihapus. Pesan masuk yang memilih paket ini tetap tersimpan.');
        redirect('paket.php');
    }
    if ($action === 'move') {
        $dir  = ($_POST['dir'] ?? '') === 'up' ? -1 : 1;
        $rows = db()->query('SELECT id FROM packages ORDER BY sort_order, id')->fetchAll(PDO::FETCH_COLUMN);
        $pos  = array_search($id, array_map('intval', $rows), true);
        $swap = $pos + $dir;
        if ($pos !== false && isset($rows[$swap])) {
            [$rows[$pos], $rows[$swap]] = [$rows[$swap], $rows[$pos]];
            $st = db()->prepare('UPDATE packages SET sort_order = ? WHERE id = ?');
            foreach ($rows as $i => $rid) {
                $st->execute([$i + 1, $rid]);
            }
        }
        redirect('paket.php');
    }

    if ($action === 'save') {
        $form = [
            'id'           => $id,
            'tier'         => trim((string) ($_POST['tier'] ?? '')),
            'name'         => trim((string) ($_POST['name'] ?? '')),
            'slug'         => trim(strtolower((string) ($_POST['slug'] ?? ''))),
            'description'  => trim((string) ($_POST['description'] ?? '')),
            'normal_price' => (int) preg_replace('/\D/', '', (string) ($_POST['normal_price'] ?? '')),
            'price'        => (int) preg_replace('/\D/', '', (string) ($_POST['price'] ?? '')),
            'price_note'   => trim((string) ($_POST['price_note'] ?? '')) ?: 'Tahun pertama',
            'renewal_price'=> (int) preg_replace('/\D/', '', (string) ($_POST['renewal_price'] ?? '')),
            'is_featured'  => isset($_POST['is_featured']) ? 1 : 0,
            'is_active'    => isset($_POST['is_active']) ? 1 : 0,
            'features_raw' => (string) ($_POST['features'] ?? ''),
        ];
        $features = array_values(array_filter(array_map('trim', preg_split('/\R/', $form['features_raw']))));
        if ($form['slug'] === '') {
            $form['slug'] = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($form['name'])), '-');
        }

        if ($form['name'] === '')                       $errors['name'] = 'Isi nama paket.';
        if ($form['tier'] === '')                       $errors['tier'] = 'Isi label tingkatan, mis. "Menengah".';
        if (!preg_match('/^[a-z0-9-]{2,40}$/', $form['slug'])) $errors['slug'] = 'Gunakan huruf kecil, angka, dan tanda minus (2–40 karakter).';
        if ($form['price'] <= 0)                        $errors['price'] = 'Isi harga jual.';
        if ($form['normal_price'] === 0)                $form['normal_price'] = $form['price'];   // kosong = tanpa diskon
        if ($form['normal_price'] < $form['price'])     $errors['normal_price'] = 'Harga normal harus sama atau lebih besar dari harga jual.';
        if (!$features)                                 $errors['features'] = 'Tulis minimal satu fitur.';

        $dup = db()->prepare('SELECT COUNT(*) FROM packages WHERE slug = ? AND id <> ?');
        $dup->execute([$form['slug'], $id]);
        if ($dup->fetchColumn()) $errors['slug'] = 'Kode ini sudah dipakai paket lain.';

        if (!$errors) {
            $pdo = db();
            $pdo->beginTransaction();
            try {
                $data = [$form['slug'], $form['tier'], $form['name'], $form['description'], $form['normal_price'],
                         $form['price'], $form['price_note'], $form['renewal_price'] ?: null, $form['is_featured'], $form['is_active']];
                if ($id > 0) {
                    $pdo->prepare('UPDATE packages SET slug=?, tier=?, name=?, description=?, normal_price=?, price=?, price_note=?, renewal_price=?, is_featured=?, is_active=? WHERE id=?')
                        ->execute([...$data, $id]);
                } else {
                    $next = (int) $pdo->query('SELECT COALESCE(MAX(sort_order),0)+1 FROM packages')->fetchColumn();
                    $pdo->prepare('INSERT INTO packages (slug, tier, name, description, normal_price, price, price_note, renewal_price, is_featured, is_active, sort_order) VALUES (?,?,?,?,?,?,?,?,?,?,?)')
                        ->execute([...$data, $next]);
                    $id = (int) $pdo->lastInsertId();
                }
                // hanya satu paket yang boleh berlabel "Paling laris"
                if ($form['is_featured']) {
                    $pdo->prepare('UPDATE packages SET is_featured = 0 WHERE id <> ?')->execute([$id]);
                }
                $pdo->prepare('DELETE FROM package_features WHERE package_id = ?')->execute([$id]);
                $ins = $pdo->prepare('INSERT INTO package_features (package_id, feature, sort_order) VALUES (?, ?, ?)');
                foreach ($features as $i => $f) {
                    $ins->execute([$id, text_cut($f, 255), $i + 1]);
                }
                $pdo->commit();
            } catch (Throwable $ex) {
                $pdo->rollBack();
                throw $ex;
            }
            flash('Paket "' . $form['name'] . '" tersimpan.');
            redirect('paket.php');
        }
        $editId = $id;
    }
}

/* ---------- Data form ---------- */
if ($editId !== null && $form === null) {
    if ($editId > 0) {
        foreach (get_packages(false) as $p) {
            if ((int) $p['id'] === $editId) {
                $form = $p + ['features_raw' => implode("\n", $p['features'])];
            }
        }
        if (!$form) {
            flash('Paket tidak ditemukan.', 'err');
            redirect('paket.php');
        }
    } else {
        $form = ['id' => 0, 'tier' => '', 'name' => '', 'slug' => '', 'description' => '', 'normal_price' => '',
                 'price' => '', 'price_note' => 'Tahun pertama', 'renewal_price' => '', 'is_featured' => 0, 'is_active' => 1, 'features_raw' => ''];
    }
}

$packages = get_packages(false);
$leadCount = db()->query('SELECT package_id, COUNT(*) FROM leads WHERE package_id IS NOT NULL GROUP BY package_id')->fetchAll(PDO::FETCH_KEY_PAIR);

admin_header($form !== null ? ($form['id'] ? 'Ubah paket' : 'Tambah paket') : 'Paket harga', 'paket');

function field_error(array $errors, string $k): string
{
    return isset($errors[$k]) ? '<small class="err" id="err-' . $k . '">' . e($errors[$k]) . '</small>' : '';
}
function invalid(array $errors, string $k): string
{
    return isset($errors[$k]) ? ' aria-invalid="true" aria-describedby="err-' . $k . '"' : '';
}
?>

<?php if ($form !== null): ?>
    <form method="post" class="card editor">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" value="<?= (int) $form['id'] ?>">
        <?php if ($errors): ?><p class="flash flash--err" role="alert">Ada <?= count($errors) ?> isian yang perlu diperbaiki.</p><?php endif; ?>

        <div class="grid2">
            <div>
                <label for="name">Nama paket</label>
                <input id="name" name="name" value="<?= e($form['name']) ?>" placeholder="Perusahaan Menengah"<?= invalid($errors, 'name') ?>>
                <?= field_error($errors, 'name') ?>
            </div>
            <div>
                <label for="tier">Label tingkatan</label>
                <input id="tier" name="tier" value="<?= e($form['tier']) ?>" placeholder="Menengah"<?= invalid($errors, 'tier') ?>>
                <?= field_error($errors, 'tier') ?>
            </div>
        </div>

        <label for="description">Untuk siapa paket ini</label>
        <input id="description" name="description" value="<?= e($form['description']) ?>" maxlength="255" placeholder="Perusahaan dengan beberapa lini layanan dan klien B2B aktif.">

        <div class="grid3">
            <div>
                <label for="normal_price">Harga normal <span class="muted">(dicoret; kosongkan jika tanpa diskon)</span></label>
                <input id="normal_price" name="normal_price" inputmode="numeric" value="<?= e((string) $form['normal_price']) ?>" placeholder="3500000"<?= invalid($errors, 'normal_price') ?>>
                <?= field_error($errors, 'normal_price') ?>
            </div>
            <div>
                <label for="price">Harga jual</label>
                <input id="price" name="price" inputmode="numeric" value="<?= e((string) $form['price']) ?>" placeholder="2750000"<?= invalid($errors, 'price') ?>>
                <?= field_error($errors, 'price') ?>
            </div>
            <div>
                <label for="price_note">Keterangan harga</label>
                <input id="price_note" name="price_note" value="<?= e($form['price_note']) ?>" placeholder="Tahun pertama">
            </div>
        </div>

        <label for="renewal_price">Biaya perpanjangan per tahun <span class="muted">(opsional)</span></label>
        <input id="renewal_price" name="renewal_price" inputmode="numeric" value="<?= e((string) ($form['renewal_price'] ?? '')) ?>" placeholder="950000">

        <label for="features">Fitur paket <span class="muted">(satu fitur per baris)</span></label>
        <textarea id="features" name="features" rows="9"<?= invalid($errors, 'features') ?>><?= e($form['features_raw']) ?></textarea>
        <?= field_error($errors, 'features') ?>

        <div class="grid2">
            <div>
                <label for="slug">Kode paket <span class="muted">(kosongkan agar dibuat otomatis)</span></label>
                <input id="slug" name="slug" value="<?= e($form['slug']) ?>" placeholder="menengah"<?= invalid($errors, 'slug') ?>>
                <?= field_error($errors, 'slug') ?>
            </div>
            <div class="checks">
                <label class="check"><input type="checkbox" name="is_featured" <?= $form['is_featured'] ? 'checked' : '' ?>> Tandai "Paling laris"</label>
                <label class="check"><input type="checkbox" name="is_active" <?= $form['is_active'] ? 'checked' : '' ?>> Tampilkan di website</label>
            </div>
        </div>

        <div class="actions">
            <button class="btn">Simpan paket</button>
            <a href="paket.php" class="link">Batal</a>
        </div>
    </form>

<?php else: ?>
    <p class="toolbar"><a class="btn" href="?edit=0">Tambah paket</a></p>

    <?php if (!$packages): ?>
        <div class="empty card"><p><strong>Belum ada paket.</strong></p><p>Tambahkan paket pertama agar muncul di halaman harga.</p></div>
    <?php else: ?>
    <div class="table card">
        <table>
            <thead>
                <tr><th>Urutan</th><th>Paket</th><th>Harga</th><th>Fitur</th><th>Peminat</th><th>Tampil</th><th><span class="sr">Aksi</span></th></tr>
            </thead>
            <tbody>
            <?php foreach ($packages as $i => $p): ?>
                <tr class="<?= $p['is_active'] ? '' : 'is-off' ?>">
                    <td class="order">
                        <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="move"><input type="hidden" name="id" value="<?= $p['id'] ?>">
                            <button name="dir" value="up" class="iconbtn" <?= $i === 0 ? 'disabled' : '' ?> aria-label="Naikkan <?= e($p['name']) ?>">▲</button>
                            <button name="dir" value="down" class="iconbtn" <?= $i === count($packages) - 1 ? 'disabled' : '' ?> aria-label="Turunkan <?= e($p['name']) ?>">▼</button>
                        </form>
                    </td>
                    <td>
                        <strong><?= e($p['name']) ?></strong>
                        <?php if ($p['is_featured']): ?><span class="badge badge--deal">Paling laris</span><?php endif; ?>
                        <br><span class="muted"><?= e($p['tier']) ?></span>
                    </td>
                    <td><?php if ($p['normal_price'] > $p['price']): ?><s class="muted"><?= e(rupiah($p['normal_price'])) ?></s><br><?php endif; ?><strong><?= e(rupiah($p['price'])) ?></strong><?php if ($p['renewal_price']): ?><br><span class="muted">+<?= e(rupiah($p['renewal_price'])) ?>/thn</span><?php endif; ?></td>
                    <td><?= count($p['features']) ?> fitur</td>
                    <td><?= (int) ($leadCount[$p['id']] ?? 0) ?></td>
                    <td>
                        <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= $p['id'] ?>">
                            <button class="switch" role="switch" aria-checked="<?= $p['is_active'] ? 'true' : 'false' ?>" aria-label="Tampilkan <?= e($p['name']) ?> di website"><span></span></button>
                        </form>
                    </td>
                    <td class="rowact">
                        <a href="?edit=<?= $p['id'] ?>" class="link">Ubah</a>
                        <form method="post" onsubmit="return confirm('Hapus paket <?= e($p['name']) ?>?')"><?= csrf_field() ?>
                            <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $p['id'] ?>">
                            <button class="link link--danger">Hapus</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
<?php endif; ?>

<?php admin_footer(); ?>

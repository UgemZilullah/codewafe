<?php
declare(strict_types=1);
require __DIR__ . '/_init.php';
require_login();
guard_post();

$editId = isset($_GET['edit']) ? (int) $_GET['edit'] : null;
$errors = [];
$form   = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id     = (int) ($_POST['id'] ?? 0);

    if ($action === 'toggle') {
        db()->prepare('UPDATE faqs SET is_active = 1 - is_active WHERE id = ?')->execute([$id]);
        redirect('faq.php');
    }
    if ($action === 'delete') {
        db()->prepare('DELETE FROM faqs WHERE id = ?')->execute([$id]);
        flash('Pertanyaan dihapus.');
        redirect('faq.php');
    }
    if ($action === 'move') {
        $dir  = ($_POST['dir'] ?? '') === 'up' ? -1 : 1;
        $rows = array_map('intval', db()->query('SELECT id FROM faqs ORDER BY sort_order, id')->fetchAll(PDO::FETCH_COLUMN));
        $pos  = array_search($id, $rows, true);
        if ($pos !== false && isset($rows[$pos + $dir])) {
            [$rows[$pos], $rows[$pos + $dir]] = [$rows[$pos + $dir], $rows[$pos]];
            $st = db()->prepare('UPDATE faqs SET sort_order = ? WHERE id = ?');
            foreach ($rows as $i => $rid) {
                $st->execute([$i + 1, $rid]);
            }
        }
        redirect('faq.php');
    }
    if ($action === 'save') {
        $form = [
            'id'        => $id,
            'question'  => trim((string) ($_POST['question'] ?? '')),
            'answer'    => trim((string) ($_POST['answer'] ?? '')),
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
        ];
        if (text_len($form['question']) < 5) $errors['question'] = 'Tulis pertanyaannya, minimal 5 karakter.';
        if (text_len($form['answer']) < 5)   $errors['answer']   = 'Tulis jawabannya.';

        if (!$errors) {
            if ($id > 0) {
                db()->prepare('UPDATE faqs SET question = ?, answer = ?, is_active = ? WHERE id = ?')
                    ->execute([text_cut($form['question'], 255), $form['answer'], $form['is_active'], $id]);
            } else {
                $next = (int) db()->query('SELECT COALESCE(MAX(sort_order),0)+1 FROM faqs')->fetchColumn();
                db()->prepare('INSERT INTO faqs (question, answer, is_active, sort_order) VALUES (?, ?, ?, ?)')
                    ->execute([text_cut($form['question'], 255), $form['answer'], $form['is_active'], $next]);
            }
            flash('Pertanyaan tersimpan.');
            redirect('faq.php');
        }
        $editId = $id;
    }
}

if ($editId !== null && $form === null) {
    $form = ['id' => 0, 'question' => '', 'answer' => '', 'is_active' => 1];
    if ($editId > 0) {
        $st = db()->prepare('SELECT * FROM faqs WHERE id = ?');
        $st->execute([$editId]);
        $form = $st->fetch() ?: null;
        if (!$form) {
            flash('Pertanyaan tidak ditemukan.', 'err');
            redirect('faq.php');
        }
    }
}

$faqs = get_faqs(false);
admin_header($form !== null ? ($form['id'] ? 'Ubah pertanyaan' : 'Tambah pertanyaan') : 'FAQ', 'faq');
?>

<?php if ($form !== null): ?>
    <form method="post" class="card editor">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" value="<?= (int) $form['id'] ?>">

        <label for="question">Pertanyaan</label>
        <input id="question" name="question" maxlength="255" value="<?= e($form['question']) ?>" <?= isset($errors['question']) ? 'aria-invalid="true" aria-describedby="err-q"' : '' ?>>
        <?php if (isset($errors['question'])): ?><small class="err" id="err-q"><?= e($errors['question']) ?></small><?php endif; ?>

        <label for="answer">Jawaban</label>
        <textarea id="answer" name="answer" rows="5" <?= isset($errors['answer']) ? 'aria-invalid="true" aria-describedby="err-a"' : '' ?>><?= e($form['answer']) ?></textarea>
        <?php if (isset($errors['answer'])): ?><small class="err" id="err-a"><?= e($errors['answer']) ?></small><?php endif; ?>

        <label class="check"><input type="checkbox" name="is_active" <?= $form['is_active'] ? 'checked' : '' ?>> Tampilkan di website</label>

        <div class="actions">
            <button class="btn">Simpan pertanyaan</button>
            <a href="faq.php" class="link">Batal</a>
        </div>
    </form>
<?php else: ?>
    <p class="toolbar"><a class="btn" href="?edit=0">Tambah pertanyaan</a></p>
    <?php if (!$faqs): ?>
        <div class="empty card"><p><strong>Belum ada pertanyaan.</strong></p><p>Tambahkan pertanyaan yang sering ditanyakan calon klien.</p></div>
    <?php else: ?>
    <div class="table card">
        <table>
            <thead><tr><th>Urutan</th><th>Pertanyaan & jawaban</th><th>Tampil</th><th><span class="sr">Aksi</span></th></tr></thead>
            <tbody>
            <?php foreach ($faqs as $i => $f): ?>
                <tr class="<?= $f['is_active'] ? '' : 'is-off' ?>">
                    <td class="order">
                        <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="move"><input type="hidden" name="id" value="<?= $f['id'] ?>">
                            <button name="dir" value="up" class="iconbtn" <?= $i === 0 ? 'disabled' : '' ?> aria-label="Naikkan">▲</button>
                            <button name="dir" value="down" class="iconbtn" <?= $i === count($faqs) - 1 ? 'disabled' : '' ?> aria-label="Turunkan">▼</button>
                        </form>
                    </td>
                    <td><strong><?= e($f['question']) ?></strong><br><span class="muted"><?= e(text_cut($f['answer'], 140)) ?><?= text_len($f['answer']) > 140 ? '…' : '' ?></span></td>
                    <td>
                        <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= $f['id'] ?>">
                            <button class="switch" role="switch" aria-checked="<?= $f['is_active'] ? 'true' : 'false' ?>" aria-label="Tampilkan pertanyaan ini"><span></span></button>
                        </form>
                    </td>
                    <td class="rowact">
                        <a href="?edit=<?= $f['id'] ?>" class="link">Ubah</a>
                        <form method="post" onsubmit="return confirm('Hapus pertanyaan ini?')"><?= csrf_field() ?>
                            <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $f['id'] ?>">
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

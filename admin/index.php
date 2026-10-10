<?php
declare(strict_types=1);
require __DIR__ . '/_init.php';
require_login();
guard_post();

$statuses = ['baru' => 'Baru', 'dihubungi' => 'Dihubungi', 'deal' => 'Deal', 'batal' => 'Batal'];

/* ---------- Aksi ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int) ($_POST['id'] ?? 0);
    if (($_POST['action'] ?? '') === 'status' && isset($statuses[$_POST['status'] ?? ''])) {
        $note = trim((string) ($_POST['note'] ?? ''));
        db()->prepare('UPDATE leads SET status = ?, note = ? WHERE id = ?')
            ->execute([$_POST['status'], $note === '' ? null : text_cut($note, 255), $id]);
        flash('Pesan #CW' . str_pad((string) $id, 4, '0', STR_PAD_LEFT) . ' diperbarui.');
    } elseif (($_POST['action'] ?? '') === 'delete') {
        db()->prepare('DELETE FROM leads WHERE id = ?')->execute([$id]);
        flash('Pesan dihapus.');
    }
    redirect('index.php?' . http_build_query(array_filter(['status' => $_GET['status'] ?? '', 'q' => $_GET['q'] ?? ''])));
}

/* ---------- Statistik ---------- */
$counts = array_fill_keys(array_keys($statuses), 0);
foreach (db()->query('SELECT status, COUNT(*) n FROM leads GROUP BY status') as $r) {
    $counts[$r['status']] = (int) $r['n'];
}
$total = array_sum($counts);
$week  = (int) db()->query('SELECT COUNT(*) FROM leads WHERE created_at >= NOW() - INTERVAL 7 DAY')->fetchColumn();
$dealValue = (int) db()->query("SELECT COALESCE(SUM(package_price),0) FROM leads WHERE status = 'deal'")->fetchColumn();
$popular = db()->query('SELECT package_name AS name, COUNT(*) n FROM leads WHERE package_name IS NOT NULL GROUP BY package_name ORDER BY n DESC LIMIT 1')->fetch();

/* ---------- Daftar ---------- */
$filter = (string) ($_GET['status'] ?? '');
$q      = trim((string) ($_GET['q'] ?? ''));
$where  = [];
$params = [];
if (isset($statuses[$filter])) {
    $where[] = 'l.status = ?';
    $params[] = $filter;
}
if ($q !== '') {
    $where[] = '(l.name LIKE ? OR l.business LIKE ? OR l.message LIKE ?)';
    array_push($params, "%$q%", "%$q%", "%$q%");
}
$sql = 'SELECT l.* FROM leads l'
     . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
     . ' ORDER BY l.created_at DESC, l.id DESC LIMIT 200';
$st = db()->prepare($sql);
$st->execute($params);
$leads = $st->fetchAll();

admin_header('Pesan masuk', 'index');
?>

<section class="stats">
    <div><span>Total pesan</span><strong><?= $total ?></strong></div>
    <div><span>7 hari terakhir</span><strong><?= $week ?></strong></div>
    <div><span>Belum dihubungi</span><strong class="<?= $counts['baru'] ? 'hot' : '' ?>"><?= $counts['baru'] ?></strong></div>
    <div><span>Nilai deal</span><strong><?= e(rupiah($dealValue)) ?></strong></div>
    <div><span>Paket paling diminati</span><strong class="sm"><?= e($popular['name'] ?? 'Belum ada') ?></strong></div>
</section>

<form class="filters" method="get">
    <nav class="tabs" aria-label="Filter status">
        <a href="?" <?= $filter === '' ? 'aria-current="true"' : '' ?>>Semua <small><?= $total ?></small></a>
        <?php foreach ($statuses as $k => $label): ?>
            <a href="?status=<?= $k ?>" <?= $filter === $k ? 'aria-current="true"' : '' ?>><?= $label ?> <small><?= $counts[$k] ?></small></a>
        <?php endforeach; ?>
    </nav>
    <?php if ($filter): ?><input type="hidden" name="status" value="<?= e($filter) ?>"><?php endif; ?>
    <input type="search" name="q" value="<?= e($q) ?>" placeholder="Cari nama, usaha, atau isi pesan" aria-label="Cari pesan">
</form>

<?php if (!$leads): ?>
    <div class="empty card">
        <p><strong><?= $q || $filter ? 'Tidak ada pesan yang cocok.' : 'Belum ada pesan masuk.' ?></strong></p>
        <p><?= $q || $filter ? 'Ubah kata kunci atau pilih status lain.' : 'Pesan dari form konsultasi di website akan muncul di sini.' ?></p>
    </div>
<?php else: ?>
    <div class="leads">
        <?php foreach ($leads as $l):
            $code = '#CW' . str_pad((string) $l['id'], 4, '0', STR_PAD_LEFT);
        ?>
        <article class="lead card status-<?= e($l['status']) ?>">
            <header class="lead__head">
                <div>
                    <h2><?= e($l['name']) ?> <span class="muted"><?= e($l['business']) ?></span></h2>
                    <p class="muted"><?= e($code) ?>, <?= e(date('d M Y H.i', strtotime($l['created_at']))) ?></p>
                </div>
                <span class="badge badge--<?= e($l['status']) ?>"><?= e($statuses[$l['status']]) ?></span>
            </header>

            <p class="lead__pkg">
                <?= $l['package_name'] ? e($l['package_name']) . ' <b>' . e(rupiah($l['package_price'])) . '</b>' : '<span class="muted">Belum memilih paket</span>' ?>
            </p>
            <?php if (!empty($l['budget'])): ?><p class="lead__budget">Estimasi budget: <b><?= e($l['budget']) ?></b></p><?php endif; ?>
            <?php if ($l['message']): ?><p class="lead__msg"><?= nl2br(e($l['message'])) ?></p><?php endif; ?>

            <form method="post" class="lead__form">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int) $l['id'] ?>">
                <input type="hidden" name="action" value="status">
                <label class="sr" for="s<?= $l['id'] ?>">Status</label>
                <select id="s<?= $l['id'] ?>" name="status">
                    <?php foreach ($statuses as $k => $label): ?>
                        <option value="<?= $k ?>" <?= $l['status'] === $k ? 'selected' : '' ?>><?= $label ?></option>
                    <?php endforeach; ?>
                </select>
                <label class="sr" for="n<?= $l['id'] ?>">Catatan</label>
                <input id="n<?= $l['id'] ?>" name="note" value="<?= e($l['note']) ?>" placeholder="Catatan internal, mis. follow up Senin">
                <button class="btn btn--sm">Simpan</button>
            </form>
            <footer class="lead__foot">
                <span class="muted">Balas pelanggan lewat WhatsApp dari nomor yang mereka pakai saat chat masuk.</span>
                <form method="post" onsubmit="return confirm('Hapus pesan <?= e($code) ?>? Tindakan ini tidak bisa dibatalkan.')">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int) $l['id'] ?>">
                    <input type="hidden" name="action" value="delete">
                    <button class="link link--danger">Hapus</button>
                </form>
            </footer>
        </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php admin_footer(); ?>

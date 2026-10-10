<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

$site     = settings();
$packages = get_packages();
$services = get_services();
$reasons  = get_reasons();
$steps    = get_steps();
$faqs     = get_faqs();
$budgets  = budget_options();

/* ---------- Form konsultasi: validasi, simpan ke database, lalu ke WhatsApp ---------- */
$errors = [];
$old = ['nama' => '', 'bisnis' => '', 'paket' => (string) ($_GET['paket'] ?? ''), 'budget' => '', 'pesan' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old = [
        'nama'   => trim((string) ($_POST['nama'] ?? '')),
        'bisnis' => trim((string) ($_POST['bisnis'] ?? '')),
        'paket'  => trim((string) ($_POST['paket'] ?? '')),
        'budget' => trim((string) ($_POST['budget'] ?? '')),
        'pesan'  => trim((string) ($_POST['pesan'] ?? '')),
    ];

    $selected = null;
    foreach ($packages as $p) {
        if ($p['slug'] === $old['paket']) {
            $selected = $p;
        }
    }

    if (!csrf_ok()) {
        $errors['form'] = 'Sesi formulir kedaluwarsa. Muat ulang halaman lalu kirim lagi.';
    } elseif (trim((string) ($_POST['website'] ?? '')) !== '') {
        $errors['form'] = 'Formulir tidak dapat diproses.';           // honeypot terisi = bot
    } elseif (too_many_leads(client_ip())) {
        $errors['form'] = 'Terlalu banyak kiriman dari perangkat ini. Coba lagi 10 menit lagi, atau hubungi kami langsung lewat WhatsApp.';
    }
    if (text_len($old['nama']) < 2 || text_len($old['nama']) > 100) {
        $errors['nama'] = 'Tulis nama Anda, 2–100 huruf.';
    }
    if (text_len($old['bisnis']) < 2 || text_len($old['bisnis']) > 150) {
        $errors['bisnis'] = 'Tulis nama usaha atau perusahaan Anda.';
    }
    if (!isset($budgets[$old['budget']])) {
        $errors['budget'] = 'Pilih estimasi budget, atau "Belum ada patokan".';
    }
    if ($selected === null && $old['paket'] !== 'belum') {
        $errors['paket'] = 'Pilih salah satu paket, atau "Belum tahu".';
    }
    if (text_len($old['pesan']) > 600) {
        $errors['pesan'] = 'Pesan maksimal 600 karakter.';
    }

    if (!$errors) {
        $budgetLabel = $budgets[$old['budget']];
        $st = db()->prepare('INSERT INTO leads (name, business, package_id, package_name, package_price, budget, message, ip_address) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        $st->execute([$old['nama'], $old['bisnis'], $selected['id'] ?? null, $selected['name'] ?? null, $selected['price'] ?? null, $budgetLabel, $old['pesan'] ?: null, client_ip()]);
        $leadId = (int) db()->lastInsertId();

        $paketName = $selected ? $selected['name'] . ' (' . rupiah($selected['price']) . ')' : 'Belum tahu, mohon dibantu pilih';
        $text = "Halo CodeWave, saya ingin konsultasi pembuatan website.\n\n"
            . 'No. konsultasi: #CW' . str_pad((string) $leadId, 4, '0', STR_PAD_LEFT) . "\n"
            . "Nama: {$old['nama']}\n"
            . "Usaha: {$old['bisnis']}\n"
            . "Paket: {$paketName}\n"
            . "Estimasi budget: {$budgetLabel}\n"
            . ($old['pesan'] !== '' ? "Catatan: {$old['pesan']}\n" : '');
        header('Location: ' . wa_link($site['wa'], $text), true, 303);
        exit;
    }
}

$priceMin  = $packages ? min(array_map('intval', array_column($packages, 'price'))) : 0;
$savingMax = $packages ? max(array_map(fn($p) => (int) $p['normal_price'] - (int) $p['price'], $packages)) : 0;
$renewals  = array_filter(array_map(fn($p) => (int) ($p['renewal_price'] ?? 0), $packages));
$renewMin  = $renewals ? min($renewals) : 0;

/* Fitur yang sama di semua paket ditampilkan sebagai "Termasuk juga",
   fitur pembeda ditonjolkan di atasnya. Otomatis menyesuaikan saat paket diedit di admin. */
$commonFeatures = [];
if (count($packages) > 1) {
    $commonFeatures = $packages[0]['features'];
    foreach ($packages as $p) {
        $commonFeatures = array_values(array_intersect($commonFeatures, $p['features']));
    }
}
$waStart   = wa_link($site['wa'], 'Halo CodeWave, saya mau mulai project website baru.');
$waAsk     = wa_link($site['wa'], 'Halo CodeWave, saya mau tanya-tanya dulu soal pembuatan website.');
$socials   = array_filter([
    'Instagram' => $site['instagram'] ?? '',
    'Facebook'  => $site['facebook'] ?? '',
    'TikTok'    => $site['tiktok'] ?? '',
]);

/** Tombol dengan efek teks bergulir saat hover (ATM dari Ricksite). */
function roll(string $label): string
{
    return '<span class="roll" data-text="' . e($label) . '"><span>' . e($label) . '</span></span>';
}

$waIcon = '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M12.04 2a9.9 9.9 0 0 0-8.5 15l-1.4 5.1 5.2-1.4A9.9 9.9 0 1 0 12.04 2Zm0 18.1a8.2 8.2 0 0 1-4.2-1.2l-.3-.2-3.1.8.8-3-.2-.3a8.2 8.2 0 1 1 7 3.9Zm4.5-6.1c-.2-.1-1.5-.7-1.7-.8-.2-.1-.4-.1-.6.1l-.8 1c-.1.2-.3.2-.5.1a6.7 6.7 0 0 1-3.3-2.9c-.2-.4.2-.4.7-1.3.1-.2 0-.3 0-.4l-.8-1.8c-.2-.5-.4-.4-.6-.4h-.5a1 1 0 0 0-.7.3 3 3 0 0 0-.9 2.2 5.2 5.2 0 0 0 1.1 2.7 11.8 11.8 0 0 0 4.5 4c1.7.7 2.3.8 3.2.6a2.7 2.7 0 0 0 1.7-1.2 2.2 2.2 0 0 0 .2-1.2c-.1-.1-.3-.2-.5-.3Z"/></svg>';
$waveMark = '<svg class="wavemark" viewBox="0 0 40 12" aria-hidden="true"><path d="M1 6c4-5 8-5 12 0s8 5 12 0 8-5 12 0" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"/></svg>';
$reasonIcons = [
    '<path d="M12 3v3M12 18v3M3 12h3M18 12h3"/><circle cx="12" cy="12" r="5"/><circle cx="12" cy="12" r="1.5" fill="currentColor"/>',
    '<path d="M4 7h16v12H4z"/><path d="M8 7V5h8v2M4 12h16"/>',
    '<path d="M4 18l2-4a8 8 0 1 1 3 3z"/><path d="M9 10h6M9 13h4"/>',
    '<path d="M12 3l7 3v6c0 4-3 7-7 9-4-2-7-5-7-9V6z"/><path d="M9 12l2 2 4-4"/>',
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($site['site_name']) ?> — Jasa Pembuatan Website Perusahaan & UMKM</title>
    <meta name="description" content="CodeWave membuat website company profile, landing page, dan website UMKM yang langsung terhubung ke WhatsApp. Paket mulai <?= e(rupiah($priceMin)) ?>, sudah termasuk domain dan hosting.">
    <meta property="og:title" content="<?= e($site['site_name']) ?> — <?= e($site['tagline']) ?>">
    <meta property="og:description" content="Website perusahaan & UMKM yang rapi, cepat di HP, dan langsung terhubung ke WhatsApp. Mulai <?= e(rupiah($priceMin)) ?>.">
    <meta property="og:type" content="website">
    <meta property="og:locale" content="id_ID">
    <meta name="theme-color" content="#0b2a3c">
    <link rel="icon" type="image/png" href="assets/img/icon-codewave.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,500;12..96,700;12..96,800&family=Figtree:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <script>document.documentElement.classList.add('js');</script>
</head>
<body>

<a class="skip" href="#paket">Lewati ke daftar paket</a>

<header class="topbar" id="top">
    <div class="wrap topbar__in">
        <a href="#top" class="brand" aria-label="CodeWave, kembali ke atas">
            <img src="assets/img/logo-codewave.png" alt="CodeWave — Arus Digital untuk Skala Bisnis Anda" width="204" height="48">
        </a>
        <button class="navtoggle" aria-expanded="false" aria-controls="nav">
            <span class="sr">Buka menu</span><span></span><span></span>
        </button>
        <nav id="nav" class="nav">
            <a href="#layanan">Layanan</a>
            <a href="#paket">Paket harga</a>
            <a href="#alur">Cara kerja</a>
            <a href="#tentang">Tentang</a>
            <a href="#faq">FAQ</a>
            <a class="btn btn--small" href="#konsultasi"><?= roll('Konsultasi gratis') ?></a>
        </nav>
    </div>
</header>

<main>

    <!-- HERO -->
    <section class="hero">
        <div class="hero__grid" aria-hidden="true"></div>
        <div class="wrap hero__in">
            <div class="hero__copy">
                <div class="hero__badge">
                    <span class="badge-dot"></span> CS online: <?= e(format_phone($site['wa'])) ?>
                </div>
                <p class="hero__kicker">Landing page <i></i> Company profile <i></i> Corporate &amp; toko online</p>
                <h1>Bisnis Anda sedang tumbuh. Websitenya harus ikut mengalir.</h1>
                <p class="hero__lead">
                    Kami bangun website perusahaan dan UMKM yang rapi, cepat dibuka di HP, dan langsung
                    terhubung ke WhatsApp Anda. Satu tujuan: calon klien menemukan bisnis Anda, percaya, lalu chat.
                </p>
                <div class="hero__cta">
                    <a class="btn btn--wave" href="<?= e($waStart) ?>" target="_blank" rel="noopener"><?= roll('Mulai project sekarang') ?></a>
                    <a class="btn btn--line" href="#paket"><?= roll('Lihat paket harga') ?></a>
                </div>
                <ul class="hero__chips" aria-label="Layanan CodeWave">
                    <?php foreach ($services as $s): ?>
                        <li><a href="#layanan"><?= e($s['title']) ?></a></li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <div class="hero__art" aria-hidden="true">
                <svg viewBox="0 0 520 440" class="swell">
                    <defs>
                        <linearGradient id="g1" x1="0" y1="0" x2="1" y2="1">
                            <stop offset="0" stop-color="#22c3b5"/>
                            <stop offset="1" stop-color="#3b8cff"/>
                        </linearGradient>
                    </defs>
                    <g class="swell__code" fill="none" stroke="url(#g1)" stroke-width="14" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M190 120 L140 175 L190 230"/>
                        <path d="M330 120 L380 175 L330 230"/>
                        <path d="M285 105 L235 245"/>
                    </g>
                    <g class="swell__lines" fill="none" stroke-linecap="round">
                        <path class="l1" stroke="url(#g1)" stroke-width="10" d="M-40 320 C 60 270, 140 370, 260 320 S 460 270, 580 320"/>
                        <path class="l2" stroke="#22c3b5" stroke-width="6" opacity=".6" d="M-40 360 C 60 310, 140 410, 260 360 S 460 310, 580 360"/>
                        <path class="l3" stroke="#3b8cff" stroke-width="4" opacity=".4" d="M-40 398 C 60 348, 140 448, 260 398 S 460 348, 580 398"/>
                    </g>
                </svg>
                <div class="hero__ticket">
                    <span>Paket mulai</span>
                    <strong><?= e(rupiah($priceMin)) ?></strong>
                    <small>Domain + hosting termasuk</small>
                </div>
            </div>
        </div>

        <div class="wrap">
            <dl class="hero__facts">
                <div><dt>Mulai</dt><dd><?= e(rupiah($priceMin)) ?></dd></div>
                <div><dt>Selesai</dt><dd>7–14 hari kerja</dd></div>
                <?php if ($savingMax > 0): ?>
                    <div><dt>Hemat hingga</dt><dd><?= e(rupiah($savingMax)) ?></dd></div>
                <?php else: ?>
                    <div><dt>Perpanjangan mulai</dt><dd><?= e(rupiah($renewMin)) ?>/thn</dd></div>
                <?php endif; ?>
                <div><dt>Konsultasi</dt><dd>Gratis</dd></div>
            </dl>
        </div>
    </section>

    <!-- MARQUEE -->
    <div class="marquee" aria-hidden="true">
        <div class="marquee__track">
            <?php for ($r = 0; $r < 2; $r++): ?>
                <div class="marquee__group">
                    <?php foreach ($services as $s): ?>
                        <span><?= e($s['title']) ?></span><?= $waveMark ?>
                    <?php endforeach; ?>
                    <span>Domain & hosting termasuk</span><?= $waveMark ?>
                    <span>Terhubung ke WhatsApp</span><?= $waveMark ?>
                </div>
            <?php endfor; ?>
        </div>
    </div>

    <!-- LAYANAN -->
    <section class="section" id="layanan">
        <div class="wrap">
            <header class="head head--split">
                <div>
                    <p class="label"><?= $waveMark ?> Yang kami kerjakan</p>
                    <h2>Layanan yang membantu bisnis Anda tumbuh</h2>
                </div>
                <p>Fokus kami satu: website yang membuat calon pelanggan percaya, lalu menghubungi Anda.</p>
            </header>

            <ol class="svc">
                <?php foreach ($services as $i => $s): ?>
                    <li class="svc__row reveal">
                        <span class="svc__no"><?= str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) ?></span>
                        <div class="svc__title">
                            <h3><?= e($s['title']) ?></h3>
                            <?php if (!empty($s['price_label'])): ?><p class="svc__price"><?= e($s['price_label']) ?></p><?php endif; ?>
                        </div>
                        <p class="svc__desc"><?= e($s['description']) ?></p>
                        <a class="svc__link" href="<?= e(wa_link($site['wa'], 'Halo CodeWave, saya tertarik dengan layanan ' . $s['title'] . '. Boleh dijelaskan?')) ?>" target="_blank" rel="noopener" aria-label="Tanya soal <?= e($s['title']) ?> lewat WhatsApp">
                            <?= roll('Tanya layanan ini') ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ol>
        </div>
    </section>

    <!-- PAKET -->
    <section class="section section--pricing" id="paket">
        <div class="pricing__glow" aria-hidden="true"></div>
        <div class="wrap">
            <header class="head head--center">
                <p class="label"><?= $waveMark ?> Paket harga</p>
                <h2>Pilih paket, kami yang urus sisanya</h2>
                <p>Domain, hosting, SSL, dan desain premium sudah termasuk. Harga sudah final untuk tahun pertama.</p>
            </header>

            <div class="plans">
                <?php foreach ($packages as $p):
                    $save   = (int) $p['normal_price'] - (int) $p['price'];
                    $unique = array_values(array_filter($p['features'], fn($f) => !in_array($f, $commonFeatures, true)));
                    $msg    = "Halo CodeWave, saya tertarik dengan paket {$p['name']} (" . rupiah($p['price']) . '). Boleh dijelaskan lebih lanjut?';
                ?>
                <article class="plan reveal<?= $p['is_featured'] ? ' plan--featured' : '' ?>">
                    <?php if ($p['is_featured']): ?><span class="plan__badge">Paling laris</span><?php endif; ?>
                    <p class="plan__tier"><?= e($p['tier']) ?></p>
                    <h3 class="plan__name"><?= e($p['name']) ?></h3>
                    <p class="plan__desc"><?= e($p['description']) ?></p>

                    <div class="plan__price">
                        <?php if ($save > 0): ?><s><?= e(rupiah($p['normal_price'])) ?></s><?php endif; ?>
                        <strong><small>Rp</small><?= e(number_format((int) $p['price'], 0, ',', '.')) ?></strong>
                        <span><?= e($p['price_note']) ?><?php if ($save > 0): ?> <em>hemat <?= e(rupiah($save)) ?></em><?php endif; ?></span>
                        <?php if (!empty($p['renewal_price'])): ?>
                            <p class="plan__renew">Perpanjangan <?= e(rupiah($p['renewal_price'])) ?>/tahun</p>
                        <?php endif; ?>
                    </div>

                    <?php if ($unique): ?>
                        <ul class="plan__key">
                            <?php foreach ($unique as $f): ?><li><?= e($f) ?></li><?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                    <?php if ($commonFeatures): ?>
                        <p class="plan__inc">Termasuk juga</p>
                        <ul class="plan__list">
                            <?php foreach ($commonFeatures as $f): ?><li><?= e($f) ?></li><?php endforeach; ?>
                        </ul>
                    <?php endif; ?>

                    <div class="plan__cta">
                        <a class="btn <?= $p['is_featured'] ? 'btn--wave' : 'btn--dark' ?>" href="?paket=<?= e(rawurlencode($p['slug'])) ?>#konsultasi"><?= roll('Pilih paket ini') ?></a>
                        <a class="plan__ask" href="<?= e(wa_link($site['wa'], $msg)) ?>" target="_blank" rel="noopener"><?= $waIcon ?> Tanya dulu via WhatsApp</a>
                    </div>
                </article>
                <?php endforeach; ?>
            </div>

            <ul class="assure" aria-label="Berlaku untuk semua paket">
                <li><b>Tanpa biaya tersembunyi</b> Harga di atas sudah termasuk domain dan hosting tahun pertama.</li>
                <li><b>Website milik Anda</b> Akses cPanel diberikan, data dan domain atas nama bisnis Anda.</li>
                <li><b>Tetap didampingi</b> Panduan pengelolaan dan bantuan setelah website tayang.</li>
            </ul>

            <p class="plans__note">
                Butuh sistem booking, membership, atau fitur khusus di luar paket?
                <a href="#konsultasi">Ceritakan kebutuhan Anda</a>, kami buatkan penawaran tersendiri.
            </p>
        </div>
    </section>

    <!-- KENAPA CODEWAVE -->
    <section class="section section--deep" id="kenapa">
        <div class="wrap">
            <header class="head head--center head--light">
                <p class="label"><?= $waveMark ?> Kenapa CodeWave</p>
                <h2>Website yang bekerja, bukan sekadar pajangan</h2>
                <p>Prinsipnya sederhana: website harus membantu bisnis Anda mendapat klien.</p>
            </header>
            <ul class="reasons">
                <?php foreach ($reasons as $i => $r): ?>
                    <li class="reveal">
                        <svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><?= $reasonIcons[$i % count($reasonIcons)] ?></svg>
                        <h3><?= e($r['title']) ?></h3>
                        <p><?= e($r['description']) ?></p>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </section>

    <!-- CARA KERJA -->
    <section class="section section--tint" id="alur">
        <div class="wrap">
            <header class="head head--split">
                <div>
                    <p class="label"><?= $waveMark ?> Cara kami bekerja</p>
                    <h2>Dari obrolan sampai online</h2>
                </div>
                <p>Rata-rata dua minggu. Anda selalu tahu project sedang sampai mana, dan cukup menyiapkan materi.</p>
            </header>
            <ol class="steps">
                <?php foreach ($steps as $i => $st): ?>
                    <li class="reveal">
                        <span class="steps__n"><?= $i + 1 ?></span>
                        <span class="steps__tag">Tahap-<?= str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) ?></span>
                        <h3><?= e($st['title']) ?></h3>
                        <p><?= e($st['description']) ?></p>
                        <span class="steps__time"><?= e($st['duration']) ?></span>
                    </li>
                <?php endforeach; ?>
            </ol>
        </div>
    </section>

    <!-- KONSULTASI -->
    <section class="section section--deep" id="konsultasi">
        <div class="wrap contact">
            <div class="contact__copy">
                <p class="label label--light"><?= $waveMark ?> Mulai project</p>
                <h2>Punya budget dan kebutuhan? Ceritakan saja.</h2>
                <p>Isi formulir singkat ini. Kami balas dengan rekomendasi paket dan estimasi yang paling pas, tanpa biaya untuk konsultasi awal.</p>
                <ul class="contact__meta">
                    <li class="contact__meta-item">
                        <span>WhatsApp customer service</span>
                        <a href="<?= e(wa_link($site['wa'], 'Halo CodeWave CS, saya ingin konsultasi.')) ?>" target="_blank" rel="noopener" class="contact__wa-card">
                            <span class="online-indicator"></span>
                            <strong class="contact__phone"><?= e(format_phone($site['wa'])) ?></strong>
                            <small class="contact__wa-tag">Chat langsung</small>
                        </a>
                    </li>
                    <li class="contact__meta-item">
                        <span>Email resmi</span>
                        <a href="mailto:<?= e($site['email']) ?>" class="contact__email-card">
                            <svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" fill="currentColor"><path d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/></svg>
                            <strong class="contact__email-text"><?= e($site['email']) ?></strong>
                            <small class="contact__email-tag">Kirim email</small>
                        </a>
                    </li>
                    <li><span>Lokasi</span> <?= e($site['location']) ?></li>
                    <li><span>Jam respon</span> <?= e($site['hours']) ?></li>
                </ul>
            </div>

            <form class="form" method="post" action="#konsultasi" novalidate>
                <?php if (isset($errors['form'])): ?>
                    <p class="form__alert" role="alert"><?= e($errors['form']) ?></p>
                <?php elseif ($errors): ?>
                    <p class="form__alert" role="alert">Ada <?= count($errors) ?> isian yang perlu diperbaiki.</p>
                <?php endif; ?>

                <?= csrf_field() ?>
                <div class="hp" aria-hidden="true"><label for="f-website">Website</label><input id="f-website" name="website" type="text" tabindex="-1" autocomplete="off"></div>

                <div class="form__row">
                    <div class="form__field">
                        <label for="f-nama">Nama Anda</label>
                        <input id="f-nama" name="nama" type="text" autocomplete="name" value="<?= e($old['nama']) ?>"
                               <?= isset($errors['nama']) ? 'aria-invalid="true" aria-describedby="err-nama"' : '' ?> required>
                        <?php if (isset($errors['nama'])): ?><small id="err-nama" class="err"><?= e($errors['nama']) ?></small><?php endif; ?>
                    </div>
                    <div class="form__field">
                        <label for="f-bisnis">Nama usaha / perusahaan</label>
                        <input id="f-bisnis" name="bisnis" type="text" autocomplete="organization" value="<?= e($old['bisnis']) ?>"
                               <?= isset($errors['bisnis']) ? 'aria-invalid="true" aria-describedby="err-bisnis"' : '' ?> required>
                        <?php if (isset($errors['bisnis'])): ?><small id="err-bisnis" class="err"><?= e($errors['bisnis']) ?></small><?php endif; ?>
                    </div>
                </div>

                <div class="form__row">
                    <div class="form__field">
                        <label for="f-budget">Estimasi budget</label>
                        <select id="f-budget" name="budget" <?= isset($errors['budget']) ? 'aria-invalid="true" aria-describedby="err-budget"' : '' ?>>
                            <option value="">Pilih estimasi budget</option>
                            <?php foreach ($budgets as $k => $label): ?>
                                <option value="<?= e($k) ?>" <?= $old['budget'] === $k ? 'selected' : '' ?>><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (isset($errors['budget'])): ?><small id="err-budget" class="err"><?= e($errors['budget']) ?></small><?php endif; ?>
                    </div>
                    <div class="form__field">
                        <label for="f-paket">Paket yang diminati</label>
                        <select id="f-paket" name="paket" <?= isset($errors['paket']) ? 'aria-invalid="true" aria-describedby="err-paket"' : '' ?>>
                            <option value="">Pilih paket</option>
                            <?php foreach ($packages as $p): ?>
                                <option value="<?= e($p['slug']) ?>" <?= $old['paket'] === $p['slug'] ? 'selected' : '' ?>><?= e($p['name']) ?> (<?= e(rupiah($p['price'])) ?>)</option>
                            <?php endforeach; ?>
                            <option value="belum" <?= $old['paket'] === 'belum' ? 'selected' : '' ?>>Belum tahu, mohon dibantu pilih</option>
                        </select>
                        <?php if (isset($errors['paket'])): ?><small id="err-paket" class="err"><?= e($errors['paket']) ?></small><?php endif; ?>
                    </div>
                </div>

                <div class="form__field">
                    <label for="f-pesan">Ceritakan sedikit <span class="opt">(opsional)</span></label>
                    <textarea id="f-pesan" name="pesan" rows="4" maxlength="600"
                              placeholder="Contoh: kami kontraktor, ingin menampilkan proyek dan legalitas perusahaan."><?= e($old['pesan']) ?></textarea>
                    <?php if (isset($errors['pesan'])): ?><small class="err"><?= e($errors['pesan']) ?></small><?php endif; ?>
                </div>

                <button class="btn btn--wa btn--block" type="submit"><?= $waIcon ?> <?= roll('Kirim via WhatsApp') ?></button>
                <p class="form__hint">Formulir ini membuka WhatsApp dengan pesan yang sudah terisi otomatis.</p>
            </form>
        </div>
    </section>

    <!-- TENTANG -->
    <section class="section" id="tentang">
        <div class="wrap about">
            <div class="about__copy">
                <p class="label"><?= $waveMark ?> Tentang CodeWave</p>
                <h2>Arus digital untuk skala bisnis Anda</h2>
                <p><?= e($site['about'] ?? '') ?></p>
                <p class="about__quote">Website yang bagus diukur dari hasilnya: berapa banyak calon klien yang akhirnya chat dan menjadi order.</p>
            </div>
            <dl class="about__stats">
                <div><dt>Paket mulai</dt><dd><?= e(rupiah($priceMin)) ?></dd></div>
                <div><dt>Waktu pengerjaan</dt><dd>7–14 hari</dd></div>
                <div><dt>Pilihan paket</dt><dd><?= count($packages) ?> paket</dd></div>
                <div><dt>Konsultasi awal</dt><dd>Gratis</dd></div>
            </dl>
        </div>
    </section>

    <!-- FAQ -->
    <section class="section section--tint" id="faq">
        <div class="wrap faq">
            <header class="head">
                <p class="label"><?= $waveMark ?> FAQ</p>
                <h2>Pertanyaan yang sering masuk</h2>
                <p>Belum terjawab? Tanyakan langsung lewat WhatsApp.</p>
            </header>
            <div class="faq__list">
                <?php foreach ($faqs as $f): ?>
                    <details>
                        <summary><?= e($f['question']) ?></summary>
                        <p><?= e($f['answer']) ?></p>
                    </details>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- CTA PENUTUP -->
    <section class="finale">
        <svg class="finale__waves" viewBox="0 0 1440 320" preserveAspectRatio="none" aria-hidden="true">
            <path d="M0 220 C 240 160, 480 280, 720 220 S 1200 160, 1440 220 V320 H0Z" fill="rgba(255,255,255,.07)"/>
            <path d="M0 260 C 240 200, 480 320, 720 260 S 1200 200, 1440 260 V320 H0Z" fill="rgba(255,255,255,.08)"/>
        </svg>
        <div class="wrap finale__in">
            <h2>Siap punya website yang mendatangkan klien?</h2>
            <p>Ceritakan dulu bisnis Anda dan kendalanya. Kami bantu carikan solusi yang paling masuk akal.</p>
            <div class="finale__cta">
                <a class="btn btn--light" href="#konsultasi"><?= roll('Konsultasi sekarang') ?></a>
                <a class="btn btn--ghostlight" href="<?= e($waAsk) ?>" target="_blank" rel="noopener"><?= $waIcon ?> <?= roll('Chat via WhatsApp') ?></a>
            </div>
        </div>
    </section>

</main>

<footer class="footer">
    <div class="wrap footer__top">
        <div class="footer__brand">
            <img src="assets/img/logo-codewave.png" alt="CodeWave" width="190" height="45" class="footer__logo">
            <p>CodeWave membantu bisnis mendapat lebih banyak klien lewat website yang rapi dan terhubung ke WhatsApp.</p>
        </div>
        <div>
            <h3>Layanan</h3>
            <ul>
                <?php foreach ($services as $s): ?><li><a href="#layanan"><?= e($s['title']) ?></a></li><?php endforeach; ?>
            </ul>
        </div>
        <div>
            <h3>Navigasi</h3>
            <ul>
                <li><a href="#top">Beranda</a></li>
                <li><a href="#paket">Paket harga</a></li>
                <li><a href="#alur">Cara kerja</a></li>
                <li><a href="#tentang">Tentang</a></li>
                <li><a href="#faq">FAQ</a></li>
            </ul>
        </div>
        <div>
            <h3>Kontak</h3>
            <ul class="footer__contact">
                <li><span>WhatsApp</span><a href="<?= e($waAsk) ?>" target="_blank" rel="noopener"><?= e(format_phone($site['wa'])) ?></a></li>
                <li><span>Email</span><a href="mailto:<?= e($site['email']) ?>"><?= e($site['email']) ?></a></li>
                <li><span>Lokasi</span><?= e($site['location']) ?></li>
            </ul>
            <?php if ($socials): ?>
                <p class="footer__social">
                    <?php foreach ($socials as $name => $url): ?>
                        <a href="<?= e($url) ?>" target="_blank" rel="noopener"><?= e($name) ?></a>
                    <?php endforeach; ?>
                </p>
            <?php endif; ?>
        </div>
    </div>
    <div class="wrap footer__bottom">
        <p>&copy; <?= $site['year'] ?> <?= e($site['site_name']) ?>. Semua hak dilindungi.</p>
        <p><?= e($site['tagline']) ?></p>
    </div>
</footer>

<nav class="mbar" aria-label="Aksi cepat">
    <a href="#paket" class="mbar__link">Lihat paket</a>
    <a href="<?= e($waAsk) ?>" target="_blank" rel="noopener" class="mbar__wa"><?= $waIcon ?> Chat WhatsApp</a>
</nav>

<a class="wafloat" href="<?= e(wa_link($site['wa'], 'Halo CodeWave CS, saya ingin konsultasi pembuatan website.')) ?>" target="_blank" rel="noopener" aria-label="Chat WhatsApp CodeWave">
    <span class="wafloat__tooltip"><span class="wafloat__dot"></span> CS: <?= e(format_phone($site['wa'])) ?></span>
    <?= $waIcon ?>
</a>

<script src="assets/js/main.js" defer></script>
</body>
</html>

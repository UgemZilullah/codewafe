<?php
declare(strict_types=1);

/* ================= Database ================= */

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $ex) {
            db_error_page($ex);
        }
    }
    return $pdo;
}

function db_error_page(Throwable $ex): never
{
    http_response_code(503);
    $detail = APP_DEBUG ? '<pre>' . e($ex->getMessage()) . '</pre>' : '';
    echo '<!DOCTYPE html><html lang="id"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<title>Database belum tersambung</title>'
        . '<body style="font-family:system-ui,sans-serif;max-width:560px;margin:4rem auto;padding:0 1.25rem;color:#2f3a44;line-height:1.6">'
        . '<h1 style="color:#0b2a3c">Database belum tersambung</h1>'
        . '<p>Website CodeWave tidak bisa terhubung ke MySQL. Periksa tiga hal ini:</p>'
        . '<ol><li>MySQL sudah berjalan (di XAMPP: tombol <b>Start</b> pada MySQL).</li>'
        . '<li>File <code>database/codewave.sql</code> sudah di-import lewat phpMyAdmin.</li>'
        . '<li>Nama database, user, dan password di <code>includes/config.php</code> sudah benar.</li></ol>'
        . $detail . '</body></html>';
    exit;
}

/* ================= Data situs ================= */

function settings(): array
{
    static $s = null;
    if ($s === null) {
        $s = db()->query('SELECT setting_key, setting_value FROM settings')->fetchAll(PDO::FETCH_KEY_PAIR);
        $s['year'] = (int) date('Y');
    }
    return $s;
}

function setting(string $key, string $default = ''): string
{
    return (string) (settings()[$key] ?? $default);
}

/** Paket beserta daftar fiturnya. $activeOnly=false untuk halaman admin. */
function get_packages(bool $activeOnly = true): array
{
    $where = $activeOnly ? 'WHERE is_active = 1' : '';
    $packages = db()->query("SELECT * FROM packages $where ORDER BY sort_order, id")->fetchAll();
    if (!$packages) {
        return [];
    }
    $ids = array_column($packages, 'id');
    $in  = implode(',', array_fill(0, count($ids), '?'));
    $st  = db()->prepare("SELECT package_id, feature FROM package_features WHERE package_id IN ($in) ORDER BY sort_order, id");
    $st->execute($ids);
    $features = [];
    foreach ($st as $row) {
        $features[$row['package_id']][] = $row['feature'];
    }
    foreach ($packages as &$p) {
        $p['features'] = $features[$p['id']] ?? [];
    }
    return $packages;
}

function get_services(): array
{
    return db()->query('SELECT * FROM services WHERE is_active = 1 ORDER BY sort_order, id')->fetchAll();
}

function get_reasons(): array
{
    return db()->query('SELECT * FROM reasons ORDER BY sort_order, id')->fetchAll();
}

/** Pilihan estimasi budget di form konsultasi. */
function budget_options(): array
{
    return ['lt2' => 'Di bawah Rp2 juta', '2-5' => 'Rp2–5 juta', '5-10' => 'Rp5–10 juta', 'gt10' => 'Di atas Rp10 juta', 'belum' => 'Belum ada patokan'];
}

function get_steps(): array
{
    return db()->query('SELECT * FROM steps ORDER BY sort_order, id')->fetchAll();
}

function get_faqs(bool $activeOnly = true): array
{
    $where = $activeOnly ? 'WHERE is_active = 1' : '';
    return db()->query("SELECT * FROM faqs $where ORDER BY sort_order, id")->fetchAll();
}

/* ================= Helper ================= */

function e(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

function rupiah(int|string $n): string
{
    return 'Rp ' . number_format((int) $n, 0, ',', '.');
}

function wa_link(string $number, string $text): string
{
    return 'https://wa.me/' . preg_replace('/\D/', '', $number) . '?text=' . rawurlencode($text);
}

function format_phone(string $number): string
{
    $clean = preg_replace('/\D/', '', $number);
    if (str_starts_with($clean, '62')) {
        $clean = '0' . substr($clean, 2);
    }
    if (strlen($clean) >= 10 && strlen($clean) <= 13) {
        return substr($clean, 0, 4) . '-' . substr($clean, 4, 4) . '-' . substr($clean, 8);
    }
    return $number;
}

/** Panjang teks UTF-8, aman walau ekstensi mbstring tidak aktif. */
function text_len(string $s): int
{
    return function_exists('mb_strlen') ? mb_strlen($s, 'UTF-8') : (int) preg_match_all('/./us', $s);
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e($_SESSION['csrf']) . '">';
}

function csrf_ok(): bool
{
    return hash_equals($_SESSION['csrf'] ?? '', (string) ($_POST['csrf'] ?? ''));
}

function client_ip(): string
{
    return substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
}

/** Batasi spam: maks. $max kiriman per IP dalam $minutes menit. */
function too_many_leads(string $ip, int $max = 5, int $minutes = 10): bool
{
    $st = db()->prepare('SELECT COUNT(*) FROM leads WHERE ip_address = ? AND created_at > (NOW() - INTERVAL ? MINUTE)');
    $st->execute([$ip, $minutes]);
    return (int) $st->fetchColumn() >= $max;
}

/** Potong teks UTF-8 tanpa merusak huruf. */
function text_cut(string $s, int $max): string
{
    if (function_exists('mb_substr')) {
        return mb_substr($s, 0, $max, 'UTF-8');
    }
    preg_match('/^.{0,' . $max . '}/us', $s, $m);
    return $m[0] ?? '';
}

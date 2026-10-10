-- =========================================================
--  CodeWave — struktur database + data awal
--  Kompatibel: MySQL 5.7+ / MariaDB 10.3+
--  Import lewat phpMyAdmin (tab Import) atau:
--     mysql -u root -p < database/codewave.sql
-- =========================================================

SET NAMES utf8mb4;

CREATE DATABASE IF NOT EXISTS codewave
  DEFAULT CHARACTER SET utf8mb4
  DEFAULT COLLATE utf8mb4_unicode_ci;
USE codewave;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS leads, package_features, packages, services, reasons, steps, faqs, settings, admins;
SET FOREIGN_KEY_CHECKS = 1;

-- ---------- Pengaturan situs (key-value) ----------
CREATE TABLE settings (
    setting_key   VARCHAR(50)  NOT NULL PRIMARY KEY,
    setting_value TEXT         NOT NULL,
    label         VARCHAR(100) NOT NULL,
    updated_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT INTO settings (setting_key, setting_value, label) VALUES
('site_name',  'CodeWave',                              'Nama situs'),
('tagline',    'Arus Digital untuk Skala Bisnis Anda',  'Tagline'),
('wa',         '6285111306159',                        'Nomor WhatsApp admin (format 62...)'),
('email',      'info.codewafe@gmail.com',               'Email bisnis'),
('location',   'Tangerang Selatan, Banten',             'Lokasi'),
('hours',      'Senin–Sabtu, 08.00–20.00 WIB',          'Jam respon'),
('about',      'CodeWave membantu perusahaan dan UMKM punya website yang benar-benar dipakai: dibuka calon klien, dipercaya, lalu berujung chat. Kami bekerja praktis, harga jelas di depan, dan tetap mendampingi setelah website tayang.', 'Teks bagian Tentang CodeWave'),
('instagram',  '',                                      'Link Instagram (kosongkan jika tidak ada)'),
('facebook',   '',                                      'Link Facebook (kosongkan jika tidak ada)'),
('tiktok',     '',                                      'Link TikTok (kosongkan jika tidak ada)');

-- ---------- Paket harga ----------
CREATE TABLE packages (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    slug          VARCHAR(40)  NOT NULL UNIQUE,
    tier          VARCHAR(40)  NOT NULL,
    name          VARCHAR(100) NOT NULL,
    description   VARCHAR(255) NOT NULL,
    normal_price  INT UNSIGNED NOT NULL,
    price         INT UNSIGNED NOT NULL,
    price_note    VARCHAR(50)  NOT NULL DEFAULT 'Tahun pertama',
    renewal_price INT UNSIGNED NULL COMMENT 'biaya perpanjangan per tahun',
    is_featured   TINYINT(1)   NOT NULL DEFAULT 0,
    is_active     TINYINT(1)   NOT NULL DEFAULT 1,
    sort_order    INT          NOT NULL DEFAULT 0,
    created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE package_features (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    package_id  INT UNSIGNED NOT NULL,
    feature     VARCHAR(255) NOT NULL,
    sort_order  INT          NOT NULL DEFAULT 0,
    CONSTRAINT fk_feature_package FOREIGN KEY (package_id)
        REFERENCES packages(id) ON DELETE CASCADE
) ENGINE=InnoDB;

INSERT INTO packages (id, slug, tier, name, description, normal_price, price, renewal_price, is_featured, sort_order) VALUES
(1, 'landing-page',    'Paket', 'Smart Landing Page', 'Cocok untuk Anda yang ingin website simple dan minimalis.',                                1899000, 1899000,  950000, 0, 1),
(2, 'company-profile', 'Paket', 'Company Profile',    'Cocok untuk website company profile usaha, jasa, sekolah, dan sejenisnya.',               2999000, 2999000,  950000, 1, 2),
(3, 'corporate',       'Paket', 'Corporate',          'Cocok untuk website corporate, toko, atau online shop.',                                  3499000, 3499000, 1500000, 0, 3);

INSERT INTO package_features (package_id, feature, sort_order) VALUES
(1, 'Storage 5 GB', 1),
(1, 'Free domain & hosting (.com / .net)', 2),
(1, '2 halaman full design', 3),
(1, 'SEO & mobile friendly', 4),
(1, 'Desain premium / PRO', 5),
(1, 'Sertifikat SSL (https)', 6),
(1, 'Support blog system', 7),
(1, 'Akses login cPanel', 8),
(1, 'Revisi maksimal 2×', 9),
(1, 'Free 5 akun email', 10),
(1, 'Free panduan pengelolaan', 11),
(2, 'Storage 7 GB', 1),
(2, 'Free domain & hosting (.com / .net)', 2),
(2, '5 halaman full design', 3),
(2, 'SEO & mobile friendly', 4),
(2, 'Desain premium / PRO', 5),
(2, 'Sertifikat SSL (https)', 6),
(2, 'Support blog system', 7),
(2, 'Akses login cPanel', 8),
(2, 'Revisi maksimal 3×', 9),
(2, 'Free 5 akun email', 10),
(2, 'Free panduan pengelolaan', 11),
(3, 'Storage 10 GB', 1),
(3, 'Free domain & hosting (semua ekstensi populer)', 2),
(3, '5 halaman full design', 3),
(3, 'SEO & mobile friendly', 4),
(3, 'Desain premium / PRO', 5),
(3, 'Sertifikat SSL (https)', 6),
(3, 'Support blog system', 7),
(3, 'Akses login cPanel', 8),
(3, 'Revisi maksimal 3×', 9),
(3, 'Free 5 akun email', 10),
(3, 'Free panduan pengelolaan', 11);

-- ---------- Layanan ----------
CREATE TABLE services (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    title       VARCHAR(100) NOT NULL,
    description TEXT         NOT NULL,
    price_label VARCHAR(80)  NULL COMMENT 'mis. Paket mulai Rp1.950.000',
    is_active   TINYINT(1)   NOT NULL DEFAULT 1,
    sort_order  INT          NOT NULL DEFAULT 0
) ENGINE=InnoDB;

INSERT INTO services (title, description, price_label, sort_order) VALUES
('Website company profile',  'Halaman resmi perusahaan: profil, layanan, legalitas, dan kontak. Dibuat agar calon klien percaya sebelum menghubungi Anda.', 'Paket mulai Rp2.999.000', 1),
('Landing page promosi',     'Satu halaman fokus untuk kampanye, produk baru, atau event. Semua tombol mengarah ke WhatsApp atau form.', 'Paket mulai Rp1.899.000', 2),
('Website toko & katalog',   'Tampilkan produk dan harga dengan rapi, pembeli langsung pesan lewat WhatsApp tanpa perlu marketplace.', 'Paket mulai Rp3.499.000', 3),
('Perawatan & perpanjangan', 'Kami urus perpanjangan domain, hosting, backup, dan update konten kecil setelah website tayang.', 'Perpanjangan mulai Rp950.000/tahun', 4);

-- ---------- Kenapa CodeWave ----------
CREATE TABLE reasons (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    title       VARCHAR(100) NOT NULL,
    description TEXT         NOT NULL,
    sort_order  INT          NOT NULL DEFAULT 0
) ENGINE=InnoDB;

INSERT INTO reasons (title, description, sort_order) VALUES
('Mulai dari tujuan bisnis', 'Kami tanya dulu siapa calon klien Anda dan apa yang ingin mereka lakukan, baru desain menyesuaikan.', 1),
('Harga jelas di depan',     'Domain, hosting, desain, dan revisi sudah tertulis di paket. Tidak ada tagihan kejutan di tengah jalan.', 2),
('Langsung ke WhatsApp',     'Setiap tombol penting mengarah ke chat Anda, karena di situlah calon klien biasanya memutuskan.', 3),
('Didampingi setelah tayang', 'Website online bukan akhir kerja sama. Perpanjangan, backup, dan update kecil tetap kami bantu.', 4);

-- ---------- Alur kerja ----------
CREATE TABLE steps (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    title       VARCHAR(100) NOT NULL,
    description TEXT         NOT NULL,
    duration    VARCHAR(40)  NOT NULL,
    sort_order  INT          NOT NULL DEFAULT 0
) ENGINE=InnoDB;

INSERT INTO steps (title, description, duration, sort_order) VALUES
('Konsultasi',      'Ceritakan bisnis dan kebutuhan Anda lewat WhatsApp. Kami bantu pilih paket yang pas.', 'Hari 1',     1),
('Kirim materi',    'Logo, foto, teks profil, dan daftar layanan. Belum lengkap? Kami bantu susun.',         'Hari 1–2',   2),
('Desain & bangun', 'Kami rancang tampilan sesuai brand dan membangun halamannya.',                          'Hari 3–7',   3),
('Revisi',          'Anda cek di link pratinjau, lalu kirim catatan perbaikan sesuai kuota paket.',          'Hari 8–10',  4),
('Tayang',          'Website online di domain Anda, lengkap dengan panduan singkat pengelolaannya.',        'Hari 10–14', 5);

-- ---------- FAQ ----------
CREATE TABLE faqs (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    question    VARCHAR(255) NOT NULL,
    answer      TEXT         NOT NULL,
    is_active   TINYINT(1)   NOT NULL DEFAULT 1,
    sort_order  INT          NOT NULL DEFAULT 0
) ENGINE=InnoDB;

INSERT INTO faqs (question, answer, sort_order) VALUES
('Harga di atas sudah termasuk apa saja?', 'Sudah termasuk domain, hosting, sertifikat SSL, desain premium, 5 akun email, akses cPanel, dan revisi sesuai paket untuk tahun pertama. Tidak ada biaya tersembunyi.', 1),
('Berapa biaya tahun kedua?', 'Mulai tahun kedua Anda hanya membayar perpanjangan: Rp950.000/tahun untuk paket Smart Landing Page dan Company Profile, serta Rp1.500.000/tahun untuk paket Corporate.', 2),
('Berapa lama website selesai?', 'Umumnya 7–14 hari kerja sejak materi lengkap, tergantung jumlah halaman dan kecepatan revisi.', 3),
('Apakah saya bisa mengubah isi website sendiri?', 'Bisa. Untuk halaman berita/blog kami siapkan cara mengisinya, dan kami berikan panduan singkat setelah website tayang.', 4),
('Bagaimana sistem pembayarannya?', 'DP 50% saat pengerjaan dimulai, pelunasan 50% sebelum website dipindahkan ke domain Anda.', 5),
('Saya belum punya logo dan foto, bagaimana?', 'Tetap bisa mulai. Kami gunakan foto stok berlisensi bebas sementara, dan bisa diganti kapan saja.', 6);

-- ---------- Pesan masuk dari form konsultasi ----------
CREATE TABLE leads (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100) NOT NULL,
    business    VARCHAR(150) NOT NULL,
    package_id  INT UNSIGNED NULL,
    package_name  VARCHAR(100) NULL COMMENT 'nama paket saat pesan dikirim',
    package_price INT UNSIGNED NULL COMMENT 'harga paket saat pesan dikirim',
    budget        VARCHAR(40)  NULL,
    message     TEXT         NULL,
    status      ENUM('baru','dihubungi','deal','batal') NOT NULL DEFAULT 'baru',
    note        VARCHAR(255) NULL,
    ip_address  VARCHAR(45)  NULL,
    created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_created (created_at),
    CONSTRAINT fk_lead_package FOREIGN KEY (package_id)
        REFERENCES packages(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------- Admin ----------
-- Login awal: admin / codewave123  (WAJIB diganti setelah login pertama)
CREATE TABLE admins (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    username      VARCHAR(50)  NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    last_login    DATETIME     NULL,
    created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT INTO admins (username, password_hash) VALUES
('admin', '$2y$10$rdhkj44hmjfux9w0ZZgbWePUreVda.8iFOhZp6yrNRaHmwiXTuBF6');

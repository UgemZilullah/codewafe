-- =========================================================
--  Upgrade CodeWave v1 -> v2 (tampilan baru)
--  Jalankan HANYA jika database codewave sudah ada dari versi sebelumnya
--  dan Anda tidak ingin kehilangan data pesan masuk.
--  Instalasi baru cukup import codewave.sql.
-- =========================================================
SET NAMES utf8mb4;
USE codewave;

ALTER TABLE services ADD COLUMN price_label VARCHAR(80) NULL AFTER description;
UPDATE services SET price_label = 'Paket mulai Rp1.950.000'      WHERE title = 'Website company profile';
UPDATE services SET price_label = 'Harga menyesuaikan kebutuhan' WHERE title IN ('Landing page promosi', 'Website UMKM & katalog');
UPDATE services SET price_label = 'Mulai tahun kedua'            WHERE title = 'Perawatan & perpanjangan';

ALTER TABLE leads ADD COLUMN budget VARCHAR(40) NULL AFTER package_price;

CREATE TABLE IF NOT EXISTS reasons (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    title       VARCHAR(100) NOT NULL,
    description TEXT         NOT NULL,
    sort_order  INT          NOT NULL DEFAULT 0
) ENGINE=InnoDB;

-- isi contoh hanya jika tabel masih kosong
INSERT INTO reasons (title, description, sort_order)
SELECT * FROM (
    SELECT 'Mulai dari tujuan bisnis' AS t, 'Kami tanya dulu siapa calon klien Anda dan apa yang ingin mereka lakukan, baru desain menyesuaikan.' AS d, 1 AS o
    UNION ALL SELECT 'Harga jelas di depan', 'Domain, hosting, desain, dan revisi sudah tertulis di paket. Tidak ada tagihan kejutan di tengah jalan.', 2
    UNION ALL SELECT 'Langsung ke WhatsApp', 'Setiap tombol penting mengarah ke chat Anda, karena di situlah calon klien biasanya memutuskan.', 3
    UNION ALL SELECT 'Didampingi setelah tayang', 'Website online bukan akhir kerja sama. Perpanjangan, backup, dan update kecil tetap kami bantu.', 4
) AS seed
WHERE NOT EXISTS (SELECT 1 FROM reasons);

INSERT IGNORE INTO settings (setting_key, setting_value, label) VALUES
('about',     'CodeWave membantu perusahaan dan UMKM punya website yang benar-benar dipakai: dibuka calon klien, dipercaya, lalu berujung chat. Kami bekerja praktis, harga jelas di depan, dan tetap mendampingi setelah website tayang.', 'Teks bagian Tentang CodeWave'),
('instagram', '', 'Link Instagram (kosongkan jika tidak ada)'),
('facebook',  '', 'Link Facebook (kosongkan jika tidak ada)'),
('tiktok',    '', 'Link TikTok (kosongkan jika tidak ada)');

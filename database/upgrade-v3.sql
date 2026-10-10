-- =========================================================
--  Upgrade CodeWave v2 -> v3 (harga & paket baru)
--  Jalankan sekali jika database sudah berisi data (pesan masuk tetap aman).
--  Instalasi baru cukup import codewave.sql.
-- =========================================================
SET NAMES utf8mb4;
USE codewave;

ALTER TABLE packages ADD COLUMN renewal_price INT UNSIGNED NULL AFTER price_note;

-- Paket lama diganti paket baru (id dipertahankan, riwayat pesan masuk tidak berubah
-- karena nama & harga paket sudah tersimpan di tiap pesan)
DELETE FROM package_features WHERE package_id IN (1, 2, 3);
DELETE FROM packages WHERE id IN (1, 2, 3);
INSERT INTO packages (id, slug, tier, name, description, normal_price, price, renewal_price, is_featured, sort_order) VALUES
(1, 'landing-page',    'Paket', 'Smart Landing Page', 'Cocok untuk Anda yang ingin website simple dan minimalis.',                  1899000, 1899000,  950000, 0, 1),
(2, 'company-profile', 'Paket', 'Company Profile',    'Cocok untuk website company profile usaha, jasa, sekolah, dan sejenisnya.', 2999000, 2999000,  950000, 1, 2),
(3, 'corporate',       'Paket', 'Corporate',          'Cocok untuk website corporate, toko, atau online shop.',                    3499000, 3499000, 1500000, 0, 3);
UPDATE packages SET is_featured = 0 WHERE id NOT IN (2);

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

UPDATE services SET price_label = 'Paket mulai Rp2.999.000' WHERE title = 'Website company profile';
UPDATE services SET price_label = 'Paket mulai Rp1.899.000' WHERE title = 'Landing page promosi';
UPDATE services SET title = 'Website toko & katalog', price_label = 'Paket mulai Rp3.499.000' WHERE title = 'Website UMKM & katalog';
UPDATE services SET price_label = 'Perpanjangan mulai Rp950.000/tahun' WHERE title = 'Perawatan & perpanjangan';

UPDATE faqs SET answer = 'Sudah termasuk domain, hosting, sertifikat SSL, desain premium, 5 akun email, akses cPanel, dan revisi sesuai paket untuk tahun pertama. Tidak ada biaya tersembunyi.'
 WHERE question = 'Harga di atas sudah termasuk apa saja?';
UPDATE faqs SET answer = 'Mulai tahun kedua Anda hanya membayar perpanjangan: Rp950.000/tahun untuk paket Smart Landing Page dan Company Profile, serta Rp1.500.000/tahun untuk paket Corporate.'
 WHERE question = 'Berapa biaya tahun kedua?';

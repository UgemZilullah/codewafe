# CodeWave — Website Jasa Pembuatan Website (PHP + MySQL)

## Versi 3: harga & paket baru
Paket diganti menjadi Smart Landing Page (Rp1.899.000), Company Profile (Rp2.999.000, paling laris),
dan Corporate (Rp3.499.000), lengkap dengan biaya perpanjangan per tahun.
Kartu paket kini menonjolkan fitur pembeda (storage, domain, jumlah halaman, revisi) dan
mengelompokkan fitur yang sama di semua paket ke bagian "Termasuk juga" secara otomatis.
Tombol "Pilih paket ini" membuka form konsultasi dengan paket sudah terpilih.
Di HP ada bar aksi cepat (Lihat paket / Chat WhatsApp) di bawah layar.

**Database lama?** Import `database/upgrade-v3.sql` sekali (dan `upgrade-v2.sql` dulu jika masih versi 1).

## Versi 2: tampilan baru (ATM dari ricksitestudio.com)
Yang diambil dari pola Ricksite: hero gelap besar, tombol dengan teks bergulir saat disorot,
strip layanan berjalan (marquee), daftar layanan bernomor 01–04 dengan keterangan harga,
bagian "Kenapa CodeWave", tahapan kerja Tahap-01 s.d. 05, form dengan estimasi budget,
bagian Tentang, CTA penutup besar, dan footer berkolom.
Yang tetap khas CodeWave: logo dan palet teal→biru, motif gelombang, kartu paket harga
dari database, dan semua alur berakhir di WhatsApp.

**Sudah pakai versi 1 dan punya data pesan masuk?** Jangan import ulang `codewave.sql`
(akan menghapus data). Cukup import `database/upgrade-v2.sql` sekali lewat phpMyAdmin.

Website penjualan jasa pembuatan website, lengkap dengan database dan panel admin.
Semua paket harga, FAQ, layanan, dan info kontak diambil dari database, dan pesan
dari form konsultasi tersimpan otomatis.

## Kebutuhan
- PHP 8.1 atau lebih baru (ekstensi `pdo_mysql`, sudah aktif di XAMPP/Laragon/cPanel)
- MySQL 5.7+ atau MariaDB 10.3+

## Cara pasang di komputer (XAMPP)
0. Salin `includes/config.example.php` menjadi `includes/config.php` (file ini tidak disimpan di GitHub karena berisi password database).
1. Ekstrak folder `codewave` ke `C:\xampp\htdocs\`.
2. Buka XAMPP Control Panel, Start **Apache** dan **MySQL**.
3. Buka `http://localhost/phpmyadmin`, klik tab **Import**, pilih file `database/codewave.sql`, lalu klik **Import**.
   Database `codewave` akan dibuat otomatis beserta isinya.
4. Buka `http://localhost/codewave`.
5. Panel admin: `http://localhost/codewave/admin`
   - Username: `admin`
   - Password: `codewave123` → **segera ganti** di menu Pengaturan.

## Cara pasang di hosting (cPanel)
1. Di cPanel buka **MySQL Databases**: buat database dan user baru, lalu beri user itu akses ALL PRIVILEGES.
2. Buka **phpMyAdmin**, pilih database tadi, Import `database/codewave.sql`.
   Jika muncul error karena perintah `CREATE DATABASE`, hapus 3 baris `CREATE DATABASE ... USE codewave;` di bagian atas file lalu import lagi.
3. Upload isi folder `codewave` ke `public_html`.
4. Salin `includes/config.example.php` menjadi `includes/config.php`, lalu isi isi `DB_NAME`, `DB_USER`, `DB_PASS` sesuai yang dibuat di langkah 1.

## Panel admin
| Menu | Fungsi |
|---|---|
| Pesan masuk | Daftar calon klien dari form konsultasi, ubah status (Baru, Dihubungi, Deal, Batal), catatan internal, cari, dan statistik |
| Paket harga | Tambah, ubah, hapus, urutkan, sembunyikan paket, dan tandai "Paling laris" |
| FAQ | Tambah, ubah, hapus, urutkan, sembunyikan pertanyaan |
| Pengaturan | Nomor WhatsApp, email, lokasi, jam respon, tagline, dan ganti password |

Layanan (termasuk keterangan harganya), Kenapa CodeWave, dan Alur kerja disimpan di tabel `services`, `reasons`, dan `steps`; ubah lewat phpMyAdmin bila perlu.
Teks Tentang dan link Instagram/Facebook/TikTok diatur di menu Pengaturan (link kosong tidak ditampilkan).

## Tabel database
- `settings`: info bisnis (WhatsApp, email, dll.)
- `packages` + `package_features`: paket harga dan daftar fiturnya
- `services`, `steps`, `faqs`: konten halaman
- `reasons`: poin Kenapa CodeWave
- `leads`: pesan dari form konsultasi, termasuk estimasi budget (nama & harga paket ikut disimpan, jadi laporan tidak berubah walau harga paket diedit)
- `admins`: akun admin (password tersimpan ter-enkripsi)

## Keamanan yang sudah ada
Query memakai prepared statement (aman dari SQL injection), token CSRF di semua form,
honeypot dan batas 5 kiriman per 10 menit untuk menahan spam, password admin di-hash,
batas percobaan login, sesi admin berakhir setelah 2 jam tidak aktif, dan folder
`includes/` serta `database/` ditutup dari akses browser (Apache).


<?php
/**
 * CodeWave — contoh koneksi database.
 * Salin file ini menjadi config.php lalu isi sesuai server Anda.
 * config.php sengaja tidak ikut di-upload ke GitHub (lihat .gitignore).
 * Sesuaikan dengan pengaturan MySQL di XAMPP/Laragon atau cPanel Anda.
 */
const DB_HOST = 'localhost';
const DB_PORT = 3306;
const DB_NAME = 'codewave';
const DB_USER = 'root';     // cPanel biasanya: namauser_codewave
const DB_PASS = '';         // XAMPP default: kosong

const APP_DEBUG = false;    // true = tampilkan detail error (hanya saat pengembangan)

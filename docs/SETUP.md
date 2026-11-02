# Instalasi dan Menjalankan Aplikasi

## Kebutuhan

PHP 8+ dengan PDO MySQL (`pdo_mysql`), MySQL/MariaDB, browser modern, dan direktori `uploads/` yang dapat ditulisi PHP. Service Worker dan Notifications API memerlukan localhost atau HTTPS.

## Database

Koneksi default di `config.php`: host `localhost`, database `todo_app`, user `root`, password kosong. Sesuaikan untuk lingkungan lokal Anda. Jangan simpan kredensial production di source control.

Untuk impor manual, jalankan MySQL, lalu impor `data.sql` melalui phpMyAdmin atau terminal. Skrip ini membuat database, tabel, foreign key, dan data demo. `config.php` juga mencoba membuat database dan mengimpor SQL bila koneksi awal gagal dan tabel `users` belum tersedia; user MySQL perlu hak untuk membuat database/tabel.

> **Peringatan:** `data.sql` menjalankan DROP TABLE pada tabel aplikasi sebelum membuat ulang skema. Impor ulang menghapus data yang ada. Backup dahulu dan jangan jalankan pada database penting.

## XAMPP atau Laragon

Tempatkan proyek di `C:\xampp\htdocs\tugas1` (XAMPP) atau `C:\laragon\www\tugas1` (Laragon). Nyalakan Apache dan MySQL, kemudian buka [http://localhost/tugas1/login.php](http://localhost/tugas1/login.php). Ganti `tugas1` jika folder berbeda.

## PHP built-in server

Dari direktori proyek:

```bash
php -S 127.0.0.1:8000
```

Buka [http://127.0.0.1:8000/login.php](http://127.0.0.1:8000/login.php). Server bawaan PHP hanya untuk pengembangan lokal.

## Akun demo

| Username | Password | Nama |
| --- | --- | --- |
| `dafa` | `password` | Dafa Dega Wijaya |
| `teman` | `password` | Teman Kuliah |

Password seed disimpan sebagai hash dan diverifikasi dengan `password_verify()`. Ganti/hapus akun demo sebelum aplikasi digunakan di luar demonstrasi.

## Notifikasi browser

Buka dashboard via localhost/HTTPS, tekan **Aktifkan Notifikasi**, izinkan situs, lalu buat tugas dengan waktu pengingat mendatang. Dashboard perlu tetap terbuka agar jadwal diperiksa. Jika izin ditolak, ubah izin di browser dan muat ulang. Lihat [Panduan Developer: pengingat](DEVELOPER_GUIDE.md#pengingat-dan-riwayat-notifikasi).

## Progressive Web App dan penggunaan offline

PWA memerlukan localhost atau HTTPS. Login dan buka dashboard Personal/Shared saat online setidaknya sekali agar snapshot per akun/workspace tersimpan di IndexedDB. Service Worker menyimpan aset shell dan halaman offline; halaman PHP pribadi tidak dimasukkan ke Cache Storage.

Saat offline, navigasi yang gagal membuka workspace lokal. Perubahan todo disimpan pada antrean perangkat dan disinkronkan berurutan ketika koneksi kembali. Jangan hapus data situs/IndexedDB sebelum perubahan tertunda tersinkron. Pengingat dan riwayat notifikasi memerlukan koneksi ke server.

Di Edge, tombol **Unduh / Instal Aplikasi** selalu tersedia; tombol memakai prompt browser hanya jika event instalasi asli tersedia. Jika tidak, gunakan menu **Apps → Install this site as an app**. Untuk validasi, buka DevTools (`F12`): periksa **Application → Manifest**, **Service Workers**, dan **Cache Storage**; lalu centang **Network → Offline**, muat ulang, uji perubahan todo, aktifkan koneksi kembali, dan pastikan antrean tersinkron.

Detail implementasi tersedia pada [implementasi PWA](PWA_IMPLEMENTATION.md), prosedur dan hasil validasi pada [pengujian](TESTING.md), serta tautan dokumentasi lain di [README](../README.md#dokumentasi).

## URL lokal

- [Login](http://127.0.0.1:8000/login.php)
- [Personal](http://127.0.0.1:8000/index.php?view=personal)
- [Shared](http://127.0.0.1:8000/index.php?view=shared)
- [Riwayat notifikasi](http://127.0.0.1:8000/notifications.php)

Halaman selain login memerlukan sesi aktif.

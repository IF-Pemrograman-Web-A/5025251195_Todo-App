# The Iron Citadel

The Iron Citadel adalah aplikasi pengelolaan tugas berbasis PHP dan MySQL dengan workspace Personal dan Shared. Pembaruan E05 menambahkan dukungan PWA yang dapat dipasang dan workspace offline, tanpa mengganti dashboard berbasis server yang sudah ada.

**Proyek:** Tugas 1 — Pemrograman Web A · **Branch PWA:** E05

## Fitur

- Login berbasis sesi, workspace Personal/Shared, CRUD tugas, pencarian, filter, dan ringkasan status.
- Tenggat, pengingat browser, foto tugas, dan riwayat pengingat.
- Dashboard responsif dengan tema terang/gelap dan pergantian akun khusus demo.
- App shell PWA dan snapshot tugas offline dengan antrean sinkronisasi.

## Mulai cepat

Lihat [Instalasi](docs/INSTALLATION.md) untuk prasyarat dan langkah setup. [Panduan setup](docs/SETUP.md) yang sudah ada mencakup prosedur XAMPP/Laragon dan PHP built-in server.

1. Siapkan PHP dengan PDO MySQL dan jalankan MySQL/MariaDB.
2. Atur koneksi database lokal di `config.php`.
3. Dari direktori proyek, jalankan `php -S 127.0.0.1:8000`.
4. Buka [http://127.0.0.1:8000/login.php](http://127.0.0.1:8000/login.php).

> **Peringatan data:** Impor `data.sql` menghapus lalu membuat ulang tabel aplikasi. Cadangkan database terlebih dahulu; jangan impor ulang ke database yang berisi data penting.

## Ringkasan PWA E05

Aplikasi memerlukan localhost atau HTTPS untuk Service Worker. Buka kedua workspace saat online setidaknya sekali agar snapshot tersedia pada profil browser yang digunakan. Saat offline, Service Worker menyajikan aset shell dari cache dan menggunakan `offline.html` jika navigasi gagal; data tugas dan perubahan tertunda disimpan di IndexedDB, bukan pada halaman PHP yang di-cache.

Tombol **Unduh / Instal Aplikasi** tetap tersedia pada dashboard. Tombol menggunakan event `beforeinstallprompt` dari browser jika tersedia, dan jika tidak akan menampilkan langkah instalasi manual Edge. Event `beforeinstallprompt` asli pernah teramati pada profil uji Edge terisolasi, tetapi tampilan prompt native dan penyelesaian instalasi sungguhan **belum terverifikasi**. Lihat [implementasi PWA](docs/PWA_IMPLEMENTATION.md) dan [scorecard pengujian](docs/TESTING.md).

## Struktur proyek

| Path | Tujuan |
| --- | --- |
| `index.php`, `login.php`, `logout.php` | Dashboard dan pengelolaan sesi |
| `todo_actions.php`, `content.php`, `todo-item.php` | Alur tugas yang dirender server |
| `notification_*.php`, `notifications.php` | Endpoint pengingat dan riwayat |
| `manifest.webmanifest`, `service-worker.js`, `pwa.js` | Metadata PWA, cache, dan UI instalasi |
| `offline.html`, `pwa-offline.js`, `pwa_sync.php` | Workspace offline, IndexedDB, dan endpoint sinkronisasi |
| `data.sql`, `config.php`, `uploads/` | Setup database, koneksi lokal, dan foto unggahan |
| `docs/` | Dokumentasi setup, arsitektur, PWA, pengujian, screenshot, dan troubleshooting |

`index.html` dan `script.js` dipertahankan sebagai berkas client-side versi lama; dashboard aktif dirender oleh PHP.

## Dokumentasi

- [Arsitektur](docs/ARCHITECTURE.md)
- [Instalasi](docs/INSTALLATION.md)
- [Implementasi PWA](docs/PWA_IMPLEMENTATION.md)
- [Pengujian dan scorecard bukti](docs/TESTING.md)
- [Slot bukti screenshot](docs/SCREENSHOTS.md)
- [Troubleshooting](docs/TROUBLESHOOTING.md)
- [Panduan setup](docs/SETUP.md)
- [Panduan developer](docs/DEVELOPER_GUIDE.md)

## Status validasi

Persistensi status tugas offline dan sinkronisasi antrean telah diuji melalui browser automation. Browser tidak melaporkan error installability manifest dan event instalasi asli teramati pada profil Edge terisolasi. UI instalasi native, keberhasilan instalasi, dan screenshot panel DevTools masih belum terverifikasi; README ini tidak menyatakannya sebagai bukti yang sudah diperoleh. Lihat [Pengujian](docs/TESTING.md) untuk hasil dan batasannya.

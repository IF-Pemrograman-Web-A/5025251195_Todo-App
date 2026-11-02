# Arsitektur

[Kembali ke README](../README.md) · Terkait: [Instalasi](INSTALLATION.md) · [Implementasi PWA](PWA_IMPLEMENTATION.md) · [Panduan developer](DEVELOPER_GUIDE.md)

## Gambaran runtime

Aplikasi ini adalah dashboard yang dirender PHP dan menggunakan MySQL/MariaDB. Browser menyediakan interaksi client-side, penjadwalan pengingat, Service Worker, dan workspace offline berbasis IndexedDB. Entry point dashboard aktif adalah `index.php`; `index.html` dipertahankan sebagai artefak statis lama.

```text
Browser
  ├─ PHP dashboard and task forms ───────> PHP endpoints ───────> MySQL/MariaDB
  ├─ Reminder scheduler ─────────────────> notification endpoints
  ├─ Service Worker ─────────────────────> cached shell / offline fallback
  └─ Offline workspace ──> IndexedDB ──> pwa_sync.php ─────────> MySQL/MariaDB
                              snapshots
                              outbox
                              images
```

## Alur tugas online

1. `login.php` membuat sesi; `index.php` melindungi dan merender dashboard.
2. Aksi dashboard dikirim ke `todo_actions.php`. Server memvalidasi aksi dan aturan kepemilikan sebelum mengubah baris database.
3. Data pengingat dibaca dan dicatat melalui endpoint notifikasi. Browser meminta Service Worker menampilkan notifikasi; event `triggered` berarti browser menerima pemanggilan, bukan bukti seseorang melihat notifikasi.

## Alur tugas offline

1. Akun yang sudah login menyimpan data dashboard sebagai snapshot Personal/Shared per pengguna di IndexedDB saat online.
2. `offline.html` membaca snapshot dan menerapkan operasi antrean untuk ditampilkan.
3. Perubahan offline ditambahkan ke `outbox`; toggle status memperbarui snapshot terkait dan menambahkan operasi dalam satu transaksi read/write IndexedDB.
4. Saat koneksi kembali atau aplikasi diinisialisasi dalam keadaan online, `pwa-offline.js` mengirim operasi pengguna secara berurutan ke `pwa_sync.php`.
5. Respons sukses memperbarui snapshot lokal dan menghapus operasi tersebut. Operasi gagal tetap di antrean untuk dicoba kembali.

Event browser `online` memicu sinkronisasi dari halaman yang aktif. Proyek ini tidak mengimplementasikan Background Sync yang tetap berjalan setelah halaman ditutup.

## Model data lokal

The IndexedDB database is `ironCitadelPwa`, version 1:

| Store | Key | Purpose |
| --- | --- | --- |
| `snapshots` | `{userId}:{view}` | Data tugas Personal/Shared, ID akun, token CSRF, dan waktu simpan |
| `outbox` | `sequence` auto-increment | Operasi tertunda berurutan, termasuk UUID operasi dan payload tugas |
| `images` | `{userId}:{path}` | Blob foto yang di-cache untuk dirender offline |

Server menyimpan data tugas pada tabel aplikasi yang sudah ada. `pwa_sync_operations` mencatat UUID operasi pengguna dan respons agar retry tidak menerapkan operasi selesai lebih dari sekali. Tabel ini ada di `data.sql` dan dibuat oleh `pwa_sync.php` bila diperlukan.

## Batasan penting

- Halaman akun yang dirender PHP dan respons API tidak disimpan di Cache Storage.
- IndexedDB berlaku lokal untuk profil/perangkat browser; bukan backup atau penyimpanan lintas perangkat.
- Mode offline memerlukan snapshot yang telah disimpan saat online. Fitur ini tidak menyediakan autentikasi offline terhadap server.
- Logout menghapus penanda akun offline aktif, bukan snapshot lokal atau antrean data pengguna.
- `data.sql` adalah skrip reset/seed yang menghapus tabel aplikasi sebelum membuat ulang.

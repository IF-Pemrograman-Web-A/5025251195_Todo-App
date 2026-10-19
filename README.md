# The Iron Citadel

Aplikasi manajemen tugas berbasis PHP dan MySQL untuk ruang kerja Personal dan Shared. Fitur mencakup autentikasi sesi, CRUD, status tugas, lampiran foto, serta pengingat browser.

## Akses lokal

Tautan ini berlaku setelah server dijalankan. Sesuaikan host, port, atau path bila konfigurasi berbeda.

| Halaman | URL |
| --- | --- |
| Login | [login.php](http://127.0.0.1:8000/login.php) |
| Dashboard Personal | [Personal](http://127.0.0.1:8000/index.php?view=personal) |
| Ruang kerja Shared | [Shared](http://127.0.0.1:8000/index.php?view=shared) |
| Riwayat notifikasi | [Notifications](http://127.0.0.1:8000/notifications.php) |

XAMPP/Laragon dengan folder `tugas1`: `http://localhost/tugas1/`.

## Mulai cepat

1. Jalankan MySQL dan sesuaikan koneksi di `config.php`.
2. Siapkan database dengan `data.sql` atau inisialisasi otomatis.
3. Dari direktori proyek, jalankan `php -S 127.0.0.1:8000`.
4. Buka login dengan akun demo:

| Username | Password |
| --- | --- |
| `dafa` | `password` |
| `teman` | `password` |

> **Perhatian:** `data.sql` menghapus tabel aplikasi sebelum membuat ulang skema dan data demo. Backup data terlebih dahulu sebelum impor ulang.

## Fitur

- Daftar Personal/Shared, pencarian, filter, dan statistik.
- Tambah, edit detail/status, toggle selesai, dan hapus tugas.
- Tenggat, pengingat browser, foto file/kamera, dan deskripsi foto.
- Tema terang/gelap, tampilan responsif, serta Quick Switch akun demo.
- Riwayat pengingat, deadline terlewat, dan tugas selesai terjadwal.

## Panduan antarmuka

Bagian ini mendokumentasikan alur yang ditunjukkan pada tampilan aplikasi. Tautan halaman membuka instalasi lokal saat server berjalan di port 8000.

### 1. Login

Halaman login menjadi pintu masuk ke dashboard. Pengguna memasukkan username dan kata sandi; akun demo tersedia untuk mencoba alur Personal dan Shared.

[Buka halaman login](http://127.0.0.1:8000/login.php)

### 2. Dashboard Personal

Dashboard Personal menampilkan ringkasan total, pending, completed, dan overdue, serta tabel tugas milik akun aktif. Banner status memberi umpan balik pengingat browser. Tombol **Aktifkan Notifikasi** meminta izin browser, sedangkan panel **Add New Task** membuat tugas, mengatur kategori/tenggat/pengingat, dan menambahkan foto dari file atau kamera.

[Buka workspace Personal](http://127.0.0.1:8000/index.php?view=personal)

### 3. Edit tugas dan status

Tombol **Edit** membuka dialog terpusat untuk memperbarui detail tugas. Pilihan status mengikuti nilai yang digunakan aplikasi: **Proses (Belum selesai)** dan **Selesai**. Perubahan disimpan bersama judul, keterangan, kategori, tenggat, pengingat, dan deskripsi foto; kontrol checkbox pada daftar tetap tersedia untuk toggle cepat.

### 4. Workspace Shared

Workspace Shared menampilkan tugas bersama dan identitas pembuatnya. Anggota yang sudah login dapat melihat tugas dan memperbarui statusnya; pengeditan detail serta penghapusan tetap dibatasi kepada pemilik tugas.

[Buka workspace Shared](http://127.0.0.1:8000/index.php?view=shared)

### 5. Quick Switch Account

Pada akun demo yang diizinkan, sidebar menyediakan **Quick Switch Account** untuk berpindah antara `dafa` dan `teman`. Fitur ini membantu demonstrasi lintas akun dan tidak ditujukan untuk impersonasi di lingkungan production.

### 6. Filter dan tema

Pencarian memfilter judul/keterangan, sedangkan filter status menyediakan **All status**, **Pending**, **Completed**, dan **Overdue**. Tombol tema pada header berganti antara mode terang dan gelap.

## Dokumentasi

- [Instalasi dan menjalankan aplikasi](docs/SETUP.md)
- [Panduan developer lengkap](docs/DEVELOPER_GUIDE.md)

**Stack:** PHP 8+, MySQL/MariaDB, PDO, HTML, CSS, JavaScript, Notifications API, Service Worker.

Dikembangkan oleh **Dafa Dega Wijaya** untuk **E04 — The Iron Citadel**, Pemrograman Web A.

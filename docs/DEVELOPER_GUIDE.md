# Panduan Developer

Referensi fitur, arsitektur, database, dan operasional The Iron Citadel.

## Daftar isi

- [Ruang lingkup](#ruang-lingkup)
- [Fitur](#fitur)
- [Hak akses](#hak-akses)
- [Peta modul](#peta-modul)
- [Database](#database)
- [Pengingat dan riwayat notifikasi](#pengingat-dan-riwayat-notifikasi)
- [Keamanan dan batasan](#keamanan-dan-batasan)
- [Pemeliharaan](#pemeliharaan)

## Ruang lingkup

Aplikasi pengelolaan tugas server-side untuk tugas personal dan Shared. PHP menangani autentikasi, sesi, akses data, dan rendering; JavaScript mengelola interaksi dashboard, polling pengingat, serta komunikasi Service Worker. Entry point saat ini `index.php`. `index.html` dan `script.js` adalah artefak versi client-side terdahulu.

## Fitur

### Workspace

- **Personal** menampilkan tugas kategori `personal` milik akun aktif; **Shared** menampilkan seluruh tugas kategori `shared` dalam model proyek ini.
- Daftar menyediakan pencarian judul/keterangan, filter All status/Pending/Completed/Overdue, dan statistik total/pending/completed/overdue.
- Antarmuka mendukung tema terang/gelap dan tata letak responsif.

### Tugas dan lampiran

Form create menerima judul, keterangan, kategori, tenggat, waktu notifikasi, dan foto. Modal edit dapat mengubah detail dan status melalui **Proses (Belum selesai)** atau **Selesai**. Kontrol baris juga menyediakan toggle cepat. Status disimpan di `todos.selesai` sebagai 0/1. Deadline hanya overdue bagi tugas yang belum selesai.

Foto dapat dipilih dari file atau kamera browser. Batas unggahan 5 MB; tipe yang diterima JPEG, PNG, GIF, WEBP. Path disimpan di database, file ada di `uploads/` dengan nama unik. Penggantian/penghapusan tugas mencoba membersihkan file lama. `deskripsi_foto` dipakai sebagai teks alternatif dan diwajibkan oleh UI saat foto baru digunakan.

### Quick Switch

Quick Switch hanya tersedia untuk akun demo `dafa` dan `teman`. Endpoint `switch-account.php` memeriksa sesi, token CSRF, dan allowlist username, lalu meregenerasi session ID. Fitur ini hanya untuk demonstrasi, bukan impersonasi production.

## Hak akses

| Aksi | Personal | Shared |
| --- | --- | --- |
| Melihat | Pemilik yang login | Semua pengguna terautentikasi pada model saat ini |
| Membuat | Pengguna login | Pengguna login |
| Edit detail/status | Pemilik tugas | Pemilik tugas |
| Hapus | Pemilik tugas | Pemilik tugas |
| Toggle cepat | Pemilik tugas | Anggota login yang melihat daftar |

Handler server tetap harus memeriksa izin; menyembunyikan tombol UI bukan kontrol akses.

## Peta modul

| File | Fungsi |
| --- | --- |
| `index.php` | Dashboard terproteksi; memilih view dan merakit halaman. |
| `login.php`, `logout.php` | Login dan penghentian sesi. |
| `auth.php` | Sesi, helper autentikasi/proteksi route, akun demo. |
| `config.php` | Koneksi PDO dan inisialisasi database lokal. |
| `menu-bar.php` | Navigasi, identitas, Quick Switch, logout. |
| `content.php` | Query daftar, statistik, pencarian/filter. |
| `form-todo.php` | Form create dan kontrol foto/kamera. |
| `todo-item.php` | Baris tugas, toggle, modal edit, aksi pemilik. |
| `todo_actions.php` | Handler create/update/toggle/delete. |
| `notification-scheduler.js` | Snapshot, polling jadwal, koordinasi tab, komunikasi worker. |
| `notification_actions.php` | Endpoint JSON snapshot dan pencatatan event. |
| `notification_store.php` | Pembuatan tabel event dan konversi waktu. |
| `notifications.php` | Upcoming, riwayat, overdue, completed. |
| `service-worker.js` | Menampilkan notifikasi dan membuka tugas saat notifikasi dipilih. |
| `dashboard.js` | Pencarian/filter, statistik, tema, interaksi dashboard. |
| `style.css`, `dashboard.css` | Gaya, layout, komponen, tema, responsivitas. |
| `data.sql` | Skema dan seed; `uploads/` menyimpan foto. |

### Alur request

1. `index.php` memerlukan sesi login.
2. `content.php` memilih Personal/Shared berdasarkan `?view=`.
3. Form POST dikirim ke `todo_actions.php`; handler memvalidasi dan memeriksa pemilik.
4. Dashboard mengambil snapshot `notification_actions.php?action=snapshot`, lalu meminta worker memunculkan notifikasi saat waktunya tiba.
5. Hasil pemanggilan browser dicatat per penerima, tugas, dan jadwal.

## Database

Skema berada di `data.sql`.

| Tabel | Isi |
| --- | --- |
| `users` | Username, hash password, nama lengkap. |
| `todos` | Pemilik, konten, foto, deadline, reminder, status, kategori. |
| `shared_todos` | Relasi tugas Shared dengan akun pembuat/pembagi. |
| `todo_notification_events` | Event reminder per penerima, tugas, dan jadwal. |

Kolom utama `todos`: `user_id` pemilik; `tipe` personal/shared; `selesai` 0/1; `tenggat` dan `waktu_notifikasi` DATETIME zona `Asia/Jakarta`; `foto` path dan `deskripsi_foto` teks alternatif. InnoDB foreign key/cascade dan unique key event mengikuti definisi pada SQL.

## Pengingat dan riwayat notifikasi

- Izin diminta setelah klik **Aktifkan Notifikasi**. Service Worker/Notifications API memerlukan localhost atau HTTPS.
- Dashboard memuat snapshot dan memeriksa setiap 10 detik, juga saat halaman fokus/aktif kembali.
- Status event: `processing`, `triggered`, `missed`. `triggered` berarti browser menerima pemanggilan `showNotification()`, bukan berarti pengguna melihatnya.
- Event dikelola per penerima, termasuk untuk tugas Shared. Unique key menahan duplikasi per tugas dan waktu jadwal.
- Pengingat terlambat dapat ditandai missed. Deadline terlewat dan tugas selesai ditampilkan secara terpisah.
- `notifications.php` menampilkan agenda mendatang, hasil reminder, overdue, dan tugas selesai yang memiliki jadwal.
- Aplikasi tidak mengirim Web Push saat browser tertutup. Itu membutuhkan backend push dan subscription yang belum tersedia.

## Keamanan dan batasan

- Login memverifikasi hash dengan `password_verify()`; query database memakai PDO prepared statements; output tampilan di-escape.
- Endpoint notifikasi POST dan Quick Switch memvalidasi token CSRF. POST aksi tugas saat ini belum memakai token CSRF khusus.
- Edit/hapus memeriksa pemilik di server. Anggota login dapat toggle Shared sesuai model aplikasi.
- Validasi upload/batas ukuran tersedia; deployment publik perlu validasi MIME server-side yang kuat dan pembatasan akses/eksekusi direktori upload.
- Kredensial `config.php` untuk lokal. Production membutuhkan secret environment/secret manager, akun DB hak minimum, HTTPS, cookie sesi aman, dan konfigurasi error/log.
- Quick Switch akun demo tidak ditujukan untuk production.

## Pemeliharaan

Perubahan skema dicatat di `data.sql` dan, untuk event reminder, `notification_store.php`. `data.sql` adalah seed/reset yang dapat menghapus data. Backup harus mencakup database dan direktori `uploads/` karena database hanya menyimpan path. Langkah setup ada di [SETUP.md](SETUP.md).

# Instalasi

[Kembali ke README](../README.md) · Terkait: [Arsitektur](ARCHITECTURE.md) · [Implementasi PWA](PWA_IMPLEMENTATION.md) · [Troubleshooting](TROUBLESHOOTING.md) · [Panduan setup](SETUP.md)

## Prasyarat

- PHP 8+ dengan PDO MySQL (`pdo_mysql`).
- MySQL atau MariaDB.
- Browser dengan dukungan JavaScript dan IndexedDB.
- Direktori `uploads/` yang dapat ditulisi untuk foto tugas.
- Localhost atau HTTPS untuk Service Worker dan notifikasi.

## Konfigurasi database

1. Jalankan MySQL/MariaDB.
2. Periksa dan atur nilai koneksi lokal di `config.php`.
3. Buat database dan skema dengan `data.sql`, atau gunakan inisialisasi lokal yang disediakan aplikasi.
4. Pastikan proses PHP dapat menulis ke `uploads/`.

> **Peringatan:** `data.sql` menjalankan `DROP TABLE` pada tabel aplikasi sebelum membuat ulang. Cadangkan database yang ingin dipertahankan. Jangan gunakan skrip seed/reset ini pada database produksi.

Konfigurasi repositori memuat nilai default untuk development. Jangan gunakan nilai tersebut untuk deployment publik dan jangan commit kredensial yang sebenarnya. Gunakan akun database dengan hak minimum dan konfigurasi rahasia khusus deployment.

## Menjalankan secara lokal

### PHP built-in server

Dari root proyek:

```powershell
php -S 127.0.0.1:8000
```

Buka [http://127.0.0.1:8000/login.php](http://127.0.0.1:8000/login.php). Built-in server ditujukan untuk development lokal, bukan produksi.

### XAMPP atau Laragon

Tempatkan proyek di web root (misalnya `C:\xampp\htdocs\tugas1` atau `C:\laragon\www\tugas1`), jalankan Apache dan MySQL, lalu buka `http://localhost/tugas1/login.php`. Sesuaikan nama folder pada URL bila perlu.

Gunakan akun demo seed hanya untuk demonstrasi lokal. Nama dan kredensialnya dijelaskan di [SETUP.md](SETUP.md); ganti atau hapus akun demo sebelum penggunaan di luar demonstrasi.

## Persiapan penggunaan offline

1. Buka dashboard saat online dan login.
2. Buka Personal dan Shared setidaknya sekali agar aplikasi menyimpan kedua snapshot akun aktif.
3. Tunggu sampai status penyimpanan snapshot selesai sebelum offline.
4. Pengingat dan riwayat yang tersimpan di server memerlukan koneksi. Perubahan offline tertunda tetap berada di profil browser ini sampai sinkronisasi berhasil.

Lihat [implementasi PWA](PWA_IMPLEMENTATION.md) untuk perilaku cache dan [Pengujian](TESTING.md) untuk prosedur verifikasi yang aman.

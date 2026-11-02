# Troubleshooting

[Kembali ke README](../README.md) · Terkait: [Instalasi](INSTALLATION.md) · [Implementasi PWA](PWA_IMPLEMENTATION.md) · [Pengujian](TESTING.md)

## Service Worker tidak aktif

- Gunakan `http://localhost` / `http://127.0.0.1` atau HTTPS.
- Periksa Console browser dan **Application → Service Workers** untuk error registrasi/instalasi.
- Pastikan semua path di `SHELL_FILES` tersedia dan berhasil dimuat. `cache.addAll()` menggagalkan instalasi jika resource precache gagal.
- Cocokkan scope worker dengan subdirektori deployment aplikasi. Jangan langsung unregister worker atau menghapus data situs sebagai langkah awal.

## Halaman offline muncul menggantikan dashboard

Ini perilaku yang diharapkan ketika navigasi PHP gagal: Service Worker tidak menyimpan respons PHP privat. Pengalaman offline disajikan oleh `offline.html` dan membaca snapshot task dari IndexedDB. Login dan buka Personal serta Shared saat online sebelum menguji mode offline.

Jika halaman terbuka tetapi task tidak ada, pastikan profil browser dan akun yang sama digunakan saat online untuk menyimpan snapshot. Penyimpanan lokal offline tidak dibagikan antarprofil/perangkat.

## Tombol instalasi menampilkan petunjuk manual

Tombol tetap terlihat. Prompt native hanya dapat dibuka dengan event `beforeinstallprompt` valid dari browser; aplikasi tidak boleh membuat event tersebut sendiri. Event dapat tidak tersedia karena kebijakan browser/platform, installability, status profil, atau aplikasi sudah terpasang. Periksa **Application → Manifest** dan Console; ketiadaan event saja tidak membuktikan aplikasi telah terpasang.

Di Microsoft Edge, jalur manualnya: menu (titik tiga) → **Apps** → **Install this site as an app**. Event asli pernah diamati dalam profil uji terisolasi, tetapi prompt native dan instalasi selesai masih belum terverifikasi pada bukti saat ini.

## Perubahan offline tidak tersinkron

- Pastikan browser online dan akun yang login sama dengan akun pembuat operasi antrean.
- Periksa status sync dashboard serta Network/Console browser untuk error dari `pwa_sync.php`.
- Sinkronisasi memerlukan snapshot/token CSRF yang tersimpan dari sesi dashboard online. Buka ulang dashboard saat online jika aplikasi menyatakan token belum tersedia.
- Operasi yang gagal sengaja dipertahankan di outbox untuk dicoba kembali. Jangan hapus data situs ketika perubahan masih tertunda.
- Aplikasi menyinkronkan dari halaman aktif; sinkronisasi tidak dijamin berjalan setelah semua halaman ditutup.

## Error database atau unggahan

- Pastikan MySQL/MariaDB berjalan dan nilai koneksi lokal di `config.php` benar.
- Pastikan PHP mengaktifkan `pdo_mysql` dan `uploads/` dapat ditulisi.
- Periksa log PHP/server untuk detail error. Jangan mengekspos kredensial produksi dalam log atau dokumentasi.
- Impor ulang `data.sql` menghapus lalu membuat ulang tabel aplikasi. Cadangkan dahulu; jangan gunakan sebagai langkah perbaikan/reset database yang datanya harus dipertahankan.

## Notifikasi tidak muncul

Notifikasi memerlukan localhost/HTTPS, izin browser, dan dukungan browser yang sesuai. Dashboard memeriksa pengingat ketika terbuka; Web Push setelah semua jendela aplikasi ditutup belum diimplementasikan. Record riwayat `triggered` mencatat pemanggilan notifikasi browser, bukan konfirmasi bahwa pengguna melihatnya.

## Masih terkendala?

Catat browser/versi, route, status online/offline, error Console, dan status Service Worker/Network terkait. Hindari membagikan cookie sesi, token CSRF, kredensial, atau data task pribadi. Lihat [Pengujian](TESTING.md) untuk kriteria bukti.

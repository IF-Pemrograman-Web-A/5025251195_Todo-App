# Implementasi PWA

[Kembali ke README](../README.md) · Terkait: [Arsitektur](ARCHITECTURE.md) · [Pengujian](TESTING.md) · [Screenshot](SCREENSHOTS.md) · [Troubleshooting](TROUBLESHOOTING.md)

## Manifest dan tombol instalasi

`manifest.webmanifest` mendefinisikan nama aplikasi, nama singkat, `start_url` (`./login.php`), scope (`./`), display mode `standalone`, warna tema/background, serta ikon PNG ukuran 192×192 dan 512×512. Ikon 512 juga dideklarasikan untuk keperluan maskable.

`index.php` mempertahankan satu tombol **Unduh / Instal Aplikasi** di dashboard. `pwa.js` mendengarkan event `beforeinstallprompt` asli dari browser, mencegah UI otomatis default, menyimpan event, dan hanya memanggil `prompt()` saat tombol diklik pengguna. Setelah digunakan, referensi event dihapus dan pilihan pengguna ditangani. Event `appinstalled` memperbarui status tetapi tidak menghapus tombol. Mode standalone dideteksi melalui `display-mode: standalone` (serta pemeriksaan legacy `navigator.standalone`).

Jika tidak ada event prompt valid, klik tombol menampilkan petunjuk manual Edge; ketiadaan event tidak dianggap sebagai bukti instalasi. Event asli pernah teramati pada profil uji Edge terisolasi. Tampilan prompt native dan penyelesaian instalasi sungguhan belum terverifikasi. Saat aplikasi terpasang dibuka dalam mode standalone, aplikasi dapat mengenali display mode tersebut; tab browser biasa tidak selalu dapat mengetahui status instalasi jika browser tidak menyediakan event atau pemeriksaan aplikasi terpasang yang didukung.

## Service Worker dan cache

Saat ini `service-worker.js` menggunakan nama cache `iron-citadel-shell-v14`. Saat instalasi, file shell yang dikonfigurasi dimasukkan ke cache sebelum memanggil `skipWaiting()`. Aktivasi menghapus cache lama dengan awalan `iron-citadel-shell-` dan mengambil alih client yang memenuhi syarat.

| Request | Behavior |
| --- | --- |
| Navigasi same-origin (`request.mode === "navigate"`) | Network first. Jika fetch gagal, gunakan `offline.html` dari cache. Respons navigasi tidak di-cache. |
| Aset shell same-origin yang terdaftar | Cache first; fetch saat cache miss dan simpan respons basic yang berhasil. |
| Request GET lain, request eksternal, dan request non-GET | Tidak ditangani oleh fetch handler Service Worker ini. |

Tidak ada strategi Stale-While-Revalidate. Halaman PHP dan respons API tidak di-cache sebagai aset shell; `offline.html` menyediakan UI offline dan membaca data tugas privat dari IndexedDB.

Daftar shell mencakup `offline.html`, manifest, berkas CSS/JavaScript statis, dan kedua ikon. Kegagalan `cache.addAll()` mencegah instalasi worker baru selesai, sehingga semua berkas terdaftar harus tersedia dan berhasil disajikan.

## IndexedDB dan antrean

`pwa-offline.js` membuka database `ironCitadelPwa` versi 1 dengan store `snapshots`, `outbox`, dan `images`. Kunci snapshot berbentuk `{userId}:personal` dan `{userId}:shared`; record outbox memiliki sequence auto-increment dan ID operasi UUID.

CRUD offline dimasukkan ke antrean. Toggle status memperbarui tugas pada snapshot yang tersedia dan menulis operasi `set_status` ke outbox dalam transaksi read/write yang sama. Perubahan tertunda digabungkan ke snapshot yang baru diambil agar snapshot server tidak langsung menghapus status lokal yang masih mengantre.

Saat event `online` terjadi, operasi akun aktif dikirim berurutan ke `pwa_sync.php`. Halaman juga mencoba sinkronisasi saat inisialisasi online. Jika respons berhasil, snapshot lokal diperbarui dan operasi dihapus; jika gagal, operasi tetap mengantre dan pesan status ditampilkan. Retry ini berbasis event/halaman aktif, bukan Background Sync.

`pwa_sync.php` menggunakan sesi dan token CSRF, memvalidasi operasi masuk, serta menyimpan respons sukses dengan kunci `(user_id, operation_id)` dalam `pwa_sync_operations` agar retry idempoten.

## Batasan

- Data offline bersifat lokal pada profil browser dan tidak dienkripsi oleh aplikasi ini.
- Penggunaan offline memerlukan login online sebelumnya dan snapshot yang tersimpan; autentikasi serta akses server tetap berlaku saat sinkronisasi.
- Pengingat/riwayat bukan fitur offline penuh.
- Prompt instalasi bergantung pada kebijakan browser, installability, status profil, dan platform; aplikasi tidak membuat prompt palsu.
- Uninstall atau perpindahan profil browser dapat membuat snapshot dan operasi tertunda tidak tersedia. Sinkronkan perubahan penting sebelum menghapus data situs.

# E03 - The Lost Cavern

## Identitas
- **Nama:** Dafa Dega Wijaya
- **NRP:** 5025251195
- **Kelas:** Pemrograman Web A

## Deskripsi
Todo App untuk mengelola daftar tugas dengan penyimpanan lokal, tema Light/Dark, foto tugas, tenggat, dan pengingat notifikasi.

## Fitur
- Menambah, mengedit, menghapus, dan menandai Todo selesai.
- Menyimpan data Todo, tenggat, waktu pengingat, status, foto, dan deskripsi foto di IndexedDB.
- Menyimpan preferensi tema Light/Dark di localStorage.
- Mengambil foto dengan kamera melalui Media Capture API atau memilih gambar dari perangkat.
- Menampilkan preview foto sebelum menyimpan; foto kamera ditangkap sebagai JPEG dan foto file dibatasi 5 MB.
- Mendaftarkan Service Worker untuk menampilkan notifikasi, mencegah notifikasi aktif ganda dengan tag, membatalkan notifikasi, dan menangani klik notifikasi.
- Menggunakan label form, status live region, fokus terlihat, dukungan reduced motion, dan forced-colors.

## Batasan Pengingat
Waktu pengingat disimpan bersama Todo di IndexedDB dan dijadwalkan ulang saat aplikasi dibuka atau dimuat ulang. Selama halaman aplikasi berjalan, timer halaman dapat mengirim pesan ke Service Worker untuk menampilkan notifikasi jika browser mendukungnya dan izin notifikasi diberikan.

Penjadwalan menggunakan `setTimeout()` pada halaman utama. Karena itu pengingat **tidak dijamin berjalan saat tab atau browser ditutup**. Service Worker di project ini menampilkan notifikasi setelah menerima pesan; ia tidak menjalankan jadwal sendiri di latar belakang. Pengingat yang terlewat saat aplikasi tidak aktif ditandai, tetapi tidak diputar ulang.

Kamera dan Service Worker memerlukan konteks aman seperti `localhost` atau HTTPS. Izin kamera dan notifikasi harus diberikan oleh pengguna dan dapat ditolak oleh browser.

## Menjalankan dan Menguji
Jalankan project melalui server lokal pada `localhost` atau host HTTPS, lalu:

1. Tambah Todo, refresh halaman, dan pastikan Todo tetap tersedia.
2. Ganti tema, refresh, dan pastikan preferensi Light/Dark tetap tersimpan.
3. Ambil foto dengan kamera atau pilih gambar, isi deskripsi foto, simpan, lalu refresh untuk memeriksa persistence dan alt text.
4. Beri izin notifikasi, buat pengingat pada waktu mendatang, dan biarkan halaman tetap terbuka sampai waktunya tiba.
5. Uji edit jadwal, tandai Todo selesai, hapus Todo, serta klik notifikasi untuk memeriksa pembatalan dan navigasi.
6. Uji seluruh alur dengan keyboard, screen reader, mode Light/Dark, forced-colors, dan prefers-reduced-motion.

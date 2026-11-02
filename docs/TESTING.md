# Pengujian dan scorecard bukti

[Kembali ke README](../README.md) · Terkait: [Implementasi PWA](PWA_IMPLEMENTATION.md) · [Slot screenshot](SCREENSHOTS.md) · [Troubleshooting](TROUBLESHOOTING.md)

Scorecard ini memisahkan bukti dari implementasi/browser dengan pengujian yang masih memerlukan observasi langsung. **PASS** berarti pemeriksaan yang disebutkan telah diamati; **PARTIAL** berarti hanya sebagian yang terbukti; **NOT VERIFIED** berarti bukti yang tersedia belum cukup. Screenshot tidak diklaim kecuali berkasnya benar-benar ada di repositori.

## Scorecard E05

| # | Pemeriksaan | Status | Bukti dan batasan |
| --- | --- | --- | --- |
| 1 | Manifest dan ikon | **PASS** | Manifest berhasil diparse di Edge tanpa error installability; field wajib, scope/start URL, dan ikon PNG 192/512 diperiksa. |
| 2 | Service Worker dan precache | **PASS** | Edge melaporkan worker aktif pada scope aplikasi; resource shell tersedia di Cache Storage. |
| 3 | Halaman offline dan fallback navigasi | **PASS** | Browser automation menguji navigasi offline dan fallback ke workspace lokal. |
| 4 | Strategi cache sesuai source | **PASS** | Pemeriksaan source memastikan navigasi network-first dengan fallback `offline.html`, aset shell cache-first, dan request lainnya tidak di-cache. |
| 5 | Event instalasi asli dan instalasi native | **PARTIAL** | Event `beforeinstallprompt` Edge asli diamati di profil terisolasi. Prompt native, persetujuan, dan instalasi selesai belum terbukti. |
| 6 | Operasi Todo offline | **PASS** | Browser automation menguji perubahan status offline dan tampilan workspace. |
| 7 | Persistensi snapshot saat pindah/refresh | **PASS** | Status tetap berubah dalam snapshot IndexedDB dan terlihat setelah berpindah Personal/Shared serta refresh offline. |
| 8 | Sinkronisasi outbox dan pencegahan duplikasi | **PASS** | Operasi tetap mengantre saat offline; setelah koneksi pulih, antrean kosong dan status server/snapshot cocok tanpa ID task duplikat pada skenario yang diuji. |
| 9 | Bukti screenshot DevTools | **NOT VERIFIED** | Tidak ada screenshot DevTools di repositori. Slot bukti dan instruksi tersedia di [Screenshot](SCREENSHOTS.md). |
| 10 | README dan tautan dokumentasi | **PASS** | README dan dokumentasi diperbarui; tautan internal diperiksa pada validasi perubahan ini. |

## Hasil khusus instalasi

| Pengujian | Hasil |
| --- | --- |
| Error manifest pada profil Edge terisolasi | Tidak ada yang dilaporkan browser. |
| Event `beforeinstallprompt` asli | Diamati; event berasal dari browser, bukan simulasi. |
| Prompt native Edge terlihat | Belum terverifikasi. |
| Instalasi PWA selesai | Belum terverifikasi. |
| Tombol tetap ada pada dashboard | Terverifikasi dari source: satu tombol tetap dirender dan tidak disembunyikan oleh alur instalasi. |
| Aplikasi berjalan standalone setelah instalasi nyata | Belum terverifikasi karena belum ada instalasi sungguhan yang selesai. |
| Fallback manual Edge | Terverifikasi dari source: tanpa prompt valid, tombol menampilkan menu → Apps → Install this site as an app. |

## Prosedur pengujian ulang yang aman

1. Gunakan task/akun pengujian atau database development terpisah. Jangan hapus storage browser atau reset database yang berisi data pengguna.
2. Periksa **Application → Manifest** dan **Application → Service Workers** di Edge DevTools.
3. Pastikan `offline.html` dan resource terdaftar ada dalam cache aktif `iron-citadel-shell-v14`.
4. Login saat online dan buka kedua workspace untuk menyimpan snapshot.
5. Aktifkan **Network → Offline**, ubah status task, pindah workspace, lalu refresh. Pastikan status tetap berubah dan jumlah operasi tertunda tidak nol.
6. Pulihkan koneksi dan pastikan operasi berhasil, antrean kosong, serta task tidak terduplikasi.
7. Uji instalasi native hanya pada profil browser terisolasi. Jangan hapus aplikasi atau data situs dari profil utama untuk membuat skenario uji.

Proyek ini tidak memiliki test runner otomatis khusus. Pemeriksaan sintaks source dan tautan dokumentasi dilakukan terpisah untuk perubahan dokumentasi ini; pemeriksaan tersebut tidak menggantikan pengujian instalasi native sungguhan.

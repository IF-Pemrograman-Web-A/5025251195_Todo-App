# Bukti screenshot

[Kembali ke README](../README.md) · Terkait: [Scorecard pengujian](TESTING.md) · [Implementasi PWA](PWA_IMPLEMENTATION.md) · [Folder screenshot](screenshots/README.md)

Daftar berikut adalah **slot bukti kosong**. Screenshot tidak dibuat-buat atau ditambahkan. Ambil layar aplikasi/browser yang asli, hapus atau samarkan data pribadi, lalu simpan setiap berkas di `docs/screenshots/` dengan nama yang tercantum.

| # | Nama berkas | Tampilan/tujuan | Prosedur pengambilan | Status |
| --- | --- | --- | --- | --- |
| 01 | `01-login.png` | Halaman login | Buka `login.php` sebelum login. | **BELUM DIUNGGAH** |
| 02 | `02-dashboard-personal.png` | Dashboard Personal | Login dan buka Personal dengan data representatif yang tidak sensitif. | **BELUM DIUNGGAH** |
| 03 | `03-workspace-shared.png` | Workspace Shared | Buka Shared dan tampilkan pemilik/pembuat task tanpa identitas pribadi. | **BELUM DIUNGGAH** |
| 04 | `04-task-create.png` | Form pembuatan task | Tampilkan form sebelum dikirim; jangan tampilkan foto pribadi. | **BELUM DIUNGGAH** |
| 05 | `05-task-edit-status.png` | Kontrol edit dan status | Tampilkan antarmuka edit/status task dengan data uji. | **BELUM DIUNGGAH** |
| 06 | `06-reminders.png` | Kontrol/status pengingat | Tampilkan UI pengingat dan status izin. | **BELUM DIUNGGAH** |
| 07 | `07-notification-history.png` | Riwayat notifikasi | Buka halaman riwayat notifikasi dengan data uji. | **BELUM DIUNGGAH** |
| 08 | `08-pwa-manifest.png` | Manifest DevTools | Application → Manifest, termasuk nama aplikasi, display mode, dan ikon. | **BELUM DIUNGGAH** |
| 09 | `09-service-worker-cache.png` | Worker dan cache shell | Application → Service Workers dan Cache Storage dengan cache aktif. | **BELUM DIUNGGAH** |
| 10 | `10-offline-workspace.png` | Workspace offline | Network Offline dengan workspace cache dan snapshot tersimpan terlihat. | **BELUM DIUNGGAH** |
| 11 | `11-offline-pending.png` | Operasi offline tertunda | Tampilkan perubahan yang benar-benar mengantre dan status yang berubah sebelum koneksi dipulihkan. | **BELUM DIUNGGAH** |
| 12 | `12-sync-complete.png` | Sinkronisasi selesai | Pulihkan koneksi dan tampilkan sinkronisasi berhasil/tidak ada operasi tertunda. | **BELUM DIUNGGAH** |

## Catatan pengambilan

- Gunakan data uji dan profil sementara. Jangan menyertakan password, token, konten task privat, atau foto pribadi.
- Untuk bukti offline, pertahankan profil dan data browser antara screenshot antrean tertunda dan sinkronisasi selesai.
- Prompt instalasi native dan PWA yang selesai dipasang tidak tercakup kecuali benar-benar dilakukan dan diambil di profil terisolasi.
- Jangan gunakan screenshot event simulasi sebagai bukti instalasi native browser.

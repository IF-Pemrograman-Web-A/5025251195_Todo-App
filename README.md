# E03 - The Lost Cavern

## Identitas

* **Nama:** Dafa Dega Wijaya
* **NRP:** 5025251195
* **Kelas:** Pemrograman Web A

## Deskripsi

Todo App merupakan aplikasi pengelola tugas berbasis web yang dikembangkan untuk memenuhi tugas E03 - The Lost Cavern pada mata kuliah Pemrograman Web.

Aplikasi ini menerapkan penyimpanan data menggunakan IndexedDB dan localStorage, pengambilan media melalui Media Capture API, Service Worker, notifikasi pengingat, serta prinsip aksesibilitas untuk mendukung pengalaman penggunaan yang lebih baik.

## Fitur Aplikasi

### 1. Manajemen Todo

* Menambahkan, mengedit, menghapus, dan menandai Todo sebagai selesai.
* Menyimpan judul, keterangan, status, tenggat, waktu pengingat, foto, dan deskripsi foto.
* Mempertahankan data Todo setelah halaman dimuat ulang.

### 2. Pemisahan Status Tugas

Todo ditampilkan dalam dua bagian untuk memudahkan pengguna mengelola pekerjaan.

* **Belum Selesai:** menampilkan tugas yang masih perlu dikerjakan.
* **Sudah Selesai:** menampilkan tugas yang telah diselesaikan.

Perubahan status checkbox akan memindahkan Todo ke bagian yang sesuai tanpa menghilangkan data tugas.

### 3. Penyimpanan Data

* **IndexedDB:** menyimpan data Todo, termasuk informasi tugas, status, tenggat, waktu pengingat, dan foto.
* **localStorage:** menyimpan preferensi tema Light Mode dan Dark Mode.

### 4. Media Capture API dan Foto

* Mengambil foto secara langsung melalui kamera perangkat.
* Memilih gambar dari file perangkat.
* Menampilkan preview foto sebelum Todo disimpan.
* Menggunakan Canvas API untuk mengambil hasil foto kamera dalam format JPEG.
* Menyimpan foto bersama data Todo di IndexedDB.
* Menyediakan deskripsi foto sebagai teks alternatif untuk mendukung aksesibilitas.

File gambar yang dipilih dari perangkat dibatasi hingga 5 MB.

### 5. Tenggat dan Notifikasi

* Menentukan tenggat penyelesaian tugas.
* Mengatur waktu pengingat untuk setiap Todo.
* Menggunakan Notifications API untuk menampilkan notifikasi browser.
* Menggunakan Service Worker untuk menangani tampilan notifikasi dan interaksi ketika notifikasi diklik.
* Mengelola notifikasi berdasarkan identitas Todo untuk membantu mencegah notifikasi aktif ganda.

### 6. Light Mode dan Dark Mode

Aplikasi menyediakan tema terang dan gelap dengan warna, kontras, serta tampilan yang disesuaikan agar tetap nyaman digunakan.

Preferensi tema disimpan di localStorage dan dipulihkan ketika halaman dibuka kembali.

### 7. Aksesibilitas

* Label form yang jelas.
* Navigasi keyboard menggunakan Tab dan Shift + Tab.
* Indikator fokus yang terlihat.
* Status aplikasi melalui live region.
* Teks alternatif untuk foto Todo.
* Dukungan preferensi gerakan melalui `prefers-reduced-motion`.
* Dukungan mode warna sistem melalui `forced-colors`.

## Implementasi Kriteria Tugas E03

| Kriteria                         | Implementasi                                                                                               |
| -------------------------------- | ---------------------------------------------------------------------------------------------------------- |
| Web Storage                      | IndexedDB untuk data Todo dan localStorage untuk preferensi tema.                                          |
| Media Capture API                | Pengambilan foto melalui kamera, preview, dan pemilihan gambar dari perangkat.                             |
| Service Worker dan Notifications | Service Worker, field waktu notifikasi, serta pengingat Todo melalui Notifications API.                    |
| Accessibility                    | Label form, fokus keyboard, nama aksesibel untuk kontrol, dan pengumuman status aplikasi.                  |
| Accessibility Best Practices     | Kontras warna, teks alternatif gambar, navigasi keyboard, serta dukungan reduced motion dan forced colors. |

## Teknologi yang Digunakan

* HTML5
* CSS3
* JavaScript
* IndexedDB
* localStorage
* Media Capture API
* Canvas API
* Service Worker API
* Notifications API

## Cara Menjalankan Aplikasi

1. Clone atau unduh repository ini ke komputer.
2. Buka folder project menggunakan Visual Studio Code.
3. Jalankan `index.html` melalui server lokal, misalnya ekstensi Live Server.
4. Buka alamat lokal yang disediakan server melalui browser.
5. Berikan izin kamera dan notifikasi apabila ingin menguji kedua fitur tersebut.

Kamera dan Service Worker memerlukan konteks yang mendukung API browser, seperti `localhost` atau HTTPS. Izin kamera dan notifikasi juga bergantung pada pengaturan browser pengguna.

## Demo dan Pengujian

Bagian ini menyediakan dokumentasi video untuk memperlihatkan penggunaan aplikasi dan pengujian aksesibilitas.

### 1. Video Pengujian Keseluruhan Aplikasi

Video ini memperlihatkan tampilan aplikasi dan pengujian fitur utama, meliputi pengelolaan Todo, perubahan status tugas, penyimpanan data, pergantian tema, serta fitur pendukung yang diuji.

**Video Demo:** 
https://github.com/user-attachments/assets/2fb9f1d3-22f1-4fdb-a281-538968d6c89d


### 2. Video Pengujian Aksesibilitas Keyboard

Video ini memperlihatkan pengujian navigasi menggunakan keyboard, terutama tombol Tab untuk berpindah antarkontrol, serta pemeriksaan indikator fokus pada elemen interaktif aplikasi.

**Video Aksesibilitas:** [Tonton Pengujian Aksesibilitas Keyboard](GANTI_DENGAN_URL_VIDEO_KEDUA)

### Skenario Pengujian

| No. | Skenario                                            | Tujuan                                                     |
| --- | --------------------------------------------------- | ---------------------------------------------------------- |
| 1   | Menambahkan Todo dan memuat ulang halaman           | Memastikan data tetap tersimpan di IndexedDB.              |
| 2   | Mengganti Light Mode dan Dark Mode                  | Memastikan preferensi tema tetap tersimpan.                |
| 3   | Menandai Todo selesai dan membatalkan centang       | Memastikan Todo berpindah ke bagian status yang sesuai.    |
| 4   | Mengedit dan menghapus Todo                         | Memastikan fungsi pengelolaan tugas berjalan.              |
| 5   | Mengambil atau memilih foto, lalu menyimpan Todo    | Memeriksa preview dan persistensi foto.                    |
| 6   | Mengatur waktu pengingat dan menunggu notifikasi    | Memeriksa fungsi notifikasi ketika halaman aplikasi aktif. |
| 7   | Menavigasi aplikasi menggunakan Tab dan Shift + Tab | Memeriksa urutan fokus dan indikator fokus keyboard.       |
| 8   | Menguji Light Mode dan Dark Mode                    | Memeriksa konsistensi tampilan dan keterbacaan.            |

## Batasan Pengingat

Waktu pengingat disimpan bersama data Todo di IndexedDB dan dijadwalkan kembali saat aplikasi dibuka atau dimuat ulang.

Pada implementasi saat ini, penjadwalan bergantung pada `setTimeout()` di halaman utama. Ketika waktu pengingat tiba dan halaman aplikasi masih berjalan, timer dapat mengirim pesan ke Service Worker untuk menampilkan notifikasi apabila browser mendukungnya dan izin notifikasi telah diberikan.

**Pengingat tidak dijamin muncul ketika tab atau browser ditutup.** Service Worker pada project ini menampilkan notifikasi setelah menerima pesan, tetapi tidak menjalankan jadwal pengingat secara mandiri di latar belakang. Pengingat yang terlewat saat aplikasi tidak aktif juga tidak diputar ulang.

## Penutup

Project E03 - The Lost Cavern ini menjadi implementasi pembelajaran mengenai penggunaan API web modern, pengelolaan data persisten, pengambilan media, notifikasi browser, serta penerapan aksesibilitas pada aplikasi Todo berbasis web.

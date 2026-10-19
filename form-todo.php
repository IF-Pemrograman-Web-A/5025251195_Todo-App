<?php
// form-todo.php - Komponen form tambah todo baru
$view = $_GET['view'] ?? 'personal';
?>
<aside class="right-panel" aria-labelledby="form-heading">
    <h2 id="form-heading">Add New Task</h2>

    <form id="todo-form" method="POST" action="todo_actions.php" enctype="multipart/form-data">
        <input type="hidden" name="action" value="create">
        <input type="hidden" name="return_view" value="<?= htmlspecialchars($view) ?>">
        <input type="hidden" name="foto_base64" id="foto-base64">

        <div class="form-group">
            <label for="judul">Judul</label>
            <input type="text" id="judul" name="judul" placeholder="Tulis tugas..." autocomplete="off" required>
        </div>

        <div class="form-group">
            <label for="keterangan">Keterangan</label>
            <textarea id="keterangan" name="keterangan" rows="3" placeholder="Detail tugas..."></textarea>
        </div>

        <div class="form-group">
            <label for="tipe">Kategori Menu</label>
            <select id="tipe" name="tipe" >
                <option value="personal" <?= $view === 'personal' ? 'selected' : '' ?>>Personal (Tugas Pribadi)</option>
                <option value="shared" <?= $view === 'shared' ? 'selected' : '' ?>>Shared (Tugas Organisasi)</option>
            </select>
        </div>

        <fieldset class="form-group">
            <legend>Foto Tugas</legend>

            <div class="photo-actions">
                <button type="button" id="tombol-kamera">Ambil Foto</button>
                <button type="button" id="tombol-file">Pilih dari File</button>
            </div>

            <input
                type="file"
                id="input-file"
                name="foto"
                accept="image/*"
                hidden
                aria-label="Pilih foto tugas dari file"
            >

            <video
                id="preview-kamera"
                autoplay
                playsinline
                muted
                hidden
                aria-label="Pratinjau kamera"
            ></video>

            <button
                type="button"
                id="tombol-jepret"
                hidden
            >
                Jepret Foto
            </button>

            <button
                type="button"
                id="tombol-stop-kamera"
                hidden
            >
                Hentikan Kamera
            </button>

            <canvas
                id="canvas-kamera"
                hidden
            ></canvas>

            <img
                id="preview-foto"
                hidden
                alt="Pratinjau foto tugas"
            >

            <button
                type="button"
                id="tombol-hapus-foto"
                class="delete-button"
                style="margin-top: 8px; width: 100%; display: none;"
            >
                Batal / Hapus Foto
            </button>
        </fieldset>

        <div class="form-group">
            <label for="deskripsi-foto">
                Deskripsi Foto (jika menggunakan foto)
            </label>
            <input
                type="text"
                id="deskripsi-foto"
                name="deskripsi_foto"
                maxlength="200"
                aria-describedby="bantuan-deskripsi-foto"
            >
            <small id="bantuan-deskripsi-foto">
                Wajib diisi jika foto digunakan. Teks ini menjadi deskripsi gambar.
            </small>
        </div>

        <div class="form-group">
            <label for="waktu-tenggat">
                Tenggat Tugas (Asia/Jakarta)
            </label>
            <input
                type="datetime-local"
                id="waktu-tenggat"
                name="tenggat"
                aria-describedby="bantuan-tenggat"
            >
            <small id="bantuan-tenggat">
                Waktu batas penyelesaian tugas, zona waktu Asia/Jakarta.
            </small>
        </div>

        <div class="form-group">
            <label for="waktu-notifikasi">
                Waktu Notifikasi (Asia/Jakarta)
            </label>
            <input
                type="datetime-local"
                id="waktu-notifikasi"
                name="waktu_notifikasi"
                aria-describedby="bantuan-notifikasi"
            >
            <small id="bantuan-notifikasi">
                Waktu lokal Asia/Jakarta. Aktifkan notifikasi browser agar pengingat dapat ditampilkan.
            </small>
        </div>

        <button type="submit" id="tombol-simpan">Simpan Tugas</button>
    </form>
</aside>

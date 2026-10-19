<?php
$is_owner = (int) $todo['user_id'] === (int) $_SESSION['user_id'];
$is_done = (int) $todo['selesai'] === 1;
$restoreThisTodo = is_array($editRestore ?? null) && (int) ($editRestore['todo_id'] ?? 0) === (int) $todo['id'];
$editValues = $restoreThisTodo && is_array($editRestore['values'] ?? null) ? $editRestore['values'] : [];
$editOpenOnLoad = $restoreThisTodo && !empty($editFormError);
$editTitleValue = (string) ($editValues['judul'] ?? $todo['judul']);
$editDescriptionValue = (string) ($editValues['keterangan'] ?? ($todo['keterangan'] ?? ''));
$editTypeValue = (string) ($editValues['tipe'] ?? $todo['tipe']);
if (!in_array($editTypeValue, ['personal', 'shared'], true)) $editTypeValue = $todo['tipe'];
$editDeadlineValue = array_key_exists('tenggat', $editValues)
    ? (string) $editValues['tenggat']
    : ($todo['tenggat'] ? date('Y-m-d\\TH:i', strtotime($todo['tenggat'])) : '');
$editReminderValue = array_key_exists('waktu_notifikasi', $editValues)
    ? (string) $editValues['waktu_notifikasi']
    : ($todo['waktu_notifikasi'] ? date('Y-m-d\\TH:i', strtotime($todo['waktu_notifikasi'])) : '');
$editPhotoDescriptionValue = (string) ($editValues['deskripsi_foto'] ?? ($todo['deskripsi_foto'] ?? ''));

$deadlineTimestamp = !empty($todo['tenggat']) ? strtotime($todo['tenggat']) : false;
if ($deadlineTimestamp === false) {
    $deadlineClass = 'badge--none';
    $deadlineText = 'Tanpa tenggat';
} elseif ($is_done) {
    $deadlineClass = 'badge--deadline';
    $deadlineText = 'Tenggat tercatat';
} elseif ($deadlineTimestamp <= time()) {
    $deadlineClass = 'badge--overdue';
    $deadlineText = 'Tenggat terlewat';
} else {
    $deadlineClass = 'badge--deadline';
    $deadlineText = 'Tenggat mendatang';
}

$reminderTimestamp = !empty($todo['waktu_notifikasi']) ? strtotime($todo['waktu_notifikasi']) : false;
if ($reminderTimestamp === false) {
    $reminderClass = 'badge--none';
    $reminderText = 'Tanpa pengingat';
} elseif ($is_done) {
    $reminderClass = 'badge--none';
    $reminderText = 'Pengingat nonaktif';
} elseif ($reminderTimestamp <= time()) {
    $reminderClass = 'badge--overdue';
    $reminderText = 'Waktu pengingat lewat';
} else {
    $reminderClass = 'badge--reminder';
    $reminderText = 'Ada pengingat';
}
?>
<tr class="task-item <?= $is_done ? 'done' : '' ?>"
    id="todo-<?= (int) $todo['id'] ?>"
    data-todo-id="<?= (int) $todo['id'] ?>"
    data-status="<?= $is_done ? 'completed' : 'pending' ?>"
    data-overdue="<?= (!$is_done && $deadlineTimestamp !== false && $deadlineTimestamp <= time()) ? 'true' : 'false' ?>"
    data-search="<?= htmlspecialchars(trim(($todo['judul'] ?? '') . ' ' . ($todo['keterangan'] ?? '')), ENT_QUOTES, 'UTF-8') ?>">
    <td class="task-toggle-cell">
        <form method="POST" action="todo_actions.php" class="toggle-form">
            <input type="hidden" name="action" value="toggle">
            <input type="hidden" name="todo_id" value="<?= (int) $todo['id'] ?>">
            <input type="hidden" name="return_view" value="<?= htmlspecialchars($current_view ?? ($_GET['view'] ?? 'personal'), ENT_QUOTES, 'UTF-8') ?>">
            <button type="submit" class="task-toggle" aria-label="<?= $is_done ? 'Tandai belum selesai' : 'Tandai selesai' ?>">
                <span aria-hidden="true"><?= $is_done ? '&#10003;' : '' ?></span>
            </button>
        </form>
    </td>
    <td class="task-main-cell">
        <span class="task-title"><?= htmlspecialchars($todo['judul'], ENT_QUOTES, 'UTF-8') ?></span>
        <?php if (!empty($todo['keterangan'])): ?>
            <small class="task-description"><?= htmlspecialchars($todo['keterangan'], ENT_QUOTES, 'UTF-8') ?></small>
        <?php endif; ?>
        <?php if (!empty($todo['foto'])): ?>
            <a class="task-photo-link" href="<?= htmlspecialchars($todo['foto'], ENT_QUOTES, 'UTF-8') ?>" aria-label="Lihat foto tugas">
                <img class="task-photo" src="<?= htmlspecialchars($todo['foto'], ENT_QUOTES, 'UTF-8') ?>"
                     alt="<?= htmlspecialchars($todo['deskripsi_foto'] ?: 'Foto tugas ' . $todo['judul'], ENT_QUOTES, 'UTF-8') ?>">
            </a>
        <?php endif; ?>
        <?php if (($current_view ?? ($_GET['view'] ?? 'personal')) === 'shared' && isset($todo['pembuat'])): ?>
            <small class="task-description task-creator">Dibuat oleh <?= htmlspecialchars($todo['pembuat'], ENT_QUOTES, 'UTF-8') ?></small>
        <?php endif; ?>
    </td>
    <td><span class="category-badge"><?= $todo['tipe'] === 'shared' ? 'Shared' : 'Personal' ?></span></td>
    <td class="task-meta-cell">
        <?php if ($deadlineTimestamp !== false): ?>
            <span class="cell-date"><?= htmlspecialchars(date('d M Y, H:i', $deadlineTimestamp), ENT_QUOTES, 'UTF-8') ?></span>
        <?php endif; ?>
        <span class="badge <?= $deadlineClass ?>" id="todo-deadline-<?= (int) $todo['id'] ?>"><?= $deadlineText ?></span>
    </td>
    <td class="task-meta-cell">
        <?php if ($reminderTimestamp !== false): ?>
            <span class="cell-date"><?= htmlspecialchars(date('d M Y, H:i', $reminderTimestamp), ENT_QUOTES, 'UTF-8') ?></span>
        <?php endif; ?>
        <span class="badge <?= $reminderClass ?>" id="todo-reminder-<?= (int) $todo['id'] ?>"><?= $reminderText ?></span>
    </td>
    <td><span class="badge <?= $is_done ? 'badge--done' : 'badge--progress' ?>" id="todo-status-<?= (int) $todo['id'] ?>"><?= $is_done ? 'Selesai' : 'Proses' ?></span></td>
    <td class="task-actions-cell">
        <div class="task-actions">
            <?php if ($is_owner): ?>
                <button type="button" class="edit-button" onclick="toggleEdit(<?= (int) $todo['id'] ?>)" aria-haspopup="dialog" aria-controls="edit-form-<?= (int) $todo['id'] ?>" aria-label="Edit tugas <?= htmlspecialchars($todo['judul'], ENT_QUOTES, 'UTF-8') ?>">Edit</button>
                <form method="POST" action="todo_actions.php" class="delete-form" onsubmit="return confirm('Apakah kamu yakin ingin menghapus tugas ini?')">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="todo_id" value="<?= (int) $todo['id'] ?>">
                    <input type="hidden" name="return_view" value="<?= htmlspecialchars($current_view ?? ($_GET['view'] ?? 'personal'), ENT_QUOTES, 'UTF-8') ?>">
                    <button type="submit" class="delete-button" aria-label="Hapus tugas <?= htmlspecialchars($todo['judul'], ENT_QUOTES, 'UTF-8') ?>">Hapus</button>
                </form>
            <?php endif; ?>
        </div>
    </td>
</tr>
<?php if ($is_owner): ?>
<tr class="edit-row" id="edit-row-<?= (int) $todo['id'] ?>" data-todo-id="<?= (int) $todo['id'] ?>">
    <td colspan="7">
        <div class="edit-modal-overlay" id="edit-form-<?= (int) $todo['id'] ?>" data-edit-modal data-todo-id="<?= (int) $todo['id'] ?>" aria-hidden="<?= $editOpenOnLoad ? 'false' : 'true' ?>" <?= $editOpenOnLoad ? '' : 'hidden' ?>>
            <section class="edit-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="edit-title-<?= (int) $todo['id'] ?>" aria-describedby="edit-description-<?= (int) $todo['id'] ?>" tabindex="-1">
                <header class="edit-modal-header">
                    <div class="edit-modal-heading">
                        <p class="edit-modal-eyebrow">EDIT TASK</p>
                        <h2 id="edit-title-<?= (int) $todo['id'] ?>">Perbarui detail dan status tugas Anda</h2>
                        <p class="edit-modal-task-name"><?= htmlspecialchars($editTitleValue, ENT_QUOTES, 'UTF-8') ?></p>
                    </div>
                    <button type="button" class="edit-modal-close" onclick="toggleEdit(<?= (int) $todo['id'] ?>)" aria-label="Tutup Edit Task">&times;</button>
                    <p class="visually-hidden" id="edit-description-<?= (int) $todo['id'] ?>">Edit detail tugas. Perubahan disimpan ke akun Anda.</p>
                </header>
                <?php if ($editOpenOnLoad): ?>
                    <p class="edit-modal-error" role="alert"><?= htmlspecialchars($editFormError, ENT_QUOTES, 'UTF-8') ?></p>
                <?php endif; ?>
                <form method="POST" action="todo_actions.php" enctype="multipart/form-data" class="edit-modal-form" data-edit-form>
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="todo_id" value="<?= (int) $todo['id'] ?>">
                    <input type="hidden" name="return_view" value="<?= htmlspecialchars($current_view ?? ($_GET['view'] ?? 'personal'), ENT_QUOTES, 'UTF-8') ?>">
                    <div class="edit-modal-body">
                        <div class="edit-task-status">
                            <label for="edit-status-<?= (int) $todo['id'] ?>">Status tugas</label>
                            <?php $editStatusValue = (string) ($editValues['selesai'] ?? (string) (int) $todo['selesai']); ?>
                            <?php if (!in_array($editStatusValue, ['0', '1'], true)) $editStatusValue = (string) (int) $todo['selesai']; ?>
                            <select id="edit-status-<?= (int) $todo['id'] ?>" name="selesai">
                                <option value="0" <?= $editStatusValue === '0' ? 'selected' : '' ?>>Proses (Belum selesai)</option>
                                <option value="1" <?= $editStatusValue === '1' ? 'selected' : '' ?>>Selesai</option>
                            </select>
                            <small>Pilih status tugas yang ingin disimpan.</small>
                        </div>
                        <div class="edit-modal-grid">
                            <div class="editing-field editing-field--full">
                                <label for="edit-judul-<?= (int) $todo['id'] ?>">Judul</label>
                                <input type="text" id="edit-judul-<?= (int) $todo['id'] ?>" name="judul" value="<?= htmlspecialchars($editTitleValue, ENT_QUOTES, 'UTF-8') ?>" required>
                            </div>
                            <div class="editing-field editing-field--full">
                                <label for="edit-keterangan-<?= (int) $todo['id'] ?>">Keterangan</label>
                                <textarea id="edit-keterangan-<?= (int) $todo['id'] ?>" name="keterangan" rows="4"><?= htmlspecialchars($editDescriptionValue, ENT_QUOTES, 'UTF-8') ?></textarea>
                            </div>
                            <div class="editing-field">
                                <label for="edit-tipe-<?= (int) $todo['id'] ?>">Kategori Menu</label>
                                <select id="edit-tipe-<?= (int) $todo['id'] ?>" name="tipe">
                                    <option value="personal" <?= $editTypeValue === 'personal' ? 'selected' : '' ?>>Personal (Pribadi)</option>
                                    <option value="shared" <?= $editTypeValue === 'shared' ? 'selected' : '' ?>>Shared (Organisasi)</option>
                                </select>
                            </div>
                            <div class="editing-field">
                                <label for="edit-tenggat-<?= (int) $todo['id'] ?>">Tenggat (Asia/Jakarta)</label>
                                <input type="datetime-local" id="edit-tenggat-<?= (int) $todo['id'] ?>" name="tenggat" value="<?= htmlspecialchars($editDeadlineValue, ENT_QUOTES, 'UTF-8') ?>">
                            </div>
                            <div class="editing-field">
                                <label for="edit-notifikasi-<?= (int) $todo['id'] ?>">Waktu Notifikasi (Asia/Jakarta)</label>
                                <input type="datetime-local" id="edit-notifikasi-<?= (int) $todo['id'] ?>" name="waktu_notifikasi" value="<?= htmlspecialchars($editReminderValue, ENT_QUOTES, 'UTF-8') ?>">
                            </div>
                            <div class="editing-field">
                                <label for="edit-foto-<?= (int) $todo['id'] ?>">Ganti Foto (File Handling)</label>
                                <?php if (!empty($todo['foto'])): ?>
                                    <span class="edit-current-photo">Foto saat ini tersimpan pada tugas.</span>
                                <?php endif; ?>
                                <input type="file" id="edit-foto-<?= (int) $todo['id'] ?>" name="foto" accept="image/*">
                            </div>
                            <div class="editing-field editing-field--full">
                                <label for="edit-alt-<?= (int) $todo['id'] ?>">Deskripsi Foto</label>
                                <input type="text" id="edit-alt-<?= (int) $todo['id'] ?>" name="deskripsi_foto" value="<?= htmlspecialchars($editPhotoDescriptionValue, ENT_QUOTES, 'UTF-8') ?>">
                            </div>
                        </div>
                    </div>
                    <footer class="edit-modal-footer">
                        <button type="button" class="edit-modal-cancel" onclick="toggleEdit(<?= (int) $todo['id'] ?>)">Batal</button>
                        <button type="submit" class="edit-modal-save">Simpan Perubahan</button>
                    </footer>
                </form>
            </section>
        </div>
    </td>
</tr>
<?php endif; ?>

<?php
date_default_timezone_set('Asia/Jakarta');
require_once 'config.php';
require_once 'auth.php';
requireLogin();

$user_id = $_SESSION['user_id'];
$action = $_POST['action'] ?? '';
$return_view = $_POST['return_view'] ?? ($_POST['tipe'] ?? 'personal');
$redirect_url = ($return_view === 'shared') ? 'index.php?view=shared' : 'index.php?view=personal';

function normalizeAppDateTime($value)
{
    if ($value === null || trim((string) $value) === '') {
        return null;
    }
    $value = trim((string) $value);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(?::\d{2})?$/', $value)) {
        throw new DomainException('Format tanggal dan waktu tidak valid.');
    }
    $format = strlen($value) === 16 ? '!Y-m-d\TH:i' : '!Y-m-d\TH:i:s';
    $date = DateTimeImmutable::createFromFormat($format, $value, new DateTimeZone('Asia/Jakarta'));
    $errors = DateTimeImmutable::getLastErrors();
    if (!$date || ($errors && ($errors['warning_count'] || $errors['error_count']))) {
        throw new DomainException('Tanggal dan waktu tidak valid.');
    }
    return $date->format('Y-m-d H:i:s');
}

function validateReminderInFuture($reminder)
{
    if ($reminder === null) return;
    $date = new DateTimeImmutable($reminder, new DateTimeZone('Asia/Jakarta'));
    if ($date <= new DateTimeImmutable('now', new DateTimeZone('Asia/Jakarta'))) {
        throw new DomainException('Waktu notifikasi harus berada di masa depan.');
    }
}


function redirectEditValidationError($todoId, $message, $redirectUrl)
{
    $_SESSION['edit_form_error'] = $message;
    $_SESSION['edit_restore'] = [
        'todo_id' => (int) $todoId,
        'values' => [
            'judul' => (string) ($_POST['judul'] ?? ''),
            'keterangan' => (string) ($_POST['keterangan'] ?? ''),
            'tipe' => (string) ($_POST['tipe'] ?? 'personal'),
            'selesai' => (string) ($_POST['selesai'] ?? '0'),
            'tenggat' => (string) ($_POST['tenggat'] ?? ''),
            'waktu_notifikasi' => (string) ($_POST['waktu_notifikasi'] ?? ''),
            'deskripsi_foto' => (string) ($_POST['deskripsi_foto'] ?? '')
        ]
    ];
    header("Location: $redirectUrl");
    exit;
}
switch ($action) {
    case 'create':
        $judul = trim($_POST['judul'] ?? '');
        $keterangan = trim($_POST['keterangan'] ?? '');
        $tenggatInput = $_POST['tenggat'] ?? null;
        $waktuNotifikasiInput = $_POST['waktu_notifikasi'] ?? null;
        $tipe = $_POST['tipe'] ?? 'personal';
        $deskripsi_foto = trim($_POST['deskripsi_foto'] ?? '');

        if (!$judul) {
            $_SESSION['flash_error'] = 'Judul tugas harus diisi.';
            header("Location: $redirect_url");
            exit;
        }

        try {
            $tenggat = normalizeAppDateTime($tenggatInput);
            $waktu_notifikasi = normalizeAppDateTime($waktuNotifikasiInput);
            validateReminderInFuture($waktu_notifikasi);
        } catch (DomainException $exception) {
            $_SESSION['flash_error'] = $exception->getMessage();
            header("Location: $redirect_url");
            exit;
        }

        // Handle camera photo (base64) OR uploaded file
        $foto_path = null;
        $upload_dir = 'uploads/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        if (!empty($_POST['foto_base64']) && strpos($_POST['foto_base64'], 'data:image/') === 0) {
            $base64_str = $_POST['foto_base64'];
            if (preg_match('/^data:image\/(\w+);base64,/', $base64_str, $matches)) {
                $raw = substr($base64_str, strpos($base64_str, ',') + 1);
                $decoded = base64_decode($raw);
                if ($decoded !== false && strlen($decoded) <= 5 * 1024 * 1024) {
                    $ext = strtolower($matches[1]);
                    if ($ext === 'jpeg') $ext = 'jpg';
                    $filename = 'todo_cam_' . $user_id . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                    $foto_path = $upload_dir . $filename;
                    file_put_contents($foto_path, $decoded);
                }
            }
        } elseif (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $file_type = $_FILES['foto']['type'];
            $file_size = $_FILES['foto']['size'];

            if (!in_array($file_type, $allowed_types)) {
                $_SESSION['flash_error'] = 'File harus berupa gambar (JPEG, PNG, GIF, WEBP).';
                header("Location: $redirect_url");
                exit;
            }

            if ($file_size > 5 * 1024 * 1024) {
                $_SESSION['flash_error'] = 'Ukuran foto maksimal 5 MB.';
                header("Location: $redirect_url");
                exit;
            }

            $extension = pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION);
            $filename = 'todo_' . $user_id . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
            $foto_path = $upload_dir . $filename;

            if (!move_uploaded_file($_FILES['foto']['tmp_name'], $foto_path)) {
                $_SESSION['flash_error'] = 'Gagal mengupload foto.';
                header("Location: $redirect_url");
                exit;
            }
        }

        $stmt = $pdo->prepare('
            INSERT INTO todos (user_id, judul, keterangan, foto, deskripsi_foto, tenggat, waktu_notifikasi, tipe)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ');
        $stmt->execute([$user_id, $judul, $keterangan, $foto_path, $deskripsi_foto, $tenggat, $waktu_notifikasi, $tipe]);

        $todo_id = $pdo->lastInsertId();

        // If shared, insert into shared_todos
        if ($tipe === 'shared') {
            $stmt = $pdo->prepare('INSERT INTO shared_todos (todo_id, shared_by) VALUES (?, ?)');
            $stmt->execute([$todo_id, $user_id]);
        }

        $_SESSION['flash_success'] = 'Tugas berhasil ditambahkan.';
        break;

    case 'update':
        $todo_id = (int)($_POST['todo_id'] ?? 0);
        $judul = trim($_POST['judul'] ?? '');
        $keterangan = trim($_POST['keterangan'] ?? '');
        $tenggatInput = $_POST['tenggat'] ?? null;
        $waktuNotifikasiInput = $_POST['waktu_notifikasi'] ?? null;
        $tipe = $_POST['tipe'] ?? 'personal';
        $selesaiInput = (string) ($_POST['selesai'] ?? '');

        if (!in_array($selesaiInput, ['0', '1'], true)) {
            redirectEditValidationError($todo_id, 'Status tugas tidak valid.', $redirect_url);
        }
        $selesai = (int) $selesaiInput;
        $deskripsi_foto = trim($_POST['deskripsi_foto'] ?? '');

        if (!$judul) {
            redirectEditValidationError($todo_id, 'Judul tugas harus diisi.', $redirect_url);
        }

        // Verify ownership
        $stmt = $pdo->prepare('SELECT * FROM todos WHERE id = ? AND user_id = ?');
        $stmt->execute([$todo_id, $user_id]);
        $todo = $stmt->fetch();

        if (!$todo) {
            $_SESSION['flash_error'] = 'Tugas tidak ditemukan atau Anda tidak memiliki akses.';
            header("Location: $redirect_url");
            exit;
        }

        try {
            $tenggat = normalizeAppDateTime($tenggatInput);
            $waktu_notifikasi = normalizeAppDateTime($waktuNotifikasiInput);
            validateReminderInFuture($waktu_notifikasi);
        } catch (DomainException $exception) {
            redirectEditValidationError($todo_id, $exception->getMessage(), $redirect_url);
        }

        $upload_dir = 'uploads/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        $foto_path = $todo['foto'];

        // Handle camera photo (base64) OR file upload
        if (!empty($_POST['foto_base64']) && strpos($_POST['foto_base64'], 'data:image/') === 0) {
            $base64_str = $_POST['foto_base64'];
            if (preg_match('/^data:image\/(\w+);base64,/', $base64_str, $matches)) {
                $raw = substr($base64_str, strpos($base64_str, ',') + 1);
                $decoded = base64_decode($raw);
                if ($decoded !== false && strlen($decoded) <= 5 * 1024 * 1024) {
                    $ext = strtolower($matches[1]);
                    if ($ext === 'jpeg') $ext = 'jpg';
                    $filename = 'todo_cam_' . $user_id . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                    $new_path = $upload_dir . $filename;
                    if (file_put_contents($new_path, $decoded)) {
                        if ($todo['foto'] && file_exists($todo['foto'])) {
                            unlink($todo['foto']);
                        }
                        $foto_path = $new_path;
                    }
                }
            }
        } elseif (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $file_type = $_FILES['foto']['type'];
            $file_size = $_FILES['foto']['size'];

            if (in_array($file_type, $allowed_types) && $file_size <= 5 * 1024 * 1024) {
                $extension = pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION);
                $filename = 'todo_' . $user_id . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
                $new_path = $upload_dir . $filename;

                if (move_uploaded_file($_FILES['foto']['tmp_name'], $new_path)) {
                    if ($todo['foto'] && file_exists($todo['foto'])) {
                        unlink($todo['foto']);
                    }
                    $foto_path = $new_path;
                }
            }
        }

        $stmt = $pdo->prepare('
            UPDATE todos SET judul = ?, keterangan = ?, foto = ?, deskripsi_foto = ?, tenggat = ?, waktu_notifikasi = ?, tipe = ?, selesai = ?
            WHERE id = ? AND user_id = ?
        ');
        $stmt->execute([$judul, $keterangan, $foto_path, $deskripsi_foto, $tenggat, $waktu_notifikasi, $tipe, $selesai, $todo_id, $user_id]);

        // Sync with shared_todos
        if ($tipe === 'shared') {
            $chk = $pdo->prepare('SELECT id FROM shared_todos WHERE todo_id = ?');
            $chk->execute([$todo_id]);
            if (!$chk->fetch()) {
                $stmt = $pdo->prepare('INSERT INTO shared_todos (todo_id, shared_by) VALUES (?, ?)');
                $stmt->execute([$todo_id, $user_id]);
            }
        } else {
            $stmt = $pdo->prepare('DELETE FROM shared_todos WHERE todo_id = ?');
            $stmt->execute([$todo_id]);
        }

        unset($_SESSION['edit_restore'], $_SESSION['edit_form_error']);
        $_SESSION['flash_success'] = 'Tugas berhasil diperbarui.';
        break;

    case 'toggle':
        $todo_id = (int)($_POST['todo_id'] ?? 0);

        $stmt = $pdo->prepare('SELECT selesai FROM todos WHERE id = ? AND (user_id = ? OR tipe = "shared")');
        $stmt->execute([$todo_id, $user_id]);
        $todo = $stmt->fetch();

        if ($todo) {
            $new_status = $todo['selesai'] ? 0 : 1;
            $stmt = $pdo->prepare('UPDATE todos SET selesai = ? WHERE id = ?');
            $stmt->execute([$new_status, $todo_id]);
            $_SESSION['flash_success'] = $new_status ? 'Tugas ditandai selesai.' : 'Tugas ditandai belum selesai.';
        }
        break;

    case 'delete':
        $todo_id = (int)($_POST['todo_id'] ?? 0);

        // Get photo path before delete
        $stmt = $pdo->prepare('SELECT foto FROM todos WHERE id = ? AND user_id = ?');
        $stmt->execute([$todo_id, $user_id]);
        $todo = $stmt->fetch();

        if ($todo) {
            // Delete photo file if exists
            if ($todo['foto'] && file_exists($todo['foto'])) {
                unlink($todo['foto']);
            }

            $stmt = $pdo->prepare('DELETE FROM todos WHERE id = ? AND user_id = ?');
            $stmt->execute([$todo_id, $user_id]);
            $_SESSION['flash_success'] = 'Tugas berhasil dihapus.';
        } else {
            $_SESSION['flash_error'] = 'Tugas tidak ditemukan atau Anda tidak memiliki akses.';
        }
        break;

    default:
        $_SESSION['flash_error'] = 'Aksi tidak valid.';
        break;
}

header("Location: $redirect_url");
exit;
?>

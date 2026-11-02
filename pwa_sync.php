<?php
date_default_timezone_set('Asia/Jakarta');
require_once 'config.php';
require_once 'auth.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

function pwaJsonResponse($status, array $payload)
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function pwaNormalizeDate($value)
{
    if ($value === null || trim((string) $value) === '') return null;
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

function pwaSavePhoto($data, $userId)
{
    if (!is_string($data) || !preg_match('/^data:image\/(?:jpeg|png|gif|webp);base64,/', $data, $matches)) {
        throw new DomainException('Data foto tidak valid.');
    }
    $contents = base64_decode(substr($data, strpos($data, ',') + 1), true);
    if ($contents === false || strlen($contents) > 5 * 1024 * 1024) {
        throw new DomainException('Foto tidak valid atau melebihi 5 MB.');
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->buffer($contents);
    $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];
    if (!isset($extensions[$mime])) {
        throw new DomainException('File harus berupa JPEG, PNG, GIF, atau WEBP.');
    }
    $directory = __DIR__ . '/uploads';
    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
        throw new RuntimeException('Direktori upload tidak dapat dibuat.');
    }
    $filename = 'todo_pwa_' . $userId . '_' . bin2hex(random_bytes(8)) . '.' . $extensions[$mime];
    $path = $directory . '/' . $filename;
    if (file_put_contents($path, $contents) === false) {
        throw new RuntimeException('Foto tidak dapat disimpan.');
    }
    return 'uploads/' . $filename;
}

function pwaFetchTodo(PDO $pdo, $todoId, $userId)
{
    $stmt = $pdo->prepare('
        SELECT t.*, u.nama_lengkap AS pembuat
        FROM todos t
        JOIN users u ON u.id = t.user_id
        WHERE t.id = ? AND (t.user_id = ? OR t.tipe = "shared")
        LIMIT 1
    ');
    $stmt->execute([$todoId, $userId]);
    $todo = $stmt->fetch();
    if (!$todo) return null;
    $todo['id'] = (int) $todo['id'];
    $todo['user_id'] = (int) $todo['user_id'];
    $todo['selesai'] = (int) $todo['selesai'];
    $todo['is_owner'] = $todo['user_id'] === (int) $userId;
    return $todo;
}

if (!isLoggedIn()) {
    pwaJsonResponse(401, ['ok' => false, 'message' => 'Sesi login tidak tersedia.']);
}

$userId = (int) $_SESSION['user_id'];
if (empty($_SESSION['pwa_sync_csrf'])) {
    $_SESSION['pwa_sync_csrf'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['pwa_sync_csrf'];
$createdPhotoPath = null;

try {
    $pdo->exec('
        CREATE TABLE IF NOT EXISTS pwa_sync_operations (
            user_id INT NOT NULL,
            operation_id CHAR(36) NOT NULL,
            response_json LONGTEXT DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (user_id, operation_id),
            KEY pwa_sync_created_at (created_at),
            CONSTRAINT pwa_sync_user_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ');

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $view = ($_GET['view'] ?? 'personal') === 'shared' ? 'shared' : 'personal';
        if ($view === 'shared') {
            $stmt = $pdo->prepare('
                SELECT t.*, u.nama_lengkap AS pembuat
                FROM todos t JOIN users u ON u.id = t.user_id
                WHERE t.tipe = "shared"
                ORDER BY t.selesai ASC, t.created_at DESC
            ');
            $stmt->execute();
        } else {
            $stmt = $pdo->prepare('
                SELECT t.*, u.nama_lengkap AS pembuat
                FROM todos t JOIN users u ON u.id = t.user_id
                WHERE t.user_id = ? AND t.tipe = "personal"
                ORDER BY t.selesai ASC, t.created_at DESC
            ');
            $stmt->execute([$userId]);
        }
        $todos = $stmt->fetchAll();
        foreach ($todos as &$todo) {
            $todo['id'] = (int) $todo['id'];
            $todo['user_id'] = (int) $todo['user_id'];
            $todo['selesai'] = (int) $todo['selesai'];
            $todo['is_owner'] = $todo['user_id'] === $userId;
        }
        unset($todo);
        pwaJsonResponse(200, [
            'ok' => true,
            'userId' => $userId,
            'view' => $view,
            'csrfToken' => $csrfToken,
            'todos' => $todos
        ]);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        pwaJsonResponse(405, ['ok' => false, 'message' => 'Method tidak didukung.']);
    }

    $payload = json_decode(file_get_contents('php://input'), true);
    if (!is_array($payload)) {
        pwaJsonResponse(400, ['ok' => false, 'message' => 'Permintaan JSON tidak valid.']);
    }
    if (!hash_equals($csrfToken, (string) ($payload['csrfToken'] ?? ''))) {
        pwaJsonResponse(403, ['ok' => false, 'message' => 'Token sesi tidak valid. Muat ulang dashboard saat online.']);
    }

    $operationId = (string) ($payload['operationId'] ?? '');
    if (!preg_match('/^[a-f0-9-]{36}$/i', $operationId)) {
        pwaJsonResponse(400, ['ok' => false, 'message' => 'ID operasi sinkronisasi tidak valid.']);
    }

    $pdo->beginTransaction();
    $claim = $pdo->prepare('
        INSERT IGNORE INTO pwa_sync_operations (user_id, operation_id)
        VALUES (?, ?)
    ');
    $claim->execute([$userId, $operationId]);
    if ($claim->rowCount() === 0) {
        $existing = $pdo->prepare('
            SELECT response_json FROM pwa_sync_operations
            WHERE user_id = ? AND operation_id = ? FOR UPDATE
        ');
        $existing->execute([$userId, $operationId]);
        $saved = $existing->fetchColumn();
        if ($saved !== false && $saved !== null) {
            $pdo->commit();
            echo $saved;
            exit;
        }
        $pdo->rollBack();
        pwaJsonResponse(409, ['ok' => false, 'message' => 'Operasi sinkronisasi sedang diproses. Coba lagi sebentar.']);
    }

    $action = (string) ($payload['action'] ?? '');
    $todoData = is_array($payload['todo'] ?? null) ? $payload['todo'] : [];
    $result = ['ok' => true, 'operationId' => $operationId, 'action' => $action];
    $oldPhoto = null;

    if ($action === 'create') {
        $title = trim((string) ($todoData['judul'] ?? ''));
        if ($title === '' || mb_strlen($title) > 255) {
            throw new DomainException('Judul tugas wajib diisi dan maksimal 255 karakter.');
        }
        $type = ($todoData['tipe'] ?? 'personal') === 'shared' ? 'shared' : 'personal';
        $deadline = pwaNormalizeDate($todoData['tenggat'] ?? null);
        $reminder = pwaNormalizeDate($todoData['waktu_notifikasi'] ?? null);
        $photoPath = null;
        if (!empty($todoData['foto_base64'])) {
            $photoPath = pwaSavePhoto($todoData['foto_base64'], $userId);
            $createdPhotoPath = $photoPath;
        }
        if ($photoPath && trim((string) ($todoData['deskripsi_foto'] ?? '')) === '') {
            throw new DomainException('Deskripsi foto wajib diisi jika tugas memiliki foto.');
        }
        $stmt = $pdo->prepare('
            INSERT INTO todos (user_id, judul, keterangan, foto, deskripsi_foto, tenggat, waktu_notifikasi, tipe, selesai)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ');
        $stmt->execute([
            $userId,
            $title,
            trim((string) ($todoData['keterangan'] ?? '')),
            $photoPath,
            trim((string) ($todoData['deskripsi_foto'] ?? '')),
            $deadline,
            $reminder,
            $type,
            !empty($todoData['selesai']) ? 1 : 0
        ]);
        $todoId = (int) $pdo->lastInsertId();
        if ($type === 'shared') {
            $share = $pdo->prepare('INSERT INTO shared_todos (todo_id, shared_by) VALUES (?, ?)');
            $share->execute([$todoId, $userId]);
        }
        $result['todo'] = pwaFetchTodo($pdo, $todoId, $userId);
    } elseif ($action === 'update') {
        $todoId = (int) ($todoData['id'] ?? 0);
        $current = $pdo->prepare('SELECT * FROM todos WHERE id = ? AND user_id = ? LIMIT 1');
        $current->execute([$todoId, $userId]);
        $existingTodo = $current->fetch();
        if (!$existingTodo) throw new DomainException('Tugas tidak ditemukan atau Anda tidak memiliki akses.');

        $changes = is_array($todoData['changes'] ?? null) ? $todoData['changes'] : [];
        $allowed = ['judul', 'keterangan', 'tenggat', 'waktu_notifikasi', 'tipe', 'selesai', 'deskripsi_foto'];
        $sets = [];
        $values = [];
        foreach ($changes as $field => $value) {
            if (!in_array($field, $allowed, true)) continue;
            if ($field === 'judul') {
                $value = trim((string) $value);
                if ($value === '' || mb_strlen($value) > 255) throw new DomainException('Judul tugas wajib diisi dan maksimal 255 karakter.');
            } elseif ($field === 'keterangan' || $field === 'deskripsi_foto') {
                $value = trim((string) $value);
                if ($field === 'deskripsi_foto' && mb_strlen($value) > 200) throw new DomainException('Deskripsi foto maksimal 200 karakter.');
            } elseif ($field === 'tenggat' || $field === 'waktu_notifikasi') {
                $value = pwaNormalizeDate($value);
            } elseif ($field === 'tipe') {
                if (!in_array($value, ['personal', 'shared'], true)) throw new DomainException('Kategori tugas tidak valid.');
            } elseif ($field === 'selesai') {
                if (!in_array((string) $value, ['0', '1'], true)) throw new DomainException('Status tugas tidak valid.');
                $value = (int) $value;
            }
            $sets[] = "`$field` = ?";
            $values[] = $value;
        }
        if (!empty($todoData['foto_base64'])) {
            if (trim((string) ($changes['deskripsi_foto'] ?? $existingTodo['deskripsi_foto'] ?? '')) === '') {
                throw new DomainException('Deskripsi foto wajib diisi jika tugas memiliki foto.');
            }
            $photoPath = pwaSavePhoto($todoData['foto_base64'], $userId);
            $createdPhotoPath = $photoPath;
            $sets[] = '`foto` = ?';
            $values[] = $photoPath;
            $oldPhoto = $existingTodo['foto'];
        }
        if ($sets) {
            $values[] = $todoId;
            $values[] = $userId;
            $stmt = $pdo->prepare('UPDATE todos SET ' . implode(', ', $sets) . ' WHERE id = ? AND user_id = ?');
            $stmt->execute($values);
        }
        $newType = $changes['tipe'] ?? $existingTodo['tipe'];
        if ($newType === 'shared') {
            $share = $pdo->prepare('INSERT IGNORE INTO shared_todos (todo_id, shared_by) VALUES (?, ?)');
            $share->execute([$todoId, $userId]);
        } elseif ($newType === 'personal') {
            $removeShare = $pdo->prepare('DELETE FROM shared_todos WHERE todo_id = ?');
            $removeShare->execute([$todoId]);
        }
        $result['todo'] = pwaFetchTodo($pdo, $todoId, $userId);
    } elseif ($action === 'set_status') {
        $todoId = (int) ($todoData['id'] ?? 0);
        $status = (string) ($todoData['selesai'] ?? '');
        if (!in_array($status, ['0', '1'], true)) throw new DomainException('Status tugas tidak valid.');
        $stmt = $pdo->prepare('UPDATE todos SET selesai = ? WHERE id = ? AND (user_id = ? OR tipe = "shared")');
        $stmt->execute([(int) $status, $todoId, $userId]);
        if (!$stmt->rowCount()) {
            $check = $pdo->prepare('SELECT id FROM todos WHERE id = ? AND (user_id = ? OR tipe = "shared")');
            $check->execute([$todoId, $userId]);
            if (!$check->fetch()) throw new DomainException('Tugas tidak ditemukan atau Anda tidak memiliki akses.');
        }
        $result['todo'] = pwaFetchTodo($pdo, $todoId, $userId);
    } elseif ($action === 'delete') {
        $todoId = (int) ($todoData['id'] ?? 0);
        $find = $pdo->prepare('SELECT foto FROM todos WHERE id = ? AND user_id = ? LIMIT 1');
        $find->execute([$todoId, $userId]);
        $existingTodo = $find->fetch();
        if (!$existingTodo) throw new DomainException('Tugas tidak ditemukan atau Anda tidak memiliki akses.');
        $oldPhoto = $existingTodo['foto'];
        $delete = $pdo->prepare('DELETE FROM todos WHERE id = ? AND user_id = ?');
        $delete->execute([$todoId, $userId]);
        $result['deletedId'] = $todoId;
    } else {
        throw new DomainException('Aksi sinkronisasi tidak dikenal.');
    }

    $response = json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $save = $pdo->prepare('
        UPDATE pwa_sync_operations SET response_json = ?
        WHERE user_id = ? AND operation_id = ?
    ');
    $save->execute([$response, $userId, $operationId]);
    $pdo->commit();
    $createdPhotoPath = null;
    if ($oldPhoto && is_file(__DIR__ . '/' . $oldPhoto)) {
        if (!unlink(__DIR__ . '/' . $oldPhoto)) {
            error_log('PWA synchronization could not remove old photo: ' . $oldPhoto);
        }
    }
    echo $response;
} catch (DomainException $exception) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    if ($createdPhotoPath && is_file(__DIR__ . '/' . $createdPhotoPath) && !unlink(__DIR__ . '/' . $createdPhotoPath)) {
        error_log('PWA synchronization could not remove uncommitted photo: ' . $createdPhotoPath);
    }
    pwaJsonResponse(422, ['ok' => false, 'message' => $exception->getMessage()]);
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    if ($createdPhotoPath && is_file(__DIR__ . '/' . $createdPhotoPath) && !unlink(__DIR__ . '/' . $createdPhotoPath)) {
        error_log('PWA synchronization could not remove uncommitted photo: ' . $createdPhotoPath);
    }
    error_log('PWA synchronization failed: ' . $exception->getMessage());
    pwaJsonResponse(500, ['ok' => false, 'message' => 'Sinkronisasi gagal diproses oleh server.']);
}

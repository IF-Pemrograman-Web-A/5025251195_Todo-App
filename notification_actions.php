<?php
date_default_timezone_set('Asia/Jakarta');
require_once 'config.php';
require_once 'auth.php';
require_once 'notification_store.php';
requireLogin();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

try {
    ensureNotificationEventsTable($pdo);
    $userId = (int) $_SESSION['user_id'];
    $zone = new DateTimeZone('Asia/Jakarta');
    $now = new DateTimeImmutable('now', $zone);
    $nowSql = $now->format('Y-m-d H:i:s');

    if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? '') === 'snapshot') {
        $stmt = $pdo->prepare('
            SELECT id, judul, tenggat, waktu_notifikasi, selesai, tipe
            FROM todos
            WHERE user_id = ? OR tipe = "shared"
            ORDER BY id
        ');
        $stmt->execute([$userId]);
        $todos = [];
        foreach ($stmt->fetchAll() as $todo) {
            $todos[] = [
                'id' => (int) $todo['id'],
                'judul' => $todo['judul'],
                'tenggat' => $todo['tenggat'],
                'tenggatMs' => appDateTimeToEpochMilliseconds($todo['tenggat']),
                'waktuNotifikasi' => $todo['waktu_notifikasi'],
                'waktuNotifikasiMs' => appDateTimeToEpochMilliseconds($todo['waktu_notifikasi']),
                'selesai' => (int) $todo['selesai'] === 1,
                'tipe' => $todo['tipe']
            ];
        }
        echo json_encode([
            'ok' => true,
            'recipientId' => $userId,
            'serverNowMs' => appDateTimeToEpochMilliseconds($nowSql),
            'todos' => $todos
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['ok' => false, 'message' => 'Method tidak didukung.']);
        exit;
    }

    $payload = json_decode(file_get_contents('php://input'), true);
    if (!is_array($payload)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'message' => 'Permintaan tidak valid.']);
        exit;
    }
    $sessionToken = $_SESSION['notification_csrf'] ?? '';
    $requestToken = (string) ($payload['csrfToken'] ?? '');
    if ($sessionToken === '' || !hash_equals($sessionToken, $requestToken)) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'message' => 'Token sesi tidak valid.']);
        exit;
    }

    $action = (string) ($payload['action'] ?? '');
    $todoId = (int) ($payload['todoId'] ?? 0);
    $scheduledFor = (string) ($payload['scheduledFor'] ?? '');
    if ($todoId < 1 || !preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $scheduledFor)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'message' => 'Identitas pengingat tidak valid.']);
        exit;
    }

    $schedule = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $scheduledFor, $zone);
    $dateErrors = DateTimeImmutable::getLastErrors();
    if (!$schedule || ($dateErrors && ($dateErrors['warning_count'] || $dateErrors['error_count']))) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'message' => 'Waktu pengingat tidak valid.']);
        exit;
    }

    $stmt = $pdo->prepare('
        SELECT id, judul, waktu_notifikasi, selesai
        FROM todos
        WHERE id = ? AND (user_id = ? OR tipe = "shared")
        LIMIT 1
    ');
    $stmt->execute([$todoId, $userId]);
    $todo = $stmt->fetch();
    if (!$todo || ($action !== 'result' && ((int) $todo['selesai'] === 1 || $todo['waktu_notifikasi'] !== $scheduledFor))) {
        echo json_encode(['ok' => true, 'result' => 'stale']);
        exit;
    }

    $identity = [$userId, $todoId, $scheduledFor];
    $recordMissed = function ($detail) use ($pdo, $identity, $todo, $nowSql) {
        $insert = $pdo->prepare('
            INSERT IGNORE INTO todo_notification_events
                (recipient_user_id, todo_id, todo_title, scheduled_for, status, processed_at, detail)
            VALUES (?, ?, ?, ?, "missed", ?, ?)
        ');
        $insert->execute([$identity[0], $identity[1], $todo['judul'], $identity[2], $nowSql, $detail]);
        $update = $pdo->prepare('
            UPDATE todo_notification_events
            SET status = "missed", processed_at = ?, detail = ?
            WHERE recipient_user_id = ? AND todo_id = ? AND scheduled_for = ? AND status = "processing"
        ');
        $update->execute([$nowSql, $detail, $identity[0], $identity[1], $identity[2]]);
        $current = $pdo->prepare('
            SELECT status, detail FROM todo_notification_events
            WHERE recipient_user_id = ? AND todo_id = ? AND scheduled_for = ?
        ');
        $current->execute($identity);
        $event = $current->fetch();
        return [
            'ok' => true,
            'result' => $event['status'] ?? 'missed',
            'detail' => $event['detail'] ?? $detail
        ];
    };

    if ($action === 'claim') {
        if ($schedule > $now) {
            echo json_encode(['ok' => true, 'result' => 'not_due']);
            exit;
        }
        if (empty($payload['permissionGranted'])) {
            echo json_encode($recordMissed('permission_unavailable'), JSON_UNESCAPED_UNICODE);
            exit;
        }
        if (($now->getTimestamp() - $schedule->getTimestamp()) > 86400) {
            echo json_encode($recordMissed('missed_while_inactive'), JSON_UNESCAPED_UNICODE);
            exit;
        }

        $pdo->beginTransaction();
        $insert = $pdo->prepare('
            INSERT IGNORE INTO todo_notification_events
                (recipient_user_id, todo_id, todo_title, scheduled_for, status, claim_at)
            VALUES (?, ?, ?, ?, "processing", ?)
        ');
        $insert->execute([$userId, $todoId, $todo['judul'], $scheduledFor, $nowSql]);
        if ($insert->rowCount() === 1) {
            $pdo->commit();
            echo json_encode(['ok' => true, 'result' => 'claimed']);
            exit;
        }

        $existingStmt = $pdo->prepare('
            SELECT status, claim_at, detail FROM todo_notification_events
            WHERE recipient_user_id = ? AND todo_id = ? AND scheduled_for = ? FOR UPDATE
        ');
        $existingStmt->execute($identity);
        $existing = $existingStmt->fetch();
        if ($existing && $existing['status'] === 'processing') {
            $claimAt = $existing['claim_at']
                ? new DateTimeImmutable($existing['claim_at'], $zone)
                : $now->modify('-1 minute');
            if (($now->getTimestamp() - $claimAt->getTimestamp()) > 45) {
                $retry = $pdo->prepare('
                    UPDATE todo_notification_events SET claim_at = ?, todo_title = ?, detail = NULL
                    WHERE recipient_user_id = ? AND todo_id = ? AND scheduled_for = ? AND status = "processing"
                ');
                $retry->execute([$nowSql, $todo['judul'], $identity[0], $identity[1], $identity[2]]);
                $pdo->commit();
                echo json_encode(['ok' => true, 'result' => 'claimed']);
                exit;
            }
        }
        if ($existing && $existing['status'] === 'missed' && ($now->getTimestamp() - $schedule->getTimestamp()) <= 86400) {
            $retry = $pdo->prepare('
                UPDATE todo_notification_events
                SET status = "processing", claim_at = ?, processed_at = NULL, detail = NULL, todo_title = ?
                WHERE recipient_user_id = ? AND todo_id = ? AND scheduled_for = ? AND status = "missed"
            ');
            $retry->execute([$nowSql, $todo['judul'], $identity[0], $identity[1], $identity[2]]);
            if ($retry->rowCount() === 1) {
                $pdo->commit();
                echo json_encode(['ok' => true, 'result' => 'claimed']);
                exit;
            }
        }

        $pdo->commit();
        echo json_encode(['ok' => true, 'result' => $existing['status'] ?? 'duplicate']);
        exit;
    }

    if ($action === 'result') {
        $result = ($payload['result'] ?? '') === 'triggered' ? 'triggered' : 'missed';
        $detail = preg_replace('/[^a-z0-9_-]/i', '', (string) ($payload['detail'] ?? ''));
        $stmt = $pdo->prepare('
            UPDATE todo_notification_events
            SET status = ?, processed_at = ?, detail = ?
            WHERE recipient_user_id = ? AND todo_id = ? AND scheduled_for = ? AND status = "processing"
        ');
        $stmt->execute([$result, $nowSql, $detail ?: null, $identity[0], $identity[1], $identity[2]]);
        $status = $result;
        if ($stmt->rowCount() !== 1) {
            $current = $pdo->prepare('
                SELECT status FROM todo_notification_events
                WHERE recipient_user_id = ? AND todo_id = ? AND scheduled_for = ?
            ');
            $current->execute($identity);
            $status = $current->fetchColumn() ?: 'missing';
        }
        echo json_encode(['ok' => true, 'result' => $status]);
        exit;
    }

    if ($action === 'missed') {
        if ($schedule > $now) {
            echo json_encode(['ok' => true, 'result' => 'not_due']);
            exit;
        }
        echo json_encode($recordMissed('missed_while_inactive'), JSON_UNESCAPED_UNICODE);
        exit;
    }

    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Aksi tidak dikenal.']);
} catch (Throwable $exception) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Notification processing failed: ' . $exception->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Pengingat tidak dapat diproses saat ini.']);
}
?>

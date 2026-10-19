<?php
date_default_timezone_set('Asia/Jakarta');
require_once 'config.php';
require_once 'auth.php';
require_once 'notification_store.php';
requireLogin();
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
$accountSwitched = !empty($_SESSION['account_switched']);
unset($_SESSION['account_switched']);
ensureNotificationEventsTable($pdo);

$userId = (int) $_SESSION['user_id'];
$zone = new DateTimeZone('Asia/Jakarta');
$now = new DateTimeImmutable('now', $zone);
$nowSql = $now->format('Y-m-d H:i:s');

$upcomingStmt = $pdo->prepare('
    SELECT t.id, t.judul, t.waktu_notifikasi, t.tipe
    FROM todos t
    LEFT JOIN todo_notification_events e
      ON e.recipient_user_id = ? AND e.todo_id = t.id AND e.scheduled_for = t.waktu_notifikasi
    WHERE (t.user_id = ? OR t.tipe = "shared")
      AND t.selesai = 0 AND t.waktu_notifikasi IS NOT NULL
      AND t.waktu_notifikasi > ? AND e.id IS NULL
    ORDER BY t.waktu_notifikasi ASC
');
$upcomingStmt->execute([$userId, $userId, $nowSql]);
$upcoming = $upcomingStmt->fetchAll();

$eventsStmt = $pdo->prepare('
    SELECT todo_id, todo_title, scheduled_for, status, processed_at, detail
    FROM todo_notification_events
    WHERE recipient_user_id = ?
    ORDER BY COALESCE(processed_at, scheduled_for) DESC, id DESC
    LIMIT 100
');
$eventsStmt->execute([$userId]);
$events = $eventsStmt->fetchAll();

$overdueStmt = $pdo->prepare('
    SELECT id, judul, tenggat, tipe
    FROM todos
    WHERE (user_id = ? OR tipe = "shared") AND selesai = 0
      AND tenggat IS NOT NULL AND tenggat <= ?
    ORDER BY tenggat DESC
');
$overdueStmt->execute([$userId, $nowSql]);
$overdue = $overdueStmt->fetchAll();

$completedStmt = $pdo->prepare('
    SELECT id, judul, tenggat, waktu_notifikasi, tipe
    FROM todos
    WHERE (user_id = ? OR tipe = "shared") AND selesai = 1
      AND (tenggat IS NOT NULL OR waktu_notifikasi IS NOT NULL)
    ORDER BY updated_at DESC
    LIMIT 100
');
$completedStmt->execute([$userId]);
$completed = $completedStmt->fetchAll();

function formatAppDate($value)
{
    if (!$value) return 'Waktu tidak tersedia';
    try {
        return (new DateTimeImmutable($value, new DateTimeZone('Asia/Jakarta')))->format('d M Y, H:i');
    } catch (Exception $exception) {
        return 'Waktu tidak valid';
    }
}

function notificationTaskUrl($todoId, $type)
{
    $view = $type === 'shared' ? 'shared' : 'personal';
    return 'index.php?view=' . $view . '#todo-' . (int) $todoId;
}

$displayName = $_SESSION['nama_lengkap'] ?? 'Pengguna';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - The Iron Citadel</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="dashboard.css">
</head>
<body data-account-switched="<?= $accountSwitched ? 'true' : 'false' ?>">
    <div class="app-layout">
        <?php include 'menu-bar.php'; ?>
        <div class="main-container">
        <main class="notifications-page">
            <header class="notifications-heading">
                <p class="eyebrow">THE IRON CITADEL</p>
                <h1>Notifications</h1>
                <p>Riwayat ini membedakan pemanggilan notifikasi, pengingat terlewat, deadline, dan tugas selesai.</p>
                <a href="index.php?view=personal">Kembali ke task dashboard</a>
            </header>

            <section class="notification-section" aria-labelledby="upcoming-heading">
                <h2 id="upcoming-heading">Upcoming</h2>
                <?php if (!$upcoming): ?><p class="notification-empty">Tidak ada pengingat yang akan datang.</p><?php endif; ?>
                <?php foreach ($upcoming as $item): ?>
                    <article class="notification-entry">
                        <div><strong><?= htmlspecialchars($item['judul'], ENT_QUOTES, 'UTF-8') ?></strong><span>Pengingat terjadwal · <?= htmlspecialchars(formatAppDate($item['waktu_notifikasi']), ENT_QUOTES, 'UTF-8') ?></span></div>
                        <a href="<?= htmlspecialchars(notificationTaskUrl($item['id'], $item['tipe']), ENT_QUOTES, 'UTF-8') ?>">Buka tugas</a>
                    </article>
                <?php endforeach; ?>
            </section>

            <section class="notification-section" aria-labelledby="processed-heading">
                <h2 id="processed-heading">Triggered / Missed</h2>
                <?php if (!$events): ?><p class="notification-empty">Belum ada pengingat yang diproses.</p><?php endif; ?>
                <?php foreach ($events as $event): ?>
                    <?php
                    $eventLabel = $event['status'] === 'triggered'
                        ? 'Triggered · pemanggilan browser berhasil'
                        : ($event['status'] === 'missed' ? 'Missed · notifikasi tidak berhasil dipanggil' : 'Sedang diproses');
                    $detailLabel = $event['detail'] === 'permission_unavailable'
                        ? 'Izin browser tidak tersedia'
                        : ($event['detail'] === 'missed_while_inactive'
                            ? 'Aplikasi tidak aktif melewati jendela pengiriman'
                            : ($event['detail'] === 'notification_api_failed' ? 'Browser gagal menampilkan notifikasi' : ''));
                    ?>
                    <article class="notification-entry">
                        <div>
                            <strong><?= htmlspecialchars($event['todo_title'], ENT_QUOTES, 'UTF-8') ?></strong>
                            <span><?= htmlspecialchars($eventLabel, ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars(formatAppDate($event['scheduled_for']), ENT_QUOTES, 'UTF-8') ?></span>
                            <?php if ($detailLabel): ?><small><?= htmlspecialchars($detailLabel, ENT_QUOTES, 'UTF-8') ?></small><?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </section>

            <section class="notification-section" aria-labelledby="overdue-heading">
                <h2 id="overdue-heading">Overdue</h2>
                <?php if (!$overdue): ?><p class="notification-empty">Tidak ada deadline yang terlewat.</p><?php endif; ?>
                <?php foreach ($overdue as $item): ?>
                    <article class="notification-entry">
                        <div><strong><?= htmlspecialchars($item['judul'], ENT_QUOTES, 'UTF-8') ?></strong><span>Deadline terlewat · <?= htmlspecialchars(formatAppDate($item['tenggat']), ENT_QUOTES, 'UTF-8') ?></span></div>
                        <a href="<?= htmlspecialchars(notificationTaskUrl($item['id'], $item['tipe']), ENT_QUOTES, 'UTF-8') ?>">Buka tugas</a>
                    </article>
                <?php endforeach; ?>
            </section>

            <section class="notification-section" aria-labelledby="completed-heading">
                <h2 id="completed-heading">Completed</h2>
                <?php if (!$completed): ?><p class="notification-empty">Tidak ada tugas selesai dengan jadwal yang tercatat.</p><?php endif; ?>
                <?php foreach ($completed as $item): ?>
                    <article class="notification-entry">
                        <div>
                            <strong><?= htmlspecialchars($item['judul'], ENT_QUOTES, 'UTF-8') ?></strong>
                            <span>Tugas selesai<?php if ($item['waktu_notifikasi']): ?> · pengingat <?= htmlspecialchars(formatAppDate($item['waktu_notifikasi']), ENT_QUOTES, 'UTF-8') ?><?php elseif ($item['tenggat']): ?> · deadline <?= htmlspecialchars(formatAppDate($item['tenggat']), ENT_QUOTES, 'UTF-8') ?><?php endif; ?></span>
                        </div>
                        <a href="<?= htmlspecialchars(notificationTaskUrl($item['id'], $item['tipe']), ENT_QUOTES, 'UTF-8') ?>">Buka tugas</a>
                    </article>
                <?php endforeach; ?>
            </section>
            <p class="notification-note">Triggered berarti browser menerima pemanggilan <code>showNotification()</code>; status ini tidak membuktikan notifikasi telah dilihat pengguna.</p>
        </main>
        </div>
    </div>
</body>
</html>

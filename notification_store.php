<?php
function ensureNotificationEventsTable(PDO $pdo)
{
    static $ready = false;
    if ($ready) return;

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS todo_notification_events (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            recipient_user_id INT NOT NULL,
            todo_id INT NOT NULL,
            todo_title VARCHAR(255) NOT NULL,
            scheduled_for DATETIME NOT NULL,
            status ENUM('processing', 'triggered', 'missed') NOT NULL,
            claim_at DATETIME DEFAULT NULL,
            processed_at DATETIME DEFAULT NULL,
            detail VARCHAR(80) DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY reminder_identity (recipient_user_id, todo_id, scheduled_for),
            KEY recipient_history (recipient_user_id, status, processed_at),
            CONSTRAINT notification_recipient_fk FOREIGN KEY (recipient_user_id)
                REFERENCES users(id) ON DELETE CASCADE,
            CONSTRAINT notification_todo_fk FOREIGN KEY (todo_id)
                REFERENCES todos(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $ready = true;
}

function appDateTimeToEpochMilliseconds($value)
{
    if (!$value) return null;
    try {
        $date = new DateTimeImmutable((string) $value, new DateTimeZone('Asia/Jakarta'));
        return ((int) $date->format('U')) * 1000;
    } catch (Exception $exception) {
        return null;
    }
}
?>

<?php
require_once 'config.php';
require_once 'auth.php';
requireLogin();

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$redirect = 'index.php?view=personal';
$fail = function ($message, $status = 400) use ($redirect) {
    http_response_code($status);
    $_SESSION['flash_error'] = $message;
    header('Location: ' . $redirect, true, 303);
    exit;
};

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $fail('Permintaan pergantian akun tidak valid.', 405);
}

$sessionToken = $_SESSION['account_switch_csrf'] ?? '';
$requestToken = (string) ($_POST['csrf_token'] ?? '');
if ($sessionToken === '' || !hash_equals($sessionToken, $requestToken)) {
    $fail('Sesi pergantian akun tidak valid. Muat ulang halaman dan coba lagi.', 403);
}

$currentStmt = $pdo->prepare('SELECT id, username FROM users WHERE id = ? LIMIT 1');
$currentStmt->execute([(int) $_SESSION['user_id']]);
$currentUser = $currentStmt->fetch();
if (!$currentUser || !isDemoSwitchUsername($currentUser['username'])) {
    $fail('Quick Switch hanya tersedia untuk akun demo yang diizinkan.', 403);
}

$targetUsername = trim((string) ($_POST['account_username'] ?? ''));
if (!isDemoSwitchUsername($targetUsername)) {
    $fail('Akun tujuan tidak termasuk akun demo yang diizinkan.', 403);
}

$targetStmt = $pdo->prepare('SELECT id, username, nama_lengkap FROM users WHERE username = ? LIMIT 1');
$targetStmt->execute([$targetUsername]);
$targetUser = $targetStmt->fetch();
if (!$targetUser) {
    $fail('Akun demo yang dipilih tidak tersedia.', 404);
}

if ((int) $targetUser['id'] !== (int) $currentUser['id']) {
    if (!session_regenerate_id(true)) {
        $fail('Session tidak dapat diperbarui. Silakan coba lagi.', 500);
    }
    $_SESSION['user_id'] = (int) $targetUser['id'];
    $_SESSION['username'] = $targetUser['username'];
    $_SESSION['nama_lengkap'] = $targetUser['nama_lengkap'];
    unset($_SESSION['notification_csrf'], $_SESSION['edit_restore'], $_SESSION['edit_form_error'], $_SESSION['flash_error'], $_SESSION['flash_success']);
    $_SESSION['account_switch_csrf'] = bin2hex(random_bytes(32));
    $_SESSION['account_switched'] = true;
    $_SESSION['flash_success'] = 'Berhasil beralih ke akun ' . $targetUser['nama_lengkap'] . '.';
} else {
    $_SESSION['flash_success'] = 'Akun ' . $targetUser['nama_lengkap'] . ' sudah aktif.';
}

header('Location: ' . $redirect, true, 303);
exit;
?>
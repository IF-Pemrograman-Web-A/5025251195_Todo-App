<?php
session_start();

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

function getCurrentUser($pdo) {
    if (!isLoggedIn()) {
        return null;
    }
    $stmt = $pdo->prepare('SELECT id, username, nama_lengkap FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch();
}

// Quick Switch is a classroom demonstration feature, limited to seeded demo usernames.
function demoSwitchUsernames() {
    return ['dafa', 'teman'];
}

function isDemoSwitchUsername($username) {
    return in_array((string) $username, demoSwitchUsernames(), true);
}

function getDemoSwitchAccounts($pdo) {
    $allowed = demoSwitchUsernames();
    $placeholders = implode(', ', array_fill(0, count($allowed), '?'));
    $stmt = $pdo->prepare("SELECT id, username, nama_lengkap FROM users WHERE username IN ($placeholders) ORDER BY id");
    $stmt->execute($allowed);
    return $stmt->fetchAll();
}
?>

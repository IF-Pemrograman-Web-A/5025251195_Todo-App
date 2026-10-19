<?php
// config.php - Konfigurasi koneksi database MySQL dengan PDO
$host = 'localhost';
$dbname = 'todo_app';
$username = 'root';
$password = '';

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch (PDOException $e) {
    // Jika database belum dibuat, coba inisialisasi otomatis dari data.sql jika MySQL aktif
    try {
        $pdoInit = new PDO("mysql:host=$host;charset=utf8mb4", $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);
        $pdoInit->exec("CREATE DATABASE IF NOT EXISTS `$dbname` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        
        $pdo = new PDO(
            "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
            $username,
            $password,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]
        );
        
        // Cek apakah tabel users sudah ada
        $check = $pdo->query("SHOW TABLES LIKE 'users'")->fetch();
        if (!$check && file_exists(__DIR__ . '/data.sql')) {
            $sql = file_get_contents(__DIR__ . '/data.sql');
            $pdo->exec($sql);
        }
    } catch (PDOException $innerException) {
        die('<div style="font-family:sans-serif;max-width:600px;margin:50px auto;padding:24px;border:1px solid #d9aaa4;background:#fff9f7;border-radius:10px;">'
            . '<h2 style="color:#8f3933;margin-top:0;">Koneksi Database Gagal</h2>'
            . '<p>' . htmlspecialchars($innerException->getMessage()) . '</p>'
            . '<p>Pastikan MySQL server (XAMPP / Laragon / MariaDB) sudah aktif, lalu impor file <code>data.sql</code> ke database <code>todo_app</code>.</p>'
            . '</div>');
    }
}
?>

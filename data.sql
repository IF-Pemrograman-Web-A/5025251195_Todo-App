-- ============================================
-- Database: todo_app
-- Todo App - Dafa Dega Wijaya (5025251195)
-- ============================================

CREATE DATABASE IF NOT EXISTS `todo_app` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `todo_app`;

-- ============================================
-- Tabel: users
-- ============================================
DROP TABLE IF EXISTS `todo_notification_events`;
DROP TABLE IF EXISTS `shared_todos`;
DROP TABLE IF EXISTS `todos`;
DROP TABLE IF EXISTS `users`;

CREATE TABLE `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(50) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `nama_lengkap` VARCHAR(100) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- Tabel: todos
-- ============================================
CREATE TABLE `todos` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `judul` VARCHAR(255) NOT NULL,
    `keterangan` TEXT,
    `foto` VARCHAR(500) DEFAULT NULL,
    `deskripsi_foto` VARCHAR(200) DEFAULT NULL,
    `tenggat` DATETIME DEFAULT NULL,
    `waktu_notifikasi` DATETIME DEFAULT NULL,
    `selesai` TINYINT(1) DEFAULT 0,
    `tipe` ENUM('personal', 'shared') DEFAULT 'personal',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `todo_notification_events` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `recipient_user_id` INT NOT NULL,
    `todo_id` INT NOT NULL,
    `todo_title` VARCHAR(255) NOT NULL,
    `scheduled_for` DATETIME NOT NULL,
    `status` ENUM('processing', 'triggered', 'missed') NOT NULL,
    `claim_at` DATETIME DEFAULT NULL,
    `processed_at` DATETIME DEFAULT NULL,
    `detail` VARCHAR(80) DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `reminder_identity` (`recipient_user_id`, `todo_id`, `scheduled_for`),
    KEY `recipient_history` (`recipient_user_id`, `status`, `processed_at`),
    FOREIGN KEY (`recipient_user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`todo_id`) REFERENCES `todos`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- Tabel: shared_todos (untuk todo yang dibagikan)
-- ============================================
CREATE TABLE `shared_todos` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `todo_id` INT NOT NULL,
    `shared_by` INT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`todo_id`) REFERENCES `todos`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`shared_by`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- Data Dummy: Users
-- Catatan: Di production, gunakan password_hash()
-- ============================================
INSERT INTO `users` (`username`, `password`, `nama_lengkap`) VALUES
('dafa', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Dafa Dega Wijaya'),
('teman', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Teman Kuliah');
-- Password untuk kedua user: 'password'

-- ============================================
-- Data Dummy: Todos
-- ============================================
INSERT INTO `todos` (`user_id`, `judul`, `keterangan`, `tenggat`, `selesai`, `tipe`) VALUES
(1, 'Mengerjakan Tugas PWEB', 'Membuat Todo App dengan PHP dan MySQL', '2026-11-01 23:59:00', 0, 'personal'),
(1, 'Belajar untuk UTS Basis Data', 'Baca materi normalisasi dan ER diagram', '2026-10-25 08:00:00', 0, 'personal'),
(1, 'Presentasi Proyek Kelompok', 'Siapkan slide dan demo untuk presentasi proyek akhir', '2026-10-28 10:00:00', 0, 'shared'),
(1, 'Membeli Buku Referensi', 'Beli buku Pemrograman Web di toko buku kampus', NULL, 1, 'personal'),
(2, 'Review Code Partner', 'Review pull request untuk fitur login', '2026-10-22 17:00:00', 0, 'personal'),
(2, 'Meeting Tim Proyek', 'Diskusi pembagian tugas sprint berikutnya', '2026-10-21 14:00:00', 0, 'shared'),
(1, 'Deploy Website ke Server', 'Upload file ke hosting dan konfigurasi domain', '2026-11-05 12:00:00', 0, 'shared'),
(2, 'Dokumentasi API', 'Tulis dokumentasi endpoint REST API', '2026-10-30 23:59:00', 0, 'personal');

-- ============================================
-- Data Dummy: Shared Todos
-- ============================================
INSERT INTO `shared_todos` (`todo_id`, `shared_by`) VALUES
(3, 1),
(6, 2),
(7, 1);

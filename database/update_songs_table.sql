-- Update tabel songs untuk menambahkan kolom play_count dan is_favorite
-- Jalankan ini di phpMyAdmin tab SQL

USE `musik_interaktif`;

-- Tambah kolom play_count jika belum ada
ALTER TABLE `songs` 
ADD COLUMN IF NOT EXISTS `play_count` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `file_path`;

-- Tambah kolom is_favorite
ALTER TABLE `songs` 
ADD COLUMN IF NOT EXISTS `is_favorite` TINYINT(1) NOT NULL DEFAULT 0 AFTER `play_count`;

-- Verifikasi struktur tabel
DESCRIBE `songs`;

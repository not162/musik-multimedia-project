-- SQL untuk menambahkan fitur favorit
-- Jalankan ini di HeidiSQL atau phpMyAdmin

USE `musik_interaktif`;

-- Tambahkan kolom is_favorite ke tabel songs
ALTER TABLE `songs` 
ADD COLUMN `is_favorite` TINYINT(1) NOT NULL DEFAULT 0 AFTER `play_count`;

-- Optional: Set beberapa lagu demo sebagai favorit untuk testing
-- UPDATE `songs` SET `is_favorite` = 1 WHERE `id` IN (1, 2) LIMIT 2;

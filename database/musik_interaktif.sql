-- Database: musik_interaktif (MySQL / MariaDB)
-- Alternatif impor manual lewat HeidiSQL / phpMyAdmin.
-- JANGAN dipakai bersamaan dengan "php artisan migrate" (pilih salah satu).

CREATE DATABASE IF NOT EXISTS `musik_interaktif` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `musik_interaktif`;

DROP TABLE IF EXISTS `playlist_song`;
DROP TABLE IF EXISTS `playlists`;
DROP TABLE IF EXISTS `songs`;

CREATE TABLE `songs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `artist` varchar(255) DEFAULT NULL,
  `genre` varchar(50) DEFAULT NULL,
  `duration` int unsigned NOT NULL DEFAULT 0,
  `file_path` varchar(255) NOT NULL,
  `play_count` int unsigned NOT NULL DEFAULT 0,
  `is_favorite` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `playlists` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `playlist_song` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `playlist_id` bigint unsigned NOT NULL,
  `song_id` bigint unsigned NOT NULL,
  `position` int unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `playlist_song_playlist_id_song_id_unique` (`playlist_id`,`song_id`),
  KEY `playlist_song_song_id_foreign` (`song_id`),
  CONSTRAINT `playlist_song_playlist_id_foreign` FOREIGN KEY (`playlist_id`) REFERENCES `playlists` (`id`) ON DELETE CASCADE,
  CONSTRAINT `playlist_song_song_id_foreign` FOREIGN KEY (`song_id`) REFERENCES `songs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `songs` (`id`,`title`,`artist`,`genre`,`duration`,`file_path`,`play_count`,`created_at`,`updated_at`) VALUES
(1,'Fokus Pagi','Studio Kampus','Lofi',52,'music/fokus-pagi.mp3',0,NOW(),NOW()),
(2,'Malam di Perpustakaan','Studio Kampus','Ambient',51,'music/malam-di-perpustakaan.mp3',0,NOW(),NOW()),
(3,'Kode Mengalir','Studio Kampus','Elektronik',54,'music/kode-mengalir.mp3',0,NOW(),NOW()),
(4,'Jeda Sore','Studio Kampus','Akustik',46,'music/jeda-sore.mp3',0,NOW(),NOW()),
(5,'Semangat Praktikum','Studio Kampus','Pop',50,'music/semangat-praktikum.mp3',0,NOW(),NOW());

INSERT INTO `playlists` (`id`,`name`,`description`,`created_at`,`updated_at`) VALUES
(1,'Teman Belajar','Tenang untuk membaca dan menulis kode',NOW(),NOW()),
(2,'Semangat Ngoding','Tempo lebih cepat',NOW(),NOW());

INSERT INTO `playlist_song` (`playlist_id`,`song_id`,`position`,`created_at`,`updated_at`) VALUES
(1,1,1,NOW(),NOW()),(1,2,2,NOW(),NOW()),(1,4,3,NOW(),NOW()),
(2,3,1,NOW(),NOW()),(2,5,2,NOW(),NOW());

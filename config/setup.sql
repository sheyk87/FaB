-- =====================================================================
-- Database Setup Script: Flesh and Blood TCG Sandbox Arena
-- Translated from config/setup.php
-- Compatible with MySQL 5.7+ / MySQL 8.0+ / MariaDB (XAMPP)
-- =====================================================================

-- 1. Base de Datos (Opcional en Localhost / Omitir en Hosting Compartido)
-- Si estás en XAMPP Localhost y la base no existe, descomenta las 2 líneas siguientes:
-- CREATE DATABASE IF NOT EXISTS `fab_tcg` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- USE `fab_tcg`;
-- (En hostings como InfinityFree o cPanel, selecciona tu base de datos asignada ej: if0_42649501_fab y ejecuta a partir de aquí)

-- =====================================================================
-- 2. Creación de Tablas
-- =====================================================================

-- Tabla: users
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(50) NOT NULL UNIQUE,
    `password_hash` VARCHAR(255) NOT NULL,
    `display_name` VARCHAR(100) NOT NULL,
    `avatar` VARCHAR(255) DEFAULT 'hero_default',
    `is_online` TINYINT(1) DEFAULT 0,
    `last_seen` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_online (`is_online`, `last_seen`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla: sets
CREATE TABLE IF NOT EXISTS `sets` (
    `id` VARCHAR(20) PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `card_count` INT UNSIGNED DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla: cards
CREATE TABLE IF NOT EXISTS `cards` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `unique_id` VARCHAR(64) NOT NULL UNIQUE,
    `name` VARCHAR(150) NOT NULL,
    `color` VARCHAR(20) DEFAULT NULL,
    `pitch` TINYINT DEFAULT NULL,
    `cost` VARCHAR(10) DEFAULT NULL,
    `power` VARCHAR(10) DEFAULT NULL,
    `defense` VARCHAR(10) DEFAULT NULL,
    `health` VARCHAR(10) DEFAULT NULL,
    `intelligence` VARCHAR(10) DEFAULT NULL,
    `arcane` VARCHAR(10) DEFAULT NULL,
    `type_text` VARCHAR(150) DEFAULT NULL,
    `functional_text` TEXT DEFAULT NULL,
    `functional_text_plain` TEXT DEFAULT NULL,
    `types_json` TEXT DEFAULT NULL,
    `keywords_json` TEXT DEFAULT NULL,
    `set_id` VARCHAR(20) DEFAULT NULL,
    `collector_number` VARCHAR(30) DEFAULT NULL,
    `rarity` VARCHAR(10) DEFAULT NULL,
    `image_url` VARCHAR(500) DEFAULT NULL,
    `is_hero` TINYINT(1) DEFAULT 0,
    `is_weapon` TINYINT(1) DEFAULT 0,
    `is_equipment` TINYINT(1) DEFAULT 0,
    `is_action` TINYINT(1) DEFAULT 0,
    `is_attack` TINYINT(1) DEFAULT 0,
    `is_defense_reaction` TINYINT(1) DEFAULT 0,
    `is_instant` TINYINT(1) DEFAULT 0,
    `is_item` TINYINT(1) DEFAULT 0,
    `is_aura` TINYINT(1) DEFAULT 0,
    `blitz_legal` TINYINT(1) DEFAULT 1,
    `cc_legal` TINYINT(1) DEFAULT 1,
    `commoner_legal` TINYINT(1) DEFAULT 0,
    INDEX idx_card_name (`name`),
    INDEX idx_card_pitch (`pitch`),
    INDEX idx_card_set (`set_id`),
    INDEX idx_card_hero (`is_hero`),
    INDEX idx_card_equip (`is_equipment`),
    INDEX idx_card_weapon (`is_weapon`),
    INDEX idx_card_rarity (`rarity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla: decks
CREATE TABLE IF NOT EXISTS `decks` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `hero_card_id` VARCHAR(64) NOT NULL,
    `hero_name` VARCHAR(150) NOT NULL,
    `hero_image_url` VARCHAR(500) DEFAULT NULL,
    `format` ENUM('Blitz', 'Classic Constructed', 'Commoner') DEFAULT 'Blitz',
    `description` TEXT DEFAULT NULL,
    `cards_json` LONGTEXT NOT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX idx_deck_user (`user_id`),
    INDEX idx_deck_format (`format`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla: matchmaking_queue
CREATE TABLE IF NOT EXISTS `matchmaking_queue` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL UNIQUE,
    `deck_id` INT UNSIGNED NOT NULL,
    `format` VARCHAR(30) DEFAULT 'Blitz',
    `status` ENUM('waiting', 'matched', 'cancelled') DEFAULT 'waiting',
    `matched_game_id` INT UNSIGNED DEFAULT NULL,
    `queued_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX idx_queue_status_format (`status`, `format`, `queued_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla: game_rooms
CREATE TABLE IF NOT EXISTS `game_rooms` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `room_code` VARCHAR(20) NOT NULL UNIQUE,
    `player1_id` INT UNSIGNED NOT NULL,
    `player2_id` INT UNSIGNED DEFAULT NULL,
    `player1_deck_id` INT UNSIGNED NOT NULL,
    `player2_deck_id` INT UNSIGNED DEFAULT NULL,
    `format` VARCHAR(30) DEFAULT 'Blitz',
    `status` ENUM('waiting', 'active', 'finished') DEFAULT 'waiting',
    `current_turn_player_id` INT UNSIGNED DEFAULT NULL,
    `turn_number` INT UNSIGNED DEFAULT 1,
    `winner_id` INT UNSIGNED DEFAULT NULL,
    `game_state` LONGTEXT DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`player1_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX idx_room_code (`room_code`),
    INDEX idx_room_status (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla: game_actions
CREATE TABLE IF NOT EXISTS `game_actions` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `room_id` INT UNSIGNED NOT NULL,
    `player_id` INT UNSIGNED NOT NULL,
    `action_type` VARCHAR(50) NOT NULL,
    `action_payload` LONGTEXT DEFAULT NULL,
    `sequence_num` INT UNSIGNED NOT NULL DEFAULT 1,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`room_id`) REFERENCES `game_rooms`(`id`) ON DELETE CASCADE,
    INDEX idx_room_seq (`room_id`, `sequence_num`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla: chat_messages
CREATE TABLE IF NOT EXISTS `chat_messages` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `room_id` INT UNSIGNED NOT NULL,
    `user_id` INT UNSIGNED NOT NULL,
    `user_name` VARCHAR(100) NOT NULL,
    `message` TEXT NOT NULL,
    `is_system` TINYINT(1) DEFAULT 0,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`room_id`) REFERENCES `game_rooms`(`id`) ON DELETE CASCADE,
    INDEX idx_chat_room (`room_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- 3. Inserción de Datos Iniciales (Sets)
-- =====================================================================
INSERT INTO `sets` (`id`, `name`, `card_count`) VALUES
('WTR', 'Welcome to Rathe', 0),
('ARC', 'Arcane Rising', 0),
('CRU', 'Crucible of War', 0),
('MON', 'Monarch', 0),
('ELE', 'Tales of Aria', 0),
('EVR', 'Everfest', 0),
('UPR', 'Uprising', 0),
('DYN', 'Dynasty', 0),
('OUT', 'Outsiders', 0),
('DTD', 'Dusk till Dawn', 0),
('EVO', 'Brightmeart / Evolution', 0),
('HVY', 'Heavy Hitters', 0),
('MST', 'Mistveil: Part the Mistveil', 0),
('ROS', 'Rosetta', 0),
('HNT', 'The Hunted', 0),
('LGS', 'Promo & Armory', 0),
('HP1', 'History Pack 1', 0),
('HP2', 'History Pack 2', 0)
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- =====================================================================
-- 4. Inserción de Usuarios de Prueba (PlayerOne y PlayerTwo)
-- Password por defecto: 'password123'
-- Hash Bcrypt generado: $2y$10$mWYRh5rYcrdYRBZNBMRVB.NzopIQIWvdR/zVxGpOl5Hzax43pT60O
-- =====================================================================
INSERT INTO `users` (`username`, `password_hash`, `display_name`, `avatar`, `is_online`, `last_seen`) VALUES
('PlayerOne', '$2y$10$mWYRh5rYcrdYRBZNBMRVB.NzopIQIWvdR/zVxGpOl5Hzax43pT60O', 'Hero of Rathe', 'avatar_dorinthea', 1, NOW()),
('PlayerTwo', '$2y$10$mWYRh5rYcrdYRBZNBMRVB.NzopIQIWvdR/zVxGpOl5Hzax43pT60O', 'Shadow Runeblade', 'avatar_chane', 1, NOW())
ON DUPLICATE KEY UPDATE `display_name` = VALUES(`display_name`);

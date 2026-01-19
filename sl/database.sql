-- SL铸币服务器 - 数据库导入文件
-- 版本: 1.0
-- 日期: 2026-01-19

-- 创建数据库
CREATE DATABASE IF NOT EXISTS `sl_server_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- 使用数据库
USE `sl_server_db`;

-- 创建用户表
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(50) NOT NULL UNIQUE,
    `email` VARCHAR(100) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `ip` VARCHAR(45) NOT NULL,
    `joined_at` DATETIME NOT NULL,
    `last_login` DATETIME,
    `status` ENUM('active', 'banned', 'muted') DEFAULT 'active',
    `rank` ENUM('user', 'vip', 'mod', 'admin') DEFAULT 'user',
    `coins` INT DEFAULT 0,
    `playtime` INT DEFAULT 0,
    `last_seen` DATETIME,
    `login_attempts` INT DEFAULT 0,
    `lockout_until` DATETIME,
    `avatar` VARCHAR(255) DEFAULT 'default.png',
    INDEX `idx_username` (`username`),
    INDEX `idx_email` (`email`),
    INDEX `idx_status` (`status`),
    INDEX `idx_rank` (`rank`),
    INDEX `idx_lockout_until` (`lockout_until`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 创建公告表
CREATE TABLE IF NOT EXISTS `announcements` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `title` VARCHAR(100) NOT NULL,
    `content` TEXT NOT NULL,
    `author_id` INT NOT NULL,
    `priority` INT DEFAULT 0,
    `status` ENUM('published', 'draft', 'archived') DEFAULT 'published',
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME,
    INDEX `idx_author_id` (`author_id`),
    INDEX `idx_created_at` (`created_at`),
    INDEX `idx_status` (`status`),
    INDEX `idx_priority` (`priority`),
    INDEX `idx_is_active` (`is_active`),
    FOREIGN KEY (`author_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 创建插件表
CREATE TABLE IF NOT EXISTS `plugins` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(50) NOT NULL,
    `version` VARCHAR(20) NOT NULL,
    `description` TEXT NOT NULL,
    `author` VARCHAR(50) NOT NULL,
    `url` VARCHAR(255),
    `status` ENUM('enabled', 'disabled') DEFAULT 'enabled',
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME,
    INDEX `idx_name` (`name`),
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 创建服务器特性表
CREATE TABLE IF NOT EXISTS `server_features` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `title` VARCHAR(50) NOT NULL,
    `content` TEXT NOT NULL,
    `order_num` INT NOT NULL DEFAULT 0,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME,
    INDEX `idx_order_num` (`order_num`),
    INDEX `idx_is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 创建服务器规则表
CREATE TABLE IF NOT EXISTS `server_rules` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `title` VARCHAR(50) NOT NULL,
    `content` TEXT NOT NULL,
    `order_num` INT NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME,
    INDEX `idx_order_num` (`order_num`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 创建安全日志表
CREATE TABLE IF NOT EXISTS `security_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `event_type` VARCHAR(50) NOT NULL,
    `user_id` INT,
    `ip` VARCHAR(45) NOT NULL,
    `details` TEXT,
    `created_at` DATETIME NOT NULL,
    INDEX `idx_event_type` (`event_type`),
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_ip` (`ip`),
    INDEX `idx_created_at` (`created_at`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 创建管理人员表
CREATE TABLE IF NOT EXISTS `staff_members` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(50) NOT NULL,
    `role` VARCHAR(50) NOT NULL,
    `bio` TEXT,
    `avatar` VARCHAR(255) DEFAULT 'default.png',
    `discord` VARCHAR(50),
    `email` VARCHAR(100),
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME,
    `display_order` INT DEFAULT 0,
    `joined_at` DATETIME NOT NULL,
    `user_id` INT,
    INDEX `idx_username` (`username`),
    INDEX `idx_role` (`role`),
    INDEX `idx_is_active` (`is_active`),
    INDEX `idx_display_order` (`display_order`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 插入初始数据

-- 插入管理员用户（密码: admin123）
INSERT INTO `users` (`username`, `email`, `password`, `ip`, `joined_at`, `status`, `rank`) 
VALUES ('admin', 'admin@slserver.com', '$2y$12$8T3H9YkUc5H6Z7W8X9V0B1N2M3L4K5J6I7H8G9F0E', '127.0.0.1', NOW(), 'active', 'admin') 
ON DUPLICATE KEY UPDATE 
    `email` = VALUES(`email`), 
    `password` = VALUES(`password`), 
    `status` = VALUES(`status`), 
    `rank` = VALUES(`rank`);

-- 插入默认服务器规则
INSERT INTO `server_rules` (`title`, `content`, `order_num`, `created_at`) VALUES
('规则1: 尊重他人', '请尊重服务器上的所有玩家，不允许使用侮辱性语言或进行骚扰。', 1, NOW()),
('规则2: 禁止作弊', '严禁使用任何形式的作弊软件、脚本或漏洞来获取不公平优势。', 2, NOW()),
('规则3: 禁止刷屏', '请勿在聊天中发送大量重复内容或无意义的信息。', 3, NOW()),
('规则4: 禁止广告', '未经管理员允许，不得在服务器上发布广告信息。', 4, NOW()),
('规则5: 保护隐私', '请勿分享他人的个人信息，包括真实姓名、地址、电话号码等。', 5, NOW())
ON DUPLICATE KEY UPDATE 
    `content` = VALUES(`content`), 
    `order_num` = VALUES(`order_num`);

-- 插入默认插件列表
INSERT INTO `plugins` (`name`, `version`, `description`, `author`, `url`, `status`, `created_at`) VALUES
('EssentialsX', '2.20.1', '服务器基础功能插件，提供传送、经济、权限等核心功能。', 'Essentials Team', 'https://essentialsx.net/', 'enabled', NOW()),
('WorldEdit', '7.2.16', '世界编辑插件，允许管理员快速编辑地图。', 'sk89q', 'https://enginehub.org/worldedit/', 'enabled', NOW()),
('LuckPerms', '5.4.103', '现代化的权限管理插件，支持多种权限后端。', 'Luck', 'https://luckperms.net/', 'enabled', NOW()),
('Vault', '1.7.3', '经济和权限API插件，提供统一的接口。', 'Vault Team', 'https://dev.bukkit.org/projects/vault', 'enabled', NOW()),
('CoreProtect', '21.2', '区块和物品保护插件，防止 griefing。', 'Intelli', 'https://coreprotect.net/', 'enabled', NOW())
ON DUPLICATE KEY UPDATE 
    `version` = VALUES(`version`), 
    `description` = VALUES(`description`), 
    `status` = VALUES(`status`);

-- 插入欢迎公告
INSERT INTO `announcements` (`title`, `content`, `author_id`, `priority`, `status`, `is_active`, `created_at`) 
VALUES ('欢迎来到SL铸币服务器！', '欢迎加入我们的服务器！请遵守服务器规则，享受游戏乐趣！', 1, 10, 'published', 1, NOW()) 
ON DUPLICATE KEY UPDATE 
    `content` = VALUES(`content`), 
    `priority` = VALUES(`priority`), 
    `status` = VALUES(`status`), 
    `is_active` = VALUES(`is_active`);

-- 插入初始管理人员数据
INSERT INTO `staff_members` (`username`, `role`, `bio`, `avatar`, `discord`, `email`, `is_active`, `created_at`, `updated_at`, `display_order`, `joined_at`, `user_id`) VALUES
('Admin', 'owner', '服务器创始人，负责整体管理和技术支持', 'https://via.placeholder.com/100', 'admin#1234', 'admin@slserver.com', 1, NOW(), NOW(), 1, NOW(), 1),
('Moderator', 'moderator', '服务器版主，维护游戏秩序和玩家体验', 'https://via.placeholder.com/100', 'mod#5678', 'mod@slserver.com', 1, NOW(), NOW(), 2, NOW(), NULL),
('Helper', 'helper', '服务器助手，帮助新玩家解决问题', 'https://via.placeholder.com/100', 'helper#9012', 'helper@slserver.com', 1, NOW(), NOW(), 3, NOW(), NULL)
ON DUPLICATE KEY UPDATE 
    `role` = VALUES(`role`),
    `bio` = VALUES(`bio`),
    `avatar` = VALUES(`avatar`),
    `discord` = VALUES(`discord`),
    `email` = VALUES(`email`),
    `is_active` = VALUES(`is_active`),
    `updated_at` = VALUES(`updated_at`),
    `display_order` = VALUES(`display_order`),
    `user_id` = VALUES(`user_id`);

-- 插入服务器特性初始数据
INSERT INTO `server_features` (`title`, `content`, `order_num`, `is_active`, `created_at`) VALUES
('专业经济系统', '完整的游戏内经济体系，支持交易、商店等功能，让玩家体验真实的经济环境。', 1, 1, NOW()),
('领地保护', '保护你的建筑不被其他玩家破坏，让你的创作更加安全。', 2, 1, NOW()),
('随机传送', '随机传送到新地点，探索未知世界，发现更多游戏乐趣。', 3, 1, NOW()),
('成就系统', '完成各种挑战，获得独特奖励，展示你的游戏成就。', 4, 1, NOW()),
('多种游戏模式', '支持生存、创造、冒险等多种游戏模式，满足不同玩家的需求。', 5, 1, NOW()),
('专业管理团队', '经验丰富的管理团队，确保服务器的稳定运行和公平环境。', 6, 1, NOW()),
('定期活动', '定期举办各种有趣的活动，提供丰厚奖励，增强玩家互动。', 7, 1, NOW()),
('高品质插件', '使用最新的高品质插件，提供更好的游戏体验和更多功能。', 8, 1, NOW()),
('稳定服务器', '专业服务器硬件，24小时稳定运行，确保玩家随时可以游戏。', 9, 1, NOW()),
('友好社区', '活跃友好的玩家社区，让你在游戏中结交更多朋友。', 10, 1, NOW())
ON DUPLICATE KEY UPDATE 
    `content` = VALUES(`content`), 
    `order_num` = VALUES(`order_num`),
    `is_active` = VALUES(`is_active`);

-- 插入安全日志表的初始记录
INSERT INTO `security_logs` (`event_type`, `user_id`, `ip`, `details`, `created_at`) 
VALUES ('system_init', NULL, '127.0.0.1', '数据库初始化完成', NOW());

-- 优化表（可选）
OPTIMIZE TABLE `users`, `announcements`, `plugins`, `server_rules`, `security_logs`, `staff_members`;

-- 显示数据库状态
SELECT '数据库导入完成！' AS `status`;
SELECT COUNT(*) AS `total_tables` FROM information_schema.tables WHERE table_schema = 'sl_server_db';
SELECT table_name AS `tables` FROM information_schema.tables WHERE table_schema = 'sl_server_db' ORDER BY table_name;
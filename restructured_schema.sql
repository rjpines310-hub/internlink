-- Database schema for restructured messaging system with global user IDs
-- This version avoids altering existing role tables by using a mapping table

-- Create users table for global user management
CREATE TABLE IF NOT EXISTS `users` (
  `user_id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(255) NOT NULL UNIQUE,
  `password` varchar(255) NOT NULL,
  `role` enum('student','faculty','companyhr','supervisor') NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`),
  KEY `idx_role` (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create user_mappings table to map existing role tables to global user_id
CREATE TABLE IF NOT EXISTS `user_mappings` (
  `mapping_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `role_type` enum('student','faculty','companyhr','supervisor') NOT NULL,
  `role_id` int(11) NOT NULL,
  PRIMARY KEY (`mapping_id`),
  UNIQUE KEY `unique_mapping` (`role_type`, `role_id`),
  KEY `idx_user_id` (`user_id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create messages table with global user IDs
CREATE TABLE IF NOT EXISTS `messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sender_user_id` int(11) NOT NULL,
  `receiver_user_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `sent_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `message_type` enum('text','image','file') NOT NULL DEFAULT 'text',
  `file_path` varchar(255) DEFAULT NULL,
  `edited_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_sender_user` (`sender_user_id`),
  KEY `idx_receiver_user` (`receiver_user_id`),
  KEY `idx_conversation_user` (`sender_user_id`, `receiver_user_id`),
  KEY `idx_sent_at` (`sent_at`),
  KEY `idx_is_read` (`is_read`),
  FOREIGN KEY (`sender_user_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE,
  FOREIGN KEY (`receiver_user_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Update conversation_summary view for global user IDs
CREATE OR REPLACE VIEW `conversation_summary` AS
SELECT
    u2.role as other_role,
    u2.user_id as other_user_id,
    CASE
        WHEN m1.sender_user_id = u1.user_id THEN m1.receiver_user_id
        ELSE m1.sender_user_id
    END as student_user_id,
    m1.message as last_message,
    m1.sent_at as last_message_time,
    (SELECT COUNT(*)
     FROM messages m2
     WHERE m2.receiver_user_id = u1.user_id
     AND m2.sender_user_id = CASE WHEN m1.sender_user_id = u1.user_id THEN m1.receiver_user_id ELSE m1.sender_user_id END
     AND m2.is_read = 0
    ) as unread_count
FROM messages m1
JOIN users u1 ON (m1.sender_user_id = u1.user_id OR m1.receiver_user_id = u1.user_id)
JOIN users u2 ON (CASE WHEN m1.sender_user_id = u1.user_id THEN m1.receiver_user_id ELSE m1.sender_user_id END = u2.user_id)
WHERE m1.id = (
    SELECT MAX(m3.id)
    FROM messages m3
    WHERE (
        (m3.sender_user_id = m1.sender_user_id AND m3.receiver_user_id = m1.receiver_user_id)
        OR
        (m3.sender_user_id = m1.receiver_user_id AND m3.receiver_user_id = m1.sender_user_id)
    )
)
AND u1.role = 'student';

-- Update message_preferences table to use user_id
CREATE TABLE IF NOT EXISTS `message_preferences` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `email_notifications` tinyint(1) NOT NULL DEFAULT 1,
  `push_notifications` tinyint(1) NOT NULL DEFAULT 1,
  `sound_notifications` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_prefs` (`user_id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Update conversation_participants table to use user_id
CREATE TABLE IF NOT EXISTS `conversation_participants` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `conversation_id` varchar(100) NOT NULL,
  `user_id` int(11) NOT NULL,
  `joined_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `left_at` timestamp NULL DEFAULT NULL,
  `is_admin` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_conversation` (`conversation_id`),
  KEY `idx_participant` (`user_id`),
  UNIQUE KEY `unique_participant` (`conversation_id`, `user_id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Update stored procedures and functions to use user_id
DELIMITER //
CREATE OR REPLACE PROCEDURE MarkMessagesAsRead(
    IN p_receiver_user_id INT,
    IN p_sender_user_id INT
)
BEGIN
    UPDATE messages
    SET is_read = 1
    WHERE receiver_user_id = p_receiver_user_id
    AND sender_user_id = p_sender_user_id
    AND is_read = 0;
END //
DELIMITER ;

DELIMITER //
CREATE OR REPLACE FUNCTION GetUnreadCount(
    p_user_id INT
) RETURNS INT
READS SQL DATA
DETERMINISTIC
BEGIN
    DECLARE unread_count INT DEFAULT 0;

    SELECT COUNT(*) INTO unread_count
    FROM messages
    WHERE receiver_user_id = p_user_id
    AND is_read = 0
    AND deleted_at IS NULL;

    RETURN unread_count;
END //
DELIMITER ;

-- Migration script (run after creating tables)
-- Populate users and mappings from existing data

-- Insert users from existing tables
INSERT INTO users (username, password, role, email)
SELECT CONCAT('student_', student_id), password, 'student', email FROM student;

INSERT INTO users (username, password, role, email)
SELECT CONCAT('faculty_', faculty_id), password, 'faculty', email FROM faculty;

INSERT INTO users (username, password, role, email)
SELECT CONCAT('hr_', hr_id), '', 'companyhr', '' FROM companyhr;

INSERT INTO users (username, password, role, email)
SELECT CONCAT('supervisor_', supervisor_id), password, 'supervisor', '' FROM supervisor;

-- Populate user_mappings
INSERT INTO user_mappings (user_id, role_type, role_id)
SELECT u.user_id, 'student', s.student_id
FROM users u
JOIN student s ON u.username = CONCAT('student_', s.student_id);

INSERT INTO user_mappings (user_id, role_type, role_id)
SELECT u.user_id, 'faculty', f.faculty_id
FROM users u
JOIN faculty f ON u.username = CONCAT('faculty_', f.faculty_id);

INSERT INTO user_mappings (user_id, role_type, role_id)
SELECT u.user_id, 'companyhr', c.hr_id
FROM users u
JOIN companyhr c ON u.username = CONCAT('hr_', c.hr_id);

INSERT INTO user_mappings (user_id, role_type, role_id)
SELECT u.user_id, 'supervisor', sv.supervisor_id
FROM users u
JOIN supervisor sv ON u.username = CONCAT('supervisor_', sv.supervisor_id);

-- Migrate messages (assuming old messages table exists as messages_old)
-- If not, rename current messages to messages_old first
-- ALTER TABLE messages RENAME TO messages_old;

-- Then insert into new messages
INSERT INTO messages (sender_user_id, receiver_user_id, message, sent_at, is_read, message_type, file_path, edited_at, deleted_at)
SELECT
    ums.user_id as sender_user_id,
    umr.user_id as receiver_user_id,
    m.message, m.sent_at, m.is_read, m.message_type, m.file_path, m.edited_at, m.deleted_at
FROM messages_old m
JOIN user_mappings ums ON m.sender_type = ums.role_type AND m.sender_id = ums.role_id
JOIN user_mappings umr ON m.receiver_type = umr.role_type AND m.receiver_id = umr.role_id;

-- After migration, drop old messages table
-- DROP TABLE messages_old;

-- Usage examples:
-- SELECT GetUnreadCount(1);
-- CALL MarkMessagesAsRead(1, 2);

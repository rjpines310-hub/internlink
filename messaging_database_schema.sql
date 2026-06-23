-- Database schema for improved messaging system
-- Run this SQL to create/update the messages table

-- Create messages table if it doesn't exist
CREATE TABLE IF NOT EXISTS `messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sender_type` enum('student','faculty','companyhr','supervisor') NOT NULL,
  `sender_id` int(11) NOT NULL,
  `receiver_type` enum('student','faculty','companyhr','supervisor') NOT NULL,
  `receiver_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `sent_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `message_type` enum('text','image','file') NOT NULL DEFAULT 'text',
  `file_path` varchar(255) DEFAULT NULL,
  `edited_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_sender` (`sender_type`, `sender_id`),
  KEY `idx_receiver` (`receiver_type`, `receiver_id`),
  KEY `idx_conversation` (`sender_type`, `sender_id`, `receiver_type`, `receiver_id`),
  KEY `idx_sent_at` (`sent_at`),
  KEY `idx_is_read` (`is_read`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add indexes for better performance (already in CREATE TABLE)

-- Create a view for easier conversation queries (optional)
CREATE OR REPLACE VIEW `conversation_summary` AS
SELECT
    CASE
        WHEN m1.sender_type = 'student' THEN m1.receiver_type
        ELSE m1.sender_type
    END as other_type,
    CASE
        WHEN m1.sender_type = 'student' THEN m1.receiver_id
        ELSE m1.sender_id
    END as other_id,
    CASE
        WHEN m1.sender_type = 'student' THEN m1.sender_id
        ELSE m1.receiver_id
    END as student_id,
    m1.message as last_message,
    m1.sent_at as last_message_time,
    (SELECT COUNT(*)
     FROM messages m2
     WHERE m2.receiver_type = 'student'
     AND m2.receiver_id = CASE WHEN m1.sender_type = 'student' THEN m1.sender_id ELSE m1.receiver_id END
     AND m2.sender_type = CASE WHEN m1.sender_type = 'student' THEN m1.receiver_type ELSE m1.sender_type END
     AND m2.sender_id = CASE WHEN m1.sender_type = 'student' THEN m1.receiver_id ELSE m1.sender_id END
     AND m2.is_read = 0
    ) as unread_count
FROM messages m1
WHERE m1.id = (
    SELECT MAX(m3.id)
    FROM messages m3
    WHERE (
        (m3.sender_type = m1.sender_type AND m3.sender_id = m1.sender_id AND
         m3.receiver_type = m1.receiver_type AND m3.receiver_id = m1.receiver_id)
        OR
        (m3.sender_type = m1.receiver_type AND m3.sender_id = m1.receiver_id AND
         m3.receiver_type = m1.sender_type AND m3.receiver_id = m1.sender_id)
    )
)
AND (m1.sender_type = 'student' OR m1.receiver_type = 'student');

-- Sample data for testing (optional - remove in production)
-- INSERT INTO `messages` (`sender_type`, `sender_id`, `receiver_type`, `receiver_id`, `message`) VALUES
-- ('student', 1, 'faculty', 1, 'Hello, I have a question about my internship requirements.'),
-- ('faculty', 1, 'student', 1, 'Hi! I\'d be happy to help. What specific requirements are you asking about?'),
-- ('student', 1, 'companyhr', 1, 'Thank you for considering my application. When can I expect to hear back?'),
-- ('companyhr', 1, 'student', 1, 'We will review all applications and get back to you within a week.');

-- Create notification preferences table (for future enhancements)
CREATE TABLE IF NOT EXISTS `message_preferences` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_type` enum('student','faculty','companyhr','supervisor') NOT NULL,
  `user_id` int(11) NOT NULL,
  `email_notifications` tinyint(1) NOT NULL DEFAULT 1,
  `push_notifications` tinyint(1) NOT NULL DEFAULT 1,
  `sound_notifications` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_prefs` (`user_type`, `user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create message attachments table (for future file sharing)
CREATE TABLE IF NOT EXISTS `message_attachments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `message_id` int(11) NOT NULL,
  `filename` varchar(255) NOT NULL,
  `original_filename` varchar(255) NOT NULL,
  `file_size` int(11) NOT NULL,
  `mime_type` varchar(100) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_message_id` (`message_id`),
  FOREIGN KEY (`message_id`) REFERENCES `messages` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create conversation participants table (for group messaging in future)
CREATE TABLE IF NOT EXISTS `conversation_participants` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `conversation_id` varchar(100) NOT NULL,
  `user_type` enum('student','faculty','companyhr','supervisor') NOT NULL,
  `user_id` int(11) NOT NULL,
  `joined_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `left_at` timestamp NULL DEFAULT NULL,
  `is_admin` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_conversation` (`conversation_id`),
  KEY `idx_participant` (`user_type`, `user_id`),
  UNIQUE KEY `unique_participant` (`conversation_id`, `user_type`, `user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Performance optimization: Clean up old deleted messages (run periodically)
-- DELETE FROM messages WHERE deleted_at IS NOT NULL AND deleted_at < DATE_SUB(NOW(), INTERVAL 30 DAY);

-- Create stored procedure for marking messages as read (optional)
DELIMITER //
CREATE PROCEDURE MarkMessagesAsRead(
    IN p_receiver_type VARCHAR(20),
    IN p_receiver_id INT,
    IN p_sender_type VARCHAR(20),
    IN p_sender_id INT
)
BEGIN
    UPDATE messages 
    SET is_read = 1 
    WHERE receiver_type = p_receiver_type 
    AND receiver_id = p_receiver_id 
    AND sender_type = p_sender_type 
    AND sender_id = p_sender_id 
    AND is_read = 0;
END //
DELIMITER ;

-- Create function to get unread message count
DELIMITER //
CREATE FUNCTION GetUnreadCount(
    p_user_type VARCHAR(20),
    p_user_id INT
) RETURNS INT
READS SQL DATA
DETERMINISTIC
BEGIN
    DECLARE unread_count INT DEFAULT 0;
    
    SELECT COUNT(*) INTO unread_count
    FROM messages 
    WHERE receiver_type = p_user_type 
    AND receiver_id = p_user_id 
    AND is_read = 0 
    AND deleted_at IS NULL;
    
    RETURN unread_count;
END //
DELIMITER ;

-- Usage examples:
-- SELECT GetUnreadCount('student', 1);
-- CALL MarkMessagesAsRead('student', 1, 'faculty', 1);

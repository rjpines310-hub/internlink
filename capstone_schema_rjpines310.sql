-- Drop existing tables to ensure a clean slate, respecting foreign key constraints
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `certifications`;
DROP TABLE IF EXISTS `education`;
DROP TABLE IF EXISTS `hr_requests`;
DROP TABLE IF EXISTS `internship_posts`;
DROP TABLE IF EXISTS `intern_applications`;
DROP TABLE IF EXISTS `resumes`;
DROP TABLE IF EXISTS `skills`;
DROP TABLE IF EXISTS `work_experience`;
DROP TABLE IF EXISTS `interviews`;
DROP TABLE IF EXISTS `timecard`;
DROP TABLE IF EXISTS `student_file_submissions`;
DROP TABLE IF EXISTS `file_comments`;
DROP TABLE IF EXISTS `sections`;
DROP TABLE IF EXISTS `messages`;
DROP TABLE IF EXISTS `tasks`;
DROP TABLE IF EXISTS `student_overview`;
DROP TABLE IF EXISTS `active_geofence`;
DROP TABLE IF EXISTS `geofence_locations`;
DROP TABLE IF EXISTS `admin_login`;
DROP TABLE IF EXISTS `companyhr`;
DROP TABLE IF EXISTS `faculty`;
DROP TABLE IF EXISTS `supervisor`;
DROP TABLE IF EXISTS `student`;
DROP TABLE IF EXISTS `user_mappings`;
DROP TABLE IF EXISTS `message_preferences`;
DROP TABLE IF EXISTS `conversation_participants`;
DROP TABLE IF EXISTS `users`;
DROP TABLE IF EXISTS `employment_status`; -- If it was a separate table
SET FOREIGN_KEY_CHECKS = 1;

-- Retaining users, user_mappings, message_preferences, conversation_participants from previous schema
-- These are crucial for the messaging system and global user management, which the user's `messages` table implies.
CREATE TABLE IF NOT EXISTS `users` (
  `user_id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(255) NOT NULL UNIQUE,
  `password` varchar(255) NOT NULL,
  `role` enum('student','faculty','companyhr','supervisor','admin') NOT NULL, -- Added 'admin' role
  `email` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`),
  KEY `idx_role` (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `user_mappings` (
  `mapping_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `role_type` enum('student','faculty','companyhr','supervisor','admin') NOT NULL, -- Added 'admin' role
  `role_id` int(11) NOT NULL,
  PRIMARY KEY (`mapping_id`),
  UNIQUE KEY `unique_mapping` (`role_type`, `role_id`),
  KEY `idx_user_id` (`user_id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

-- Table name: admin_login
CREATE TABLE IF NOT EXISTS `admin_login` (
  `admin_id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(255) NOT NULL UNIQUE,
  `first_name` varchar(255) NOT NULL,
  `last_name` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  PRIMARY KEY (`admin_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table name: companyhr
CREATE TABLE IF NOT EXISTS `companyhr` (
  `hr_id` INT(11) NOT NULL AUTO_INCREMENT,
  `companyname` VARCHAR(255) NOT NULL,
  `location` VARCHAR(255) DEFAULT NULL,
  `email` VARCHAR(255) NOT NULL UNIQUE,
  `contact` VARCHAR(20) DEFAULT NULL,
  `landline` VARCHAR(20) DEFAULT NULL,
  `password` VARCHAR(255) DEFAULT NULL,
  `profile_picture` VARCHAR(255) DEFAULT NULL,
  PRIMARY KEY (`hr_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table name: faculty
CREATE TABLE IF NOT EXISTS `faculty` (
  `faculty_id` INT(11) NOT NULL AUTO_INCREMENT,
  `firstname` VARCHAR(255) NOT NULL,
  `lastname` VARCHAR(255) NOT NULL,
  `email` VARCHAR(255) NOT NULL UNIQUE,
  `contact` VARCHAR(20) DEFAULT NULL,
  `password` VARCHAR(255) NOT NULL,
  `profile_picture` VARCHAR(255) DEFAULT NULL,
  PRIMARY KEY (`faculty_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table name: student
CREATE TABLE IF NOT EXISTS `student` (
  `student_id` INT(11) NOT NULL AUTO_INCREMENT,
  `studentid` VARCHAR(255) UNIQUE, -- Assuming this is a unique identifier like a school ID
  `firstname` VARCHAR(255) NOT NULL,
  `lastname` VARCHAR(255) NOT NULL,
  `section` VARCHAR(255) DEFAULT NULL,
  `email` VARCHAR(255) NOT NULL UNIQUE,
  `contact` VARCHAR(20) DEFAULT NULL,
  `password` VARCHAR(255) NOT NULL,
  `profile_picture` VARCHAR(255) DEFAULT NULL,
  `employment_status` VARCHAR(255) DEFAULT NULL, -- Changed from FK to direct field as per user
  `hr_id` INT(11) DEFAULT NULL, -- Foreign key to companyhr
  PRIMARY KEY (`student_id`),
  KEY `idx_hr_id` (`hr_id`),
  FOREIGN KEY (`hr_id`) REFERENCES `companyhr`(`hr_id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=1000 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table name: sections
CREATE TABLE IF NOT EXISTS `sections` (
  `section_id` INT(11) NOT NULL AUTO_INCREMENT,
  `section_name` VARCHAR(255) NOT NULL UNIQUE,
  PRIMARY KEY (`section_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table name: geofence_locations
CREATE TABLE IF NOT EXISTS `geofence_locations` (
  `location_id` INT(11) NOT NULL AUTO_INCREMENT,
  `location_name` VARCHAR(255) NOT NULL COMMENT 'Readable location name, e.g., "Universidad de Manila – Main Campus"',
  `latitude` DECIMAL(10,7) NOT NULL,
  `longitude` DECIMAL(10,7) NOT NULL,
  `radius` INT(11) NOT NULL DEFAULT 100 COMMENT 'Radius in meters',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`location_id`),
  KEY `idx_location_name` (`location_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table name: active_geofence
CREATE TABLE IF NOT EXISTS `active_geofence` (
  `active_id` INT(11) NOT NULL AUTO_INCREMENT,
  `location_id` INT(11) NOT NULL,
  `set_by` INT(11) NOT NULL COMMENT 'Admin user ID or HR ID who set this',
  `set_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`active_id`),
  UNIQUE KEY `unique_active_location` (`location_id`),
  KEY `idx_set_by` (`set_by`),
  FOREIGN KEY (`location_id`) REFERENCES `geofence_locations`(`location_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table name: hr_requests
CREATE TABLE IF NOT EXISTS `hr_requests` (
  `request_id` INT(11) NOT NULL AUTO_INCREMENT,
  `hr_id` INT(11) NOT NULL,
  `companyname` VARCHAR(255) NOT NULL,
  `location` VARCHAR(255) DEFAULT NULL,
  `email` VARCHAR(255) NOT NULL,
  `contact` VARCHAR(20) DEFAULT NULL,
  `landline` VARCHAR(20) DEFAULT NULL,
  `status` ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`request_id`),
  KEY `idx_hr_id` (`hr_id`),
  FOREIGN KEY (`hr_id`) REFERENCES `companyhr`(`hr_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table name: internship_posts
CREATE TABLE IF NOT EXISTS `internship_posts` (
  `post_id` INT(11) NOT NULL AUTO_INCREMENT,
  `internship_title` VARCHAR(255) NOT NULL,
  `companyname` VARCHAR(255) NOT NULL,
  `location` VARCHAR(255) DEFAULT NULL,
  `internship_description` TEXT DEFAULT NULL,
  `allowance` DECIMAL(10,2) DEFAULT NULL,
  `date_posted` DATE NOT NULL,
  `application_deadline` DATE DEFAULT NULL,
  `email` VARCHAR(255) DEFAULT NULL,
  `status` ENUM('active','closed') NOT NULL DEFAULT 'active',
  `posted_by` INT(11) DEFAULT NULL, -- Assuming this is hr_id
  PRIMARY KEY (`post_id`),
  KEY `idx_posted_by` (`posted_by`),
  FOREIGN KEY (`posted_by`) REFERENCES `companyhr`(`hr_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table name: supervisor
CREATE TABLE IF NOT EXISTS `supervisor` (
  `supervisor_id` INT(11) NOT NULL AUTO_INCREMENT,
  `firstname` VARCHAR(255) NOT NULL,
  `lastname` VARCHAR(255) NOT NULL,
  `companyname` VARCHAR(255) DEFAULT NULL,
  `email` VARCHAR(255) NOT NULL UNIQUE,
  `contact` VARCHAR(20) DEFAULT NULL,
  `password` VARCHAR(255) NOT NULL,
  `profile_picture` VARCHAR(255) DEFAULT NULL,
  `hr_id` INT(11) DEFAULT NULL, -- Foreign key to companyhr
  `post_id` INT(11) DEFAULT NULL, -- Foreign key to internship_posts
  PRIMARY KEY (`supervisor_id`),
  KEY `idx_hr_id` (`hr_id`),
  KEY `idx_post_id` (`post_id`),
  FOREIGN KEY (`hr_id`) REFERENCES `companyhr`(`hr_id`) ON DELETE SET NULL,
  FOREIGN KEY (`post_id`) REFERENCES `internship_posts`(`post_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table name: intern_applications
CREATE TABLE IF NOT EXISTS `intern_applications` (
  `application_id` INT(11) NOT NULL AUTO_INCREMENT,
  `student_id` INT(11) NOT NULL,
  `post_id` INT(11) NOT NULL,
  `application_date` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `status` ENUM('pending','approved','rejected','offered','accepted','declined') NOT NULL DEFAULT 'pending',
  `remarks` TEXT DEFAULT NULL,
  PRIMARY KEY (`application_id`),
  UNIQUE KEY `unique_student_post` (`student_id`, `post_id`),
  KEY `idx_student_id` (`student_id`),
  KEY `idx_post_id` (`post_id`),
  FOREIGN KEY (`student_id`) REFERENCES `student`(`student_id`) ON DELETE CASCADE,
  FOREIGN KEY (`post_id`) REFERENCES `internship_posts`(`post_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table name: resumes
CREATE TABLE IF NOT EXISTS `resumes` (
  `resume_id` INT(11) NOT NULL AUTO_INCREMENT,
  `student_id` INT(11) NOT NULL UNIQUE,
  `objective` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`resume_id`),
  KEY `idx_student_id` (`student_id`),
  FOREIGN KEY (`student_id`) REFERENCES `student`(`student_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table name: certifications
CREATE TABLE IF NOT EXISTS `certifications` (
  `certification_id` INT(11) NOT NULL AUTO_INCREMENT,
  `resume_id` INT(11) NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `issuer` VARCHAR(255) DEFAULT NULL,
  `date_obtained` DATE DEFAULT NULL,
  `description` TEXT DEFAULT NULL,
  PRIMARY KEY (`certification_id`),
  KEY `idx_resume_id` (`resume_id`),
  FOREIGN KEY (`resume_id`) REFERENCES `resumes`(`resume_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table name: education
CREATE TABLE IF NOT EXISTS `education` (
  `education_id` INT(11) NOT NULL AUTO_INCREMENT,
  `resume_id` INT(11) NOT NULL,
  `school_name` VARCHAR(255) NOT NULL,
  `start_year` YEAR DEFAULT NULL,
  `end_year` YEAR DEFAULT NULL,
  `description` TEXT DEFAULT NULL,
  PRIMARY KEY (`education_id`),
  KEY `idx_resume_id` (`resume_id`),
  FOREIGN KEY (`resume_id`) REFERENCES `resumes`(`resume_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table name: skills
CREATE TABLE IF NOT EXISTS `skills` (
  `skill_id` INT(11) NOT NULL AUTO_INCREMENT,
  `resume_id` INT(11) NOT NULL,
  `skill_name` VARCHAR(255) NOT NULL,
  `proficiency` ENUM('Beginner','Intermediate','Advanced','Expert') DEFAULT NULL,
  PRIMARY KEY (`skill_id`),
  KEY `idx_resume_id` (`resume_id`),
  FOREIGN KEY (`resume_id`) REFERENCES `resumes`(`resume_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table name: work_experience
CREATE TABLE IF NOT EXISTS `work_experience` (
  `experience_id` INT(11) NOT NULL AUTO_INCREMENT,
  `resume_id` INT(11) NOT NULL,
  `company_name` VARCHAR(255) NOT NULL,
  `position` VARCHAR(255) NOT NULL,
  `start_date` DATE DEFAULT NULL,
  `end_date` DATE DEFAULT NULL,
  `responsibilities` TEXT DEFAULT NULL,
  PRIMARY KEY (`experience_id`),
  KEY `idx_resume_id` (`resume_id`),
  FOREIGN KEY (`resume_id`) REFERENCES `resumes`(`resume_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table name: interviews
CREATE TABLE IF NOT EXISTS `interviews` (
  `interview_id` INT(11) NOT NULL AUTO_INCREMENT,
  `application_id` INT(11) NOT NULL,
  `hr_id` INT(11) NOT NULL,
  `companyname` VARCHAR(255) DEFAULT NULL,
  `internship_title` VARCHAR(255) DEFAULT NULL,
  `interview_datetime` DATETIME NOT NULL,
  `location` VARCHAR(255) DEFAULT NULL,
  `online_link` VARCHAR(255) DEFAULT NULL,
  `remarks` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`interview_id`),
  KEY `idx_application_id` (`application_id`),
  KEY `idx_hr_id` (`hr_id`),
  FOREIGN KEY (`application_id`) REFERENCES `intern_applications`(`application_id`) ON DELETE CASCADE,
  FOREIGN KEY (`hr_id`) REFERENCES `companyhr`(`hr_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table name: timecard
CREATE TABLE IF NOT EXISTS `timecard` (
  `timecard_id` INT(11) NOT NULL AUTO_INCREMENT,
  `student_id` INT(11) NOT NULL,
  `date` DATE NOT NULL,
  `time_in` TIME DEFAULT NULL,
  `time_out` TIME DEFAULT NULL,
  `location` VARCHAR(255) DEFAULT NULL,
  `photo_path` VARCHAR(255) DEFAULT NULL,
  `status` ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`timecard_id`),
  KEY `idx_student_id` (`student_id`),
  FOREIGN KEY (`student_id`) REFERENCES `student`(`student_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table name: student_file_submissions
CREATE TABLE IF NOT EXISTS `student_file_submissions` (
  `submission_id` INT(11) NOT NULL AUTO_INCREMENT,
  `student_id` INT(11) NOT NULL,
  `dtr_file` VARCHAR(255) DEFAULT NULL,
  `moa_file` VARCHAR(255) DEFAULT NULL,
  `letter_of_acceptance_file` VARCHAR(255) DEFAULT NULL,
  `evaluation_form_file` VARCHAR(255) DEFAULT NULL,
  `submitted_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `dtr_file_checked` BOOLEAN NOT NULL DEFAULT FALSE,
  `moa_file_checked` BOOLEAN NOT NULL DEFAULT FALSE,
  `letter_of_acceptance_file_checked` BOOLEAN NOT NULL DEFAULT FALSE,
  `evaluation_form_file_checked` BOOLEAN NOT NULL DEFAULT FALSE,
  PRIMARY KEY (`submission_id`),
  KEY `idx_student_id` (`student_id`),
  FOREIGN KEY (`student_id`) REFERENCES `student`(`student_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table name: file_comments
CREATE TABLE IF NOT EXISTS `file_comments` (
  `comment_id` INT(11) NOT NULL AUTO_INCREMENT,
  `submission_id` INT(11) NOT NULL,
  `student_id` INT(11) NOT NULL,
  `faculty_id` INT(11) NOT NULL,
  `file_type` ENUM('dtr','moa','loa','evaluation') NOT NULL,
  `comment_text` TEXT NOT NULL,
  `commented_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`comment_id`),
  KEY `idx_submission_id` (`submission_id`),
  KEY `idx_student_id` (`student_id`),
  KEY `idx_faculty_id` (`faculty_id`),
  FOREIGN KEY (`submission_id`) REFERENCES `student_file_submissions`(`submission_id`) ON DELETE CASCADE,
  FOREIGN KEY (`student_id`) REFERENCES `student`(`student_id`) ON DELETE CASCADE,
  FOREIGN KEY (`faculty_id`) REFERENCES `faculty`(`faculty_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table name: messages (adjusted to user's fields)
CREATE TABLE IF NOT EXISTS `messages` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `sender_type` ENUM('student','faculty','companyhr','supervisor','admin') NOT NULL,
  `sender_id` INT(11) NOT NULL,
  `receiver_type` ENUM('student','faculty','companyhr','supervisor','admin') NOT NULL,
  `receiver_id` INT(11) NOT NULL,
  `message` TEXT NOT NULL,
  `sent_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `is_read` BOOLEAN NOT NULL DEFAULT FALSE,
  PRIMARY KEY (`id`),
  KEY `idx_sender` (`sender_type`, `sender_id`),
  KEY `idx_receiver` (`receiver_type`, `receiver_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table name: tasks (adjusted to user's fields)
CREATE TABLE IF NOT EXISTS `tasks` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `student_id` INT(11) NOT NULL,
  `supervisor_id` INT(11) NOT NULL,
  `task_description` TEXT DEFAULT NULL,
  `due_date` DATE DEFAULT NULL,
  `status` ENUM('assigned','submitted','missed','completed') NOT NULL DEFAULT 'assigned',
  `assigned_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `submitted_at` TIMESTAMP NULL DEFAULT NULL,
  `checked_at` TIMESTAMP NULL DEFAULT NULL,
  `score` INT(11) DEFAULT NULL, -- Score out of 100
  PRIMARY KEY (`id`),
  KEY `idx_student_id` (`student_id`),
  KEY `idx_supervisor_id` (`supervisor_id`),
  FOREIGN KEY (`student_id`) REFERENCES `student`(`student_id`) ON DELETE CASCADE,
  FOREIGN KEY (`supervisor_id`) REFERENCES `supervisor`(`supervisor_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table name: student_overview (adjusted to user's fields)
CREATE TABLE IF NOT EXISTS `student_overview` (
    `student_overview_id` INT AUTO_INCREMENT PRIMARY KEY,
    `student_id` INT NOT NULL UNIQUE,
    `employment_status` VARCHAR(255) DEFAULT NULL, -- Direct field as per user
    `attendance` TEXT,
    `performance` TEXT,
    `file_submissions` TEXT,
    FOREIGN KEY (`student_id`) REFERENCES `student`(`student_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Views, Stored Procedures, and Functions from previous schema (adjusted for new messages table)
DELIMITER //
CREATE OR REPLACE PROCEDURE MarkMessagesAsRead(
    IN p_receiver_type ENUM('student','faculty','companyhr','supervisor','admin'),
    IN p_receiver_id INT,
    IN p_sender_type ENUM('student','faculty','companyhr','supervisor','admin'),
    IN p_sender_id INT
)
BEGIN
    UPDATE messages
    SET is_read = TRUE
    WHERE receiver_type = p_receiver_type
    AND receiver_id = p_receiver_id
    AND sender_type = p_sender_type
    AND sender_id = p_sender_id
    AND is_read = FALSE;
END //
DELIMITER ;

DELIMITER //
CREATE OR REPLACE FUNCTION GetUnreadCount(
    p_receiver_type ENUM('student','faculty','companyhr','supervisor','admin'),
    p_receiver_id INT
) RETURNS INT
READS SQL DATA
DETERMINISTIC
BEGIN
    DECLARE unread_count INT DEFAULT 0;

    SELECT COUNT(*) INTO unread_count
    FROM messages
    WHERE receiver_type = p_receiver_type
    AND receiver_id = p_receiver_id
    AND is_read = FALSE;

    RETURN unread_count;
END //
DELIMITER ;

-- conversation_summary view will need significant re-evaluation based on the new messages table structure.
-- For now, I will comment it out or provide a basic version.
-- CREATE OR REPLACE VIEW `conversation_summary` AS
-- SELECT
--     m1.receiver_type as other_role,
--     m1.receiver_id as other_user_id,
--     m1.sender_id as student_user_id, -- Assuming student is always the primary user for summary
--     m1.message as last_message,
--     m1.sent_at as last_message_time,
--     (SELECT COUNT(*)
--      FROM messages m2
--      WHERE m2.receiver_type = m1.sender_type
--      AND m2.receiver_id = m1.sender_id
--      AND m2.sender_type = m1.receiver_type
--      AND m2.sender_id = m1.receiver_id
--      AND m2.is_read = FALSE
--     ) as unread_count
-- FROM messages m1
-- WHERE m1.id = (
--     SELECT MAX(m3.id)
--     FROM messages m3
--     WHERE (
--         (m3.sender_type = m1.sender_type AND m3.sender_id = m1.sender_id AND m3.receiver_type = m1.receiver_type AND m3.receiver_id = m1.receiver_id)
--         OR
--         (m3.sender_type = m1.receiver_type AND m3.sender_id = m1.receiver_id AND m3.receiver_type = m1.sender_type AND m3.receiver_id = m1.sender_id)
--     )
-- )
-- AND m1.sender_type = 'student'; -- Or adjust based on who initiates the conversation summary

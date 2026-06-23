CREATE TABLE IF NOT EXISTS `student` (
  `student_id` int(11) NOT NULL AUTO_INCREMENT,
  `first_name` varchar(255) NOT NULL,
  `last_name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL UNIQUE,
  `password` varchar(255) NOT NULL,
  `course` varchar(255) DEFAULT NULL,
  `year_level` varchar(50) DEFAULT NULL,
  `contact_number` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`student_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `supervisor` (
  `supervisor_id` int(11) NOT NULL AUTO_INCREMENT,
  `first_name` varchar(255) NOT NULL,
  `last_name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL UNIQUE,
  `password` varchar(255) NOT NULL,
  `contact_number` varchar(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`supervisor_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `faculty` (
  `faculty_id` int(11) NOT NULL AUTO_INCREMENT,
  `first_name` varchar(255) NOT NULL,
  `last_name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL UNIQUE,
  `password` varchar(255) NOT NULL,
  `contact_number` varchar(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`faculty_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `companyhr` (
  `hr_id` int(11) NOT NULL AUTO_INCREMENT,
  `company_name` varchar(255) NOT NULL,
  `contact_person` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL UNIQUE,
  `password` varchar(255) DEFAULT NULL,
  `contact_number` varchar(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`hr_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `admin_login` (
  `admin_id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(255) NOT NULL UNIQUE,
  `first_name` varchar(255) NOT NULL,
  `last_name` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  PRIMARY KEY (`admin_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

CREATE TABLE IF NOT EXISTS `employment_status` (
    `employment_status_id` INT AUTO_INCREMENT PRIMARY KEY,
    `status_name` VARCHAR(255) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `student_overview` (
    `student_overview_id` INT AUTO_INCREMENT PRIMARY KEY,
    `student_id` INT,
    `employment_status_id` INT,
    `attendance` TEXT,
    `performance` TEXT,
    `file_submissions` TEXT,
    FOREIGN KEY (`student_id`) REFERENCES `student`(`student_id`),
    FOREIGN KEY (`employment_status_id`) REFERENCES `employment_status`(`employment_status_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tasks` (
  `task_id` int(11) NOT NULL AUTO_INCREMENT,
  `supervisor_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text,
  `due_date` date DEFAULT NULL,
  `status` enum('pending','completed','overdue') NOT NULL DEFAULT 'pending',
  `assigned_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `completed_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`task_id`),
  KEY `idx_supervisor_id` (`supervisor_id`),
  KEY `idx_student_id` (`student_id`),
  KEY `idx_status` (`status`),
  KEY `idx_due_date` (`due_date`),
  FOREIGN KEY (`supervisor_id`) REFERENCES `supervisor`(`supervisor_id`) ON DELETE CASCADE,
  FOREIGN KEY (`student_id`) REFERENCES `student`(`student_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

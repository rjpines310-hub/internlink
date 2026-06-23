-- Create tasks table for supervisor-student task assignments
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

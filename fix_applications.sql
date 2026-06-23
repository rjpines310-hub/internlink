USE capstone;

-- Create intern_applications table if it doesn't exist
CREATE TABLE IF NOT EXISTS `intern_applications` (
  `application_id` INT(11) NOT NULL AUTO_INCREMENT,
  `student_id` INT(11) NOT NULL,
  `post_id` INT(11) NOT NULL,
  `application_date` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `status` ENUM('Pending','Accepted','Rejected','Offer Sent','Hired','For Interview') NOT NULL DEFAULT 'Pending',
  PRIMARY KEY (`application_id`),
  KEY `idx_student_id` (`student_id`),
  KEY `idx_post_id` (`post_id`),
  KEY `idx_status` (`status`),
  FOREIGN KEY (`student_id`) REFERENCES `student`(`student_id`) ON DELETE CASCADE,
  FOREIGN KEY (`post_id`) REFERENCES `internship_posts`(`post_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create interviews table if it doesn't exist
CREATE TABLE IF NOT EXISTS `interviews` (
  `interview_id` INT(11) NOT NULL AUTO_INCREMENT,
  `application_id` INT(11) NOT NULL,
  `companyname` VARCHAR(255) NOT NULL,
  `internship_title` VARCHAR(255) NOT NULL,
  `interview_datetime` DATETIME NOT NULL,
  `location` ENUM('On-Site','Online') NOT NULL,
  `online_link` VARCHAR(255) DEFAULT NULL,
  `remarks` TEXT,
  `exact_address` VARCHAR(255) DEFAULT NULL,
  PRIMARY KEY (`interview_id`),
  KEY `idx_application_id` (`application_id`),
  FOREIGN KEY (`application_id`) REFERENCES `intern_applications`(`application_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create student_file_submissions table if it doesn't exist
CREATE TABLE IF NOT EXISTS `student_file_submissions` (
  `submission_id` INT(11) NOT NULL AUTO_INCREMENT,
  `student_id` INT(11) NOT NULL,
  `file_type` ENUM('DTR','MOA','LOA','EVALUATION') NOT NULL,
  `file_path` VARCHAR(255) NOT NULL,
  `submitted_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `dtr_file_checked` TINYINT(1) NOT NULL DEFAULT 0,
  `moa_file_checked` TINYINT(1) NOT NULL DEFAULT 0,
  `letter_of_acceptance_file_checked` TINYINT(1) NOT NULL DEFAULT 0,
  `evaluation_form_file_checked` TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`submission_id`),
  UNIQUE KEY `unique_student_file` (`student_id`, `file_type`),
  FOREIGN KEY (`student_id`) REFERENCES `student`(`student_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create file_comments table if it doesn't exist
CREATE TABLE IF NOT EXISTS `file_comments` (
  `comment_id` INT(11) NOT NULL AUTO_INCREMENT,
  `submission_id` INT(11) NOT NULL,
  `commenter_id` INT(11) NOT NULL,
  `commenter_role` ENUM('faculty','companyhr','supervisor') NOT NULL,
  `comment_text` TEXT NOT NULL,
  `commented_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`comment_id`),
  KEY `idx_submission_id` (`submission_id`),
  FOREIGN KEY (`submission_id`) REFERENCES `student_file_submissions`(`submission_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

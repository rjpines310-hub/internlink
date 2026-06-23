CREATE TABLE IF NOT EXISTS `student_overview` (
    `student_overview_id` INT AUTO_INCREMENT PRIMARY KEY,
    `student_id` INT,
    `attendance` TEXT,
    `performance` TEXT,
    `file_submissions` TEXT,
    `overall_average` DECIMAL(5,2),
    `hr_id` INT,
    `post_id` INT,
    FOREIGN KEY (`student_id`) REFERENCES `student`(`student_id`)
);

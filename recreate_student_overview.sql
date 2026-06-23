DROP TABLE IF EXISTS `student_overview`;
DROP TABLE IF EXISTS `employment_status`;

CREATE TABLE IF NOT EXISTS `student_overview` (
    `student_overview_id` INT AUTO_INCREMENT PRIMARY KEY,
    `student_id` INT,
    `employment_status` VARCHAR(255),
    `attendance` TEXT,
    `performance` TEXT,
    `file_submissions` TEXT,
    FOREIGN KEY (`student_id`) REFERENCES `student`(`student_id`)
);

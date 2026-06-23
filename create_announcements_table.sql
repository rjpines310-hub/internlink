CREATE TABLE announcements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    faculty_id INT,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    audience ENUM('student', 'supervisor', 'companyhr') NOT NULL,
    date_posted DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (faculty_id) REFERENCES faculty(faculty_id) ON DELETE CASCADE
);

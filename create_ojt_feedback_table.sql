CREATE TABLE ojt_feedback (
  feedback_id INT AUTO_INCREMENT PRIMARY KEY,
  student_id INT NOT NULL,
  faculty_id INT NULL,
  supervisor_id INT NULL,
  feedback_message TEXT NOT NULL,
  rating INT DEFAULT 0,
  given_by ENUM('faculty','supervisor') NOT NULL,
  submitted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (student_id) REFERENCES student(student_id) ON DELETE CASCADE,
  FOREIGN KEY (faculty_id) REFERENCES faculty(faculty_id) ON DELETE SET NULL,
  FOREIGN KEY (supervisor_id) REFERENCES supervisor(supervisor_id) ON DELETE SET NULL
);

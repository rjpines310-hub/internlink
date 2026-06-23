CREATE TABLE task_attachments (
  attachment_id INT AUTO_INCREMENT PRIMARY KEY,
  task_id INT NOT NULL,
  student_id INT NOT NULL,
  file_name VARCHAR(255),
  file_path VARCHAR(255),
  uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE task_comments (
  comment_id INT AUTO_INCREMENT PRIMARY KEY,
  task_id INT NOT NULL,
  user_id INT NOT NULL,
  user_role ENUM('student','supervisor'),
  comment_text TEXT,
  commented_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE manual_time_requests (
    request_id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT,
    hr_id INT,
    timecard_id INT,
    requested_time_in DATETIME NULL,
    requested_time_out DATETIME NULL,
    reason TEXT,
    status ENUM('pending', 'approved', 'denied') DEFAULT 'pending',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

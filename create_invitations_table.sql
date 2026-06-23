CREATE TABLE invitations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL,
    code VARCHAR(255) NOT NULL UNIQUE,
    role ENUM('Student', 'Company', 'Supervisor') NOT NULL,
    status ENUM('unused', 'used') DEFAULT 'unused',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

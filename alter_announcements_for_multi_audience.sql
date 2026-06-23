-- Drop existing announcements table if it exists
DROP TABLE IF EXISTS announcements;

-- Drop existing announcement_audiences table if it exists
DROP TABLE IF EXISTS announcement_audiences;

-- Recreate announcements table without the ENUM audience column
CREATE TABLE announcements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    faculty_id INT,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    date_posted DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (faculty_id) REFERENCES faculty(faculty_id) ON DELETE CASCADE
);

-- Create new junction table for multi-audience support
CREATE TABLE announcement_audiences (
    announcement_id INT,
    audience_role ENUM('student', 'supervisor', 'companyhr') NOT NULL,
    PRIMARY KEY (announcement_id, audience_role),
    FOREIGN KEY (announcement_id) REFERENCES announcements(id) ON DELETE CASCADE
);

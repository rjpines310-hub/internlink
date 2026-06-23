-- Alter tasks table: Add 'missed' to status, rename verified_at to checked_at, add score field, and create event for automatic missed status

-- Enable event scheduler if not already enabled
SET GLOBAL event_scheduler = ON;

-- Modify status enum to include 'missed'
ALTER TABLE tasks MODIFY status ENUM('assigned', 'submitted', 'completed', 'missed') NOT NULL DEFAULT 'assigned';

-- Rename verified_at to checked_at
ALTER TABLE tasks CHANGE verified_at checked_at TIMESTAMP NULL DEFAULT NULL;

-- Add submitted_at field
ALTER TABLE tasks ADD submitted_at TIMESTAMP NULL DEFAULT NULL COMMENT 'Timestamp when task was submitted' AFTER assigned_at;

-- Add score field
ALTER TABLE tasks ADD score INT(3) UNSIGNED DEFAULT NULL COMMENT 'Score out of 100' AFTER checked_at;

-- Create event to automatically update status to 'missed' for overdue assigned tasks (runs daily)
CREATE EVENT IF NOT EXISTS update_missed_tasks
ON SCHEDULE EVERY 1 DAY STARTS CURRENT_TIMESTAMP
DO
  UPDATE tasks SET status = 'missed' WHERE status = 'assigned' AND due_date < CURDATE();

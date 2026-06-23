-- Fix timecard table to match code usage
ALTER TABLE timecard ADD COLUMN IF NOT EXISTS time_in_selfie VARCHAR(255) DEFAULT NULL;
ALTER TABLE timecard ADD COLUMN IF NOT EXISTS time_out_selfie VARCHAR(255) DEFAULT NULL;
ALTER TABLE timecard ADD COLUMN IF NOT EXISTS latitude DECIMAL(10,7) DEFAULT NULL;
ALTER TABLE timecard ADD COLUMN IF NOT EXISTS longitude DECIMAL(10,7) DEFAULT NULL;
ALTER TABLE timecard ADD COLUMN IF NOT EXISTS location_id INT(11) DEFAULT NULL;
ALTER TABLE timecard ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;
ALTER TABLE timecard MODIFY COLUMN time_in DATETIME DEFAULT NULL;
ALTER TABLE timecard MODIFY COLUMN time_out DATETIME DEFAULT NULL;
ALTER TABLE timecard MODIFY COLUMN status ENUM('pending','Validated','rejected') NOT NULL DEFAULT 'pending';

-- Fix tasks table
ALTER TABLE tasks CHANGE id task_id INT(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE tasks MODIFY COLUMN status ENUM('pending','submitted','completed','missed','overdue') NOT NULL DEFAULT 'pending';

-- Add foreign key for timecard location_id if not exists
ALTER TABLE timecard ADD CONSTRAINT fk_timecard_location FOREIGN KEY (location_id) REFERENCES geofence_locations(location_id) ON DELETE SET NULL;

-- Update existing timecard records to have status 'Validated' if they have time_in and time_out
UPDATE timecard SET status = 'Validated' WHERE time_in IS NOT NULL AND time_out IS NOT NULL AND status = 'pending';

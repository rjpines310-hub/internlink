USE capstone;

-- Add missing columns to timecard table
ALTER TABLE timecard ADD COLUMN time_in_selfie VARCHAR(255) DEFAULT NULL;
ALTER TABLE timecard ADD COLUMN time_out_selfie VARCHAR(255) DEFAULT NULL;
ALTER TABLE timecard ADD COLUMN latitude DECIMAL(10,7) DEFAULT NULL;
ALTER TABLE timecard ADD COLUMN longitude DECIMAL(10,7) DEFAULT NULL;
ALTER TABLE timecard ADD COLUMN location_id INT(11) DEFAULT NULL;
ALTER TABLE timecard ADD COLUMN updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;
ALTER TABLE timecard MODIFY COLUMN status ENUM('pending','Validated','rejected') NOT NULL DEFAULT 'pending';

-- Add foreign key for location_id
ALTER TABLE timecard ADD CONSTRAINT fk_timecard_location FOREIGN KEY (location_id) REFERENCES geofence_locations(location_id) ON DELETE SET NULL;

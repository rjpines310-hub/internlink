-- Create active_geofence table for managing current active geofence locations
-- This table stores only 1 current location for all students at a time
CREATE TABLE IF NOT EXISTS `active_geofence` (
  `active_id` INT(11) NOT NULL AUTO_INCREMENT,
  `location_id` INT(11) NOT NULL,
  `set_by` INT(11) NOT NULL COMMENT 'Admin user ID or HR ID who set this',
  `set_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`active_id`),
  UNIQUE KEY `unique_active_location` (`location_id`),
  KEY `idx_set_by` (`set_by`),
  FOREIGN KEY (`location_id`) REFERENCES `geofence_locations`(`location_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

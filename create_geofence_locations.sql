-- Create geofence_locations table for geofencing integration
CREATE TABLE IF NOT EXISTS `geofence_locations` (
  `location_id` INT(11) NOT NULL AUTO_INCREMENT,
  `location_name` VARCHAR(255) NOT NULL COMMENT 'Readable location name, e.g., "Universidad de Manila – Main Campus"',
  `latitude` DECIMAL(10,7) NOT NULL,
  `longitude` DECIMAL(10,7) NOT NULL,
  `radius` INT(11) NOT NULL DEFAULT 100 COMMENT 'Radius in meters',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`location_id`),
  KEY `idx_location_name` (`location_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

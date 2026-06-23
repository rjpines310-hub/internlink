USE capstone;

-- Create geofence_locations table if not exists
CREATE TABLE IF NOT EXISTS `geofence_locations` (
  `location_id` int(11) NOT NULL AUTO_INCREMENT,
  `location_name` varchar(255) NOT NULL,
  `latitude` decimal(10,7) NOT NULL,
  `longitude` decimal(10,7) NOT NULL,
  `radius` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`location_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create active_geofence table if not exists
CREATE TABLE IF NOT EXISTS `active_geofence` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `location_id` int(11) NOT NULL,
  `set_by` int(11) NOT NULL,
  `set_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_active_geofence_location` (`location_id`),
  KEY `fk_active_geofence_hr` (`set_by`),
  CONSTRAINT `fk_active_geofence_location` FOREIGN KEY (`location_id`) REFERENCES `geofence_locations` (`location_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_active_geofence_hr` FOREIGN KEY (`set_by`) REFERENCES `companyhr` (`hr_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

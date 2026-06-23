-- Alter timecard table to use location_id instead of free text location
-- WARNING: This will drop existing location data. Backup data if necessary.

-- Add new location_id column
ALTER TABLE `timecard` ADD COLUMN `location_id` INT(11) NULL AFTER `location`;

-- Add foreign key constraint
ALTER TABLE `timecard` ADD CONSTRAINT `fk_timecard_location` FOREIGN KEY (`location_id`) REFERENCES `geofence_locations`(`location_id`) ON DELETE SET NULL;

-- Drop old location column (after confirming data migration if needed)
ALTER TABLE `timecard` DROP COLUMN `location`;

-- Temporarily disable foreign key checks
SET FOREIGN_KEY_CHECKS = 0;

-- Add hr_id column to geofence_locations table (nullable initially)
ALTER TABLE `geofence_locations`
ADD COLUMN `hr_id` INT(11) NULL AFTER `location_id`;

-- Update existing rows with a default or NULL hr_id if necessary
-- For now, we assume new entries will always have an hr_id.
-- If there are existing rows without an hr_id, they would need to be assigned or removed.
-- For this task, we assume the table is either empty or can be managed.

-- Add UNIQUE constraint and FOREIGN KEY constraint
ALTER TABLE `geofence_locations`
ADD UNIQUE KEY `uq_hr_id` (`hr_id`),
ADD CONSTRAINT `fk_geofence_hr_id` FOREIGN KEY (`hr_id`) REFERENCES `companyhr` (`hr_id`) ON DELETE CASCADE ON UPDATE CASCADE;

-- Re-enable foreign key checks
SET FOREIGN_KEY_CHECKS = 1;

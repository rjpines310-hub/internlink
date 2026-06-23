-- Add hr_id column to geofence_locations table
ALTER TABLE `geofence_locations`
ADD COLUMN `hr_id` INT(11) NULL AFTER `location_id`;

-- Add UNIQUE constraint on hr_id
ALTER TABLE `geofence_locations`
ADD UNIQUE KEY `uq_hr_id` (`hr_id`);

-- Add FOREIGN KEY constraint
ALTER TABLE `geofence_locations`
ADD CONSTRAINT `fk_geofence_hr_id` FOREIGN KEY (`hr_id`) REFERENCES `companyhr` (`hr_id`) ON DELETE CASCADE ON UPDATE CASCADE;

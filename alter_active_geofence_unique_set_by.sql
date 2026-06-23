-- Alter active_geofence table to change unique constraint from location_id to set_by
-- This allows each company (set_by) to have one active geofence location

-- Drop the foreign key constraint first
ALTER TABLE `active_geofence` DROP FOREIGN KEY `active_geofence_ibfk_1`;

-- Drop the existing unique constraint on location_id
ALTER TABLE `active_geofence` DROP INDEX `unique_active_location`;

-- Add unique constraint on set_by to allow one active location per company
ALTER TABLE `active_geofence` ADD CONSTRAINT `unique_active_per_company` UNIQUE (`set_by`);

-- Add back the foreign key constraint
ALTER TABLE `active_geofence` ADD CONSTRAINT `active_geofence_ibfk_1` FOREIGN KEY (`location_id`) REFERENCES `geofence_locations`(`location_id`) ON DELETE CASCADE;

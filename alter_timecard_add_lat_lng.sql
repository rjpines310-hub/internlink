-- Add latitude and longitude columns to timecard table for geofencing
ALTER TABLE `timecard` ADD COLUMN `latitude` DECIMAL(10,8) NULL AFTER `location_id`;
ALTER TABLE `timecard` ADD COLUMN `longitude` DECIMAL(11,8) NULL AFTER `latitude`;

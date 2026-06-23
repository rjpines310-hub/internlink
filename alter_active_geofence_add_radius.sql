-- Add radius column to active_geofence table
ALTER TABLE `active_geofence` ADD COLUMN `radius` INT(11) NOT NULL DEFAULT 100 COMMENT 'Geofence radius in meters';

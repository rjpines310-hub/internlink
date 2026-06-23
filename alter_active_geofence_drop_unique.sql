-- Drop the unique constraint on set_by to allow a single global active geofence
ALTER TABLE `active_geofence` DROP INDEX `unique_user_geofence`;

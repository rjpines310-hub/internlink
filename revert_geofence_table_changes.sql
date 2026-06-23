-- Drop the unique constraint on (set_by, set_by_user_type)
ALTER TABLE active_geofence
DROP INDEX IF EXISTS unique_user_geofence;

-- Drop the set_by_user_type column
ALTER TABLE active_geofence
DROP COLUMN IF EXISTS set_by_user_type;

-- Re-add the index on `set_by` if it was dropped (assuming it was named idx_set_by)
ALTER TABLE active_geofence
ADD INDEX idx_set_by (set_by);

-- Drop existing unique index on `set_by` if it exists
ALTER TABLE active_geofence
DROP INDEX IF EXISTS idx_set_by;

-- Add set_by_user_type column
ALTER TABLE active_geofence
ADD COLUMN set_by_user_type VARCHAR(50) NOT NULL AFTER set_by;

-- Add a unique constraint on (set_by, set_by_user_type)
ALTER TABLE active_geofence
ADD UNIQUE KEY unique_user_geofence (set_by, set_by_user_type);

-- Optional: Add a foreign key constraint if there's a common user table,
-- but for now, we'll rely on application-level logic for user type.

-- Optionally add location_id to student table for per-student geofencing
ALTER TABLE `student` ADD COLUMN `location_id` INT(11) NULL AFTER `section`;

-- Add foreign key constraint
ALTER TABLE `student` ADD CONSTRAINT `fk_student_location` FOREIGN KEY (`location_id`) REFERENCES `geofence_locations`(`location_id`) ON DELETE SET NULL;

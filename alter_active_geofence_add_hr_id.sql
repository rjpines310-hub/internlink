ALTER TABLE `active_geofence`
ADD COLUMN `hr_id` INT(11) DEFAULT NULL AFTER `radius`,
ADD CONSTRAINT `fk_active_geofence_hr_id` FOREIGN KEY (`hr_id`) REFERENCES `companyhr` (`hr_id`) ON DELETE CASCADE ON UPDATE CASCADE;

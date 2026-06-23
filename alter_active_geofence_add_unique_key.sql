ALTER TABLE `active_geofence`
ADD UNIQUE KEY `uq_company_faculty_geofence` (`hr_id`, `set_by`, `set_by_user_type`);

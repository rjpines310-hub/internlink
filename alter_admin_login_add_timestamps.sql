-- Add timestamps to admin_login table
ALTER TABLE `admin_login`
ADD COLUMN `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
ADD COLUMN `last_login` TIMESTAMP NULL;

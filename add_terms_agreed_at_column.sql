-- Migration script to add terms_agreed_at column to user tables

ALTER TABLE student
ADD COLUMN terms_agreed_at DATETIME NULL;

ALTER TABLE faculty
ADD COLUMN terms_agreed_at DATETIME NULL;

ALTER TABLE companyhr
ADD COLUMN terms_agreed_at DATETIME NULL;

ALTER TABLE supervisor
ADD COLUMN terms_agreed_at DATETIME NULL;

-- The new column terms_agreed_at will store the timestamp of when the user agreed to the terms

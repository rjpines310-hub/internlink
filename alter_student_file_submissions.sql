ALTER TABLE `student_file_submissions`
ADD COLUMN `dtr_file_checked` BOOLEAN DEFAULT FALSE,
ADD COLUMN `moa_file_checked` BOOLEAN DEFAULT FALSE,
ADD COLUMN `letter_of_acceptance_file_checked` BOOLEAN DEFAULT FALSE,
ADD COLUMN `evaluation_form_file_checked` BOOLEAN DEFAULT FALSE;

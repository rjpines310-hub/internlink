USE capstone;

-- Add post_id column to student table
ALTER TABLE student 
ADD COLUMN post_id INT(11) DEFAULT NULL AFTER hr_id;

-- Add foreign key constraint
ALTER TABLE student 
ADD CONSTRAINT fk_student_post_id 
FOREIGN KEY (post_id) REFERENCES internship_posts(post_id) 
ON DELETE SET NULL ON UPDATE CASCADE;

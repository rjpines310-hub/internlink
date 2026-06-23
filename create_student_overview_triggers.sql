DELIMITER //

DROP TRIGGER IF EXISTS after_student_insert //
CREATE TRIGGER after_student_insert
AFTER INSERT ON student
FOR EACH ROW
BEGIN
    CALL CalculateStudentOverview(NEW.student_id);
END //

DROP TRIGGER IF EXISTS after_student_update //
CREATE TRIGGER after_student_update
AFTER UPDATE ON student
FOR EACH ROW
BEGIN
    CALL CalculateStudentOverview(NEW.student_id);
END //

-- Triggers for timecard, tasks, and student_file_submissions to update student_overview
DROP TRIGGER IF EXISTS after_timecard_insert //
CREATE TRIGGER after_timecard_insert
AFTER INSERT ON timecard
FOR EACH ROW
BEGIN
    CALL CalculateStudentOverview(NEW.student_id);
END //

DROP TRIGGER IF EXISTS after_timecard_update //
CREATE TRIGGER after_timecard_update
AFTER UPDATE ON timecard
FOR EACH ROW
BEGIN
    CALL CalculateStudentOverview(NEW.student_id);
END //

DROP TRIGGER IF EXISTS after_tasks_insert //
CREATE TRIGGER after_tasks_insert
AFTER INSERT ON tasks
FOR EACH ROW
BEGIN
    CALL CalculateStudentOverview(NEW.student_id);
END //

DROP TRIGGER IF EXISTS after_tasks_update //
CREATE TRIGGER after_tasks_update
AFTER UPDATE ON tasks
FOR EACH ROW
BEGIN
    CALL CalculateStudentOverview(NEW.student_id);
END //

DROP TRIGGER IF EXISTS after_file_submissions_insert //
CREATE TRIGGER after_file_submissions_insert
AFTER INSERT ON student_file_submissions
FOR EACH ROW
BEGIN
    CALL CalculateStudentOverview(NEW.student_id);
END //

DROP TRIGGER IF EXISTS after_file_submissions_update //
CREATE TRIGGER after_file_submissions_update
AFTER UPDATE ON student_file_submissions
FOR EACH ROW
BEGIN
    CALL CalculateStudentOverview(NEW.student_id);
END //

DELIMITER ;

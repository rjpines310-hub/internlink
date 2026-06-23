DELIMITER //

DROP PROCEDURE IF EXISTS CalculateStudentOverview //

CREATE PROCEDURE CalculateStudentOverview(IN p_student_id INT)
BEGIN
    DECLARE v_attendance_score DECIMAL(5,2);
    DECLARE v_performance_score DECIMAL(5,2);
    DECLARE v_file_submissions_score DECIMAL(5,2);
    DECLARE v_overall_average DECIMAL(5,2);
    DECLARE v_employment_status VARCHAR(255);
    DECLARE v_hr_id INT;
    DECLARE v_post_id INT;
    DECLARE v_section_name VARCHAR(255);
    DECLARE v_target_ojt_hours INT DEFAULT 200; -- Default fallback as in student.php

    -- Fetch employment_status, hr_id, post_id, and section from student table
    SELECT employment_status, hr_id, post_id, section
    INTO v_employment_status, v_hr_id, v_post_id, v_section_name
    FROM student
    WHERE student_id = p_student_id;

    -- Fetch target OJT hours from sections table
    IF v_section_name IS NOT NULL AND v_section_name != '' THEN
        SELECT ojt_hours INTO v_target_ojt_hours FROM sections WHERE section_name = v_section_name;
        IF v_target_ojt_hours IS NULL OR v_target_ojt_hours <= 0 THEN
            SET v_target_ojt_hours = 200; -- Fallback if section not found or hours invalid
        END IF;
    END IF;

    -- Calculate attendance
    SELECT
        ROUND(LEAST(100, (COALESCE(SUM(TIMESTAMPDIFF(MINUTE, time_in, time_out) / 60), 0) / v_target_ojt_hours) * 100))
    INTO v_attendance_score
    FROM timecard
    WHERE student_id = p_student_id AND status = 'Validated';

    IF v_attendance_score IS NULL THEN
        SET v_attendance_score = 0.00;
    END IF;

    -- Calculate performance (original calculation)
    SELECT
        ROUND(
            COALESCE(
                (SUM(CASE WHEN status = 'completed' THEN COALESCE(score, 0) WHEN status = 'missed' THEN 50 ELSE 0 END) /
                (COUNT(CASE WHEN status IN ('completed', 'missed') THEN 1 END) * 100)) * 100,
            100.00) -- Default to 100 if no scored tasks
        )
    INTO v_performance_score
    FROM tasks
    WHERE student_id = p_student_id AND status IN ('completed', 'missed');

    IF v_performance_score IS NULL THEN
        SET v_performance_score = 100.00;
    END IF;

    -- Apply conditional performance based on employment status (new logic)
    IF v_employment_status = 'pending' THEN
        SET v_performance_score = 0;
    END IF;

    -- Calculate file submissions
    SELECT
        ROUND(
            COALESCE(
                (
                    (CASE WHEN dtr_file_checked = 1 THEN 1 ELSE 0 END) +
                    (CASE WHEN moa_file_checked = 1 THEN 1 ELSE 0 END) +
                    (CASE WHEN letter_of_acceptance_file_checked = 1 THEN 1 ELSE 0 END) +
                    (CASE WHEN evaluation_form_file_checked = 1 THEN 1 ELSE 0 END)
                ) / 4 * 100,
            0.00)
        )
    INTO v_file_submissions_score
    FROM student_file_submissions
    WHERE student_id = p_student_id;

    IF v_file_submissions_score IS NULL THEN
        SET v_file_submissions_score = 0.00;
    END IF;

    -- Calculate overall_average
    SET v_overall_average = (v_attendance_score + v_performance_score + v_file_submissions_score) / 3;

    -- Insert or Update student_overview
    INSERT INTO student_overview (student_id, employment_status, attendance, performance, file_submissions, overall_average, hr_id, post_id)
    VALUES (p_student_id, v_employment_status, v_attendance_score, v_performance_score, v_file_submissions_score, v_overall_average, v_hr_id, v_post_id)
    ON DUPLICATE KEY UPDATE
        employment_status = v_employment_status,
        attendance = v_attendance_score,
        performance = v_performance_score,
        file_submissions = v_file_submissions_score,
        overall_average = v_overall_average,
        hr_id = v_hr_id,
        post_id = v_post_id;

END //

DELIMITER ;

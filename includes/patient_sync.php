<?php

/**
 * Synchronize the `patients` table with data coming from the `users`
 * and `user_profiles` tables. This ensures that every user who has the
 * role `patient` owns a corresponding patient record that doctors,
 * nurses, dentists, and staff can view.
 */
function syncPatientRecords(PDO $pdo): void
{
    // Insert missing patient rows for any user with the patient role
    $insertSql = "
        INSERT INTO patients (
            user_id,
            sr_code,
            fname,
            mname,
            lname,
            full_name,
            email,
            contact_number,
            date_of_birth,
            age,
            sex,
            blood_type,
            address,
            program,
            year_level,
            allergies,
            medical_condition,
            guardian_name,
            guardian_contact,
            guardian_relationship,
            created_at,
            updated_at
        )
        SELECT
            u.id,
            '',
            u.fname,
            u.mname,
            u.lname,
            TRIM(CONCAT_WS(' ', u.fname, u.lname)),
            u.email,
            u.phone,
            up.dob,
            CASE 
                WHEN up.dob IS NOT NULL AND up.dob != '' THEN 
                    TIMESTAMPDIFF(YEAR, up.dob, CURDATE())
                ELSE NULL
            END,
            u.gender,
            up.blood_type,
            up.address,
            COALESCE(up.course, ''),
            COALESCE(up.year_level, ''),
            up.allergies,
            up.medical_conditions,
            up.guardian_name,
            up.guardian_contact,
            up.guardian_relationship,
            NOW(),
            NOW()
        FROM users u
        LEFT JOIN user_profiles up ON up.user_id = u.id
        LEFT JOIN patients p ON p.user_id = u.id
        WHERE u.role = 'patient'
          AND p.id IS NULL
    ";

    $pdo->exec($insertSql);

    // Keep existing patient rows up to date with the latest profile data
    $updateSql = "
        UPDATE patients p
        JOIN users u ON u.id = p.user_id
        LEFT JOIN user_profiles up ON up.user_id = u.id
        SET
            p.fname = u.fname,
            p.mname = u.mname,
            p.lname = u.lname,
            p.full_name = TRIM(CONCAT_WS(' ', u.fname, u.lname)),
            p.email = u.email,
            p.contact_number = u.phone,
            p.date_of_birth = up.dob,
            p.age = CASE 
                WHEN up.dob IS NOT NULL AND up.dob != '' THEN 
                    TIMESTAMPDIFF(YEAR, up.dob, CURDATE())
                ELSE NULL
            END,
            p.sex = COALESCE(u.gender, p.sex),
            p.blood_type = up.blood_type,
            p.address = COALESCE(up.address, p.address),
            p.program = COALESCE(up.course, p.program),
            p.year_level = COALESCE(up.year_level, p.year_level),
            p.allergies = COALESCE(up.allergies, p.allergies),
            p.medical_condition = COALESCE(up.medical_conditions, p.medical_condition),
            p.guardian_name = COALESCE(up.guardian_name, p.guardian_name),
            p.guardian_contact = COALESCE(up.guardian_contact, p.guardian_contact),
            p.guardian_relationship = COALESCE(up.guardian_relationship, p.guardian_relationship),
            p.updated_at = NOW()
        WHERE u.role = 'patient'
    ";

    $pdo->exec($updateSql);
}


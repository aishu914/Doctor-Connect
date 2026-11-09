-- ==============================================================================
-- DOCTOR CONNECT - DATABASE SCHEMA (MySQL 5.7+ / MariaDB 10.3+)
-- Token-Based Online Doctor Consultation Platform
-- ==============================================================================

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS queue_logs;
DROP TABLE IF EXISTS prescriptions;
DROP TABLE IF EXISTS notifications;
DROP TABLE IF EXISTS appointments;
DROP TABLE IF EXISTS doctor_schedules;
DROP TABLE IF EXISTS doctors;
DROP TABLE IF EXISTS clinic_settings;
DROP TABLE IF EXISTS users;
SET FOREIGN_KEY_CHECKS = 1;

-- 1. USERS TABLE
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    phone VARCHAR(20) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('patient', 'doctor', 'admin') NOT NULL DEFAULT 'patient',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user_role (role),
    INDEX idx_user_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. DOCTORS TABLE
CREATE TABLE doctors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    specialization VARCHAR(100) NOT NULL,
    qualification VARCHAR(150) DEFAULT 'MBBS, MD',
    consultation_fee DECIMAL(10,2) NOT NULL DEFAULT 500.00,
    avg_consultation_mins INT NOT NULL DEFAULT 15,
    bio TEXT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_doctors_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_doctor_specialization (specialization)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. DOCTOR SCHEDULES TABLE
-- day_of_week: 0 = Sunday, 1 = Monday, 2 = Tuesday, 3 = Wednesday, 4 = Thursday, 5 = Friday, 6 = Saturday
CREATE TABLE doctor_schedules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    doctor_id INT NOT NULL,
    day_of_week TINYINT NOT NULL COMMENT '0=Sun ... 6=Sat',
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    slot_duration INT NOT NULL DEFAULT 15 COMMENT 'Minutes per consultation',
    max_tokens INT NOT NULL DEFAULT 30,
    is_active TINYINT(1) DEFAULT 1,
    CONSTRAINT fk_schedules_doctor FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE CASCADE,
    UNIQUE KEY uq_doctor_day_time (doctor_id, day_of_week, start_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. APPOINTMENTS TABLE (WITH CRITICAL RACE-SAFE UNIQUE CONSTRAINT)
CREATE TABLE appointments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    doctor_id INT NOT NULL,
    appointment_date DATE NOT NULL,
    token_number INT NOT NULL,
    status ENUM('waiting', 'serving', 'completed', 'no_show', 'cancelled') NOT NULL DEFAULT 'waiting',
    meeting_link VARCHAR(500) NULL,
    estimated_time DATETIME NULL,
    reason_for_visit VARCHAR(255) NULL,
    cancellation_reason VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_appt_patient FOREIGN KEY (patient_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_appt_doctor FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE CASCADE,
    -- CRITICAL RACE-SAFETY CONSTRAINT:
    UNIQUE KEY unique_token_per_doctor_day (doctor_id, appointment_date, token_number),
    INDEX idx_appt_date_doc_status (appointment_date, doctor_id, status),
    INDEX idx_appt_patient_date (patient_id, appointment_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. PRESCRIPTIONS TABLE
CREATE TABLE prescriptions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    appointment_id INT NOT NULL UNIQUE,
    doctor_id INT NOT NULL,
    patient_id INT NOT NULL,
    diagnosis VARCHAR(255) NOT NULL,
    medicines JSON NULL COMMENT 'Structured list of medicines, dosage, instructions',
    clinical_notes TEXT NULL,
    follow_up_date DATE NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_presc_appointment FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE CASCADE,
    CONSTRAINT fk_presc_doctor FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE CASCADE,
    CONSTRAINT fk_presc_patient FOREIGN KEY (patient_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. QUEUE LOGS (AUDIT TRAIL)
CREATE TABLE queue_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    appointment_id INT NOT NULL,
    action ENUM('called', 'served', 'no_show', 'skipped', 'cancelled', 'recalled') NOT NULL,
    performed_by INT NULL,
    action_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_queue_appt FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE CASCADE,
    CONSTRAINT fk_queue_user FOREIGN KEY (performed_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_queue_action_time (action_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. NOTIFICATIONS TABLE
CREATE TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(150) NOT NULL,
    message TEXT NOT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_notif_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_read (user_id, is_read)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. CLINIC SETTINGS TABLE
CREATE TABLE clinic_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(50) NOT NULL UNIQUE,
    setting_value TEXT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- DEFAULT CLINIC SETTINGS
INSERT INTO clinic_settings (setting_key, setting_value) VALUES
('clinic_name', 'DoctorConnect Telehealth Clinic'),
('clinic_phone', '+1 (555) 234-5678'),
('clinic_email', 'care@doctorconnect.com'),
('clinic_address', '100 Innovation Way, Suite 400, Health City'),
('cancellation_cutoff_hours', '2'),
('default_slot_duration', '15'),
('jitsi_domain', 'meet.jit.si');

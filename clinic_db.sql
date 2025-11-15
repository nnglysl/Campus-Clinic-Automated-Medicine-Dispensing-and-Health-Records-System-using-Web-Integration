-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 12, 2025 at 04:10 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `clinic_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `appointments`
--

CREATE TABLE `appointments` (
  `id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `appointment_date` date NOT NULL,
  `appointment_time` time NOT NULL,
  `appointment_type` enum('medical','dental') NOT NULL DEFAULT 'medical',
  `status` enum('scheduled','confirmed','cancelled','completed') NOT NULL DEFAULT 'scheduled',
  `notes` text DEFAULT NULL,
  `calendar_event_id` varchar(255) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `user_id` int(11) DEFAULT NULL,
  `employee_id` varchar(50) DEFAULT NULL,
  `position` varchar(100) DEFAULT NULL,
  `department` varchar(100) DEFAULT NULL,
  `hire_date` date DEFAULT NULL,
  `last_synced` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `appointments`
--

INSERT INTO `appointments` (`id`, `patient_id`, `appointment_date`, `appointment_time`, `appointment_type`, `status`, `notes`, `calendar_event_id`, `created_by`, `created_at`, `updated_at`, `user_id`, `employee_id`, `position`, `department`, `hire_date`, `last_synced`) VALUES
(12, 1, '2025-11-10', '10:00:00', 'medical', 'scheduled', 'Initial checkup', NULL, 1, '2025-11-05 16:47:14', '2025-11-05 17:04:16', 1, NULL, NULL, NULL, NULL, NULL),
(13, 3, '2025-11-11', '17:00:00', 'medical', 'scheduled', NULL, NULL, NULL, '2025-11-07 02:09:39', '2025-11-07 02:09:39', NULL, NULL, NULL, NULL, NULL, NULL),
(14, 3, '2025-11-10', '11:00:00', 'medical', 'scheduled', NULL, NULL, NULL, '2025-11-07 08:48:27', '2025-11-07 08:48:27', NULL, NULL, NULL, NULL, NULL, NULL),
(15, 3, '2025-11-24', '13:00:00', 'dental', 'scheduled', NULL, NULL, NULL, '2025-11-07 13:59:52', '2025-11-07 13:59:52', NULL, NULL, NULL, NULL, NULL, NULL),
(16, 3, '2025-11-18', '14:00:00', 'dental', 'scheduled', NULL, NULL, NULL, '2025-11-07 14:49:39', '2025-11-07 14:49:39', NULL, NULL, NULL, NULL, NULL, NULL),
(17, 3, '2025-11-17', '10:00:00', 'medical', 'scheduled', NULL, NULL, NULL, '2025-11-07 15:13:44', '2025-11-07 15:13:44', NULL, NULL, NULL, NULL, NULL, NULL),
(18, 3, '2025-11-12', '14:00:00', 'dental', 'scheduled', NULL, NULL, NULL, '2025-11-07 15:13:52', '2025-11-07 15:13:52', NULL, NULL, NULL, NULL, NULL, NULL),
(19, 3, '2025-11-12', '12:00:00', 'dental', 'scheduled', NULL, NULL, NULL, '2025-11-07 15:14:02', '2025-11-07 15:14:02', NULL, NULL, NULL, NULL, NULL, NULL),
(20, 3, '2025-11-19', '15:00:00', 'dental', 'scheduled', NULL, NULL, NULL, '2025-11-08 03:42:49', '2025-11-08 03:42:49', NULL, NULL, NULL, NULL, NULL, NULL),
(21, 3, '2025-11-12', '13:00:00', 'dental', 'scheduled', NULL, NULL, NULL, '2025-11-08 03:42:59', '2025-11-08 03:42:59', NULL, NULL, NULL, NULL, NULL, NULL),
(22, 3, '2025-11-12', '13:00:00', 'dental', 'scheduled', NULL, NULL, NULL, '2025-11-08 03:43:01', '2025-11-08 03:43:01', NULL, NULL, NULL, NULL, NULL, NULL),
(23, 3, '2025-11-10', '06:00:00', 'medical', 'scheduled', NULL, NULL, NULL, '2025-11-08 14:21:44', '2025-11-08 14:21:44', NULL, NULL, NULL, NULL, NULL, NULL),
(24, 3, '2025-11-11', '09:58:00', 'dental', 'scheduled', NULL, NULL, NULL, '2025-11-10 15:21:36', '2025-11-10 15:21:36', NULL, NULL, NULL, NULL, NULL, NULL),
(25, 3, '2025-11-19', '08:00:00', 'dental', 'scheduled', NULL, NULL, NULL, '2025-11-11 14:01:13', '2025-11-11 14:01:13', NULL, NULL, NULL, NULL, NULL, NULL);

--
-- Triggers `appointments`
--
DELIMITER $$
CREATE TRIGGER `after_appointment_insert` AFTER INSERT ON `appointments` FOR EACH ROW BEGIN
    INSERT INTO schedule_notifications (notification_data, created_at, expires_at)
    VALUES (
        JSON_OBJECT(
            'type', 'appointment_change',
            'action', 'create',
            'appointment_id', NEW.id,
            'patient_id', NEW.patient_id,
            'appointment_date', NEW.appointment_date,
            'appointment_time', NEW.appointment_time,
            'status', NEW.status
        ),
        NOW(),
        DATE_ADD(NOW(), INTERVAL 1 HOUR)
    );
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `after_appointment_update` AFTER UPDATE ON `appointments` FOR EACH ROW BEGIN
    IF OLD.status != NEW.status THEN
        INSERT INTO schedule_notifications (notification_data, created_at, expires_at)
        VALUES (
            JSON_OBJECT(
                'type', 'appointment_change',
                'action', 'update',
                'appointment_id', NEW.id,
                'appointment_date', NEW.appointment_date,
                'appointment_time', NEW.appointment_time,
                'old_status', OLD.status,
                'new_status', NEW.status
            ),
            NOW(),
            DATE_ADD(NOW(), INTERVAL 1 HOUR)
        );
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `appointment_history`
--

CREATE TABLE `appointment_history` (
  `id` int(11) NOT NULL,
  `appointment_id` int(11) NOT NULL,
  `action` enum('created','updated','cancelled','completed','rescheduled') NOT NULL,
  `old_date` date DEFAULT NULL,
  `old_time` time DEFAULT NULL,
  `new_date` date DEFAULT NULL,
  `new_time` time DEFAULT NULL,
  `changed_by` int(11) DEFAULT NULL,
  `changed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `appointment_notifications`
--

CREATE TABLE `appointment_notifications` (
  `id` int(11) NOT NULL,
  `appointment_id` int(11) NOT NULL,
  `notification_type` enum('sms','email','push') NOT NULL,
  `sent_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('pending','sent','failed') NOT NULL DEFAULT 'pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `attendance`
--

CREATE TABLE `attendance` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `date` date NOT NULL,
  `time_in` time DEFAULT NULL,
  `time_out` time DEFAULT NULL,
  `status` enum('present','absent','late','half_day') DEFAULT 'present',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `attendance`
--

INSERT INTO `attendance` (`id`, `user_id`, `date`, `time_in`, `time_out`, `status`, `notes`, `created_at`, `updated_at`) VALUES
(1, 5, '2025-11-05', '23:39:02', NULL, 'late', NULL, '2025-11-05 22:39:02', '2025-11-05 22:39:02'),
(2, 5, '2025-11-06', '00:30:59', NULL, 'present', NULL, '2025-11-05 23:30:59', '2025-11-05 23:30:59'),
(3, 5, '2025-11-10', '09:37:03', '09:37:06', 'half_day', NULL, '2025-11-10 08:37:03', '2025-11-10 08:37:06');

-- --------------------------------------------------------

--
-- Table structure for table `doctor_schedules`
--

CREATE TABLE `doctor_schedules` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `schedule_date` date NOT NULL,
  `start_time` time DEFAULT NULL,
  `end_time` time DEFAULT NULL,
  `is_available` tinyint(1) DEFAULT 1,
  `schedule_type` enum('available','unavailable') NOT NULL DEFAULT 'available',
  `notes` text DEFAULT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `doctor_schedules`
--

INSERT INTO `doctor_schedules` (`id`, `user_id`, `schedule_date`, `start_time`, `end_time`, `is_available`, `schedule_type`, `notes`, `reason`, `created_at`, `updated_at`) VALUES
(13, 5, '2025-11-26', '08:00:00', '18:00:00', 1, 'available', NULL, NULL, '2025-11-11 08:26:16', '2025-11-11 08:26:16'),
(14, 6, '2025-11-18', '08:00:00', '18:00:00', 1, 'available', NULL, NULL, '2025-11-11 08:26:53', '2025-11-11 08:27:57'),
(15, 6, '2025-11-19', '08:00:00', '18:00:00', 1, 'available', NULL, NULL, '2025-11-11 08:28:48', '2025-11-11 08:28:48'),
(16, 6, '2025-11-20', '08:00:00', '18:00:00', 1, 'available', NULL, NULL, '2025-11-11 08:37:55', '2025-11-11 08:37:55'),
(18, 6, '2025-11-06', NULL, NULL, 0, 'unavailable', NULL, '', '2025-11-11 08:39:17', '2025-11-11 08:39:17'),
(19, 6, '2025-11-26', '08:00:00', '18:00:00', 1, 'available', NULL, NULL, '2025-11-11 08:39:24', '2025-11-11 08:39:24'),
(20, 5, '2025-11-20', '08:00:00', '18:00:00', 1, 'available', NULL, NULL, '2025-11-11 15:47:37', '2025-11-11 15:47:37'),
(21, 5, '2025-11-12', '08:00:00', '18:00:00', 1, 'available', NULL, NULL, '2025-11-11 16:06:05', '2025-11-11 16:06:05');

--
-- Triggers `doctor_schedules`
--
DELIMITER $$
CREATE TRIGGER `after_schedule_insert` AFTER INSERT ON `doctor_schedules` FOR EACH ROW BEGIN
    INSERT INTO schedule_notifications (notification_data, created_at, expires_at)
    VALUES (
        JSON_OBJECT(
            'type', 'schedule_change',
            'action', 'create',
            'schedule_id', NEW.id,
            'user_id', NEW.user_id,
            'schedule_date', NEW.schedule_date,
            'schedule_type', NEW.schedule_type,
            'start_time', NEW.start_time,
            'end_time', NEW.end_time
        ),
        NOW(),
        DATE_ADD(NOW(), INTERVAL 1 HOUR)
    );
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `after_schedule_update` AFTER UPDATE ON `doctor_schedules` FOR EACH ROW BEGIN
    IF OLD.is_available != NEW.is_available OR 
       OLD.schedule_type != NEW.schedule_type OR
       OLD.start_time != NEW.start_time OR
       OLD.end_time != NEW.end_time THEN
        
        INSERT INTO schedule_notifications (notification_data, created_at, expires_at)
        VALUES (
            JSON_OBJECT(
                'type', 'schedule_change',
                'action', 'update',
                'schedule_id', NEW.id,
                'user_id', NEW.user_id,
                'schedule_date', NEW.schedule_date,
                'old_is_available', OLD.is_available,
                'new_is_available', NEW.is_available,
                'old_schedule_type', OLD.schedule_type,
                'new_schedule_type', NEW.schedule_type
            ),
            NOW(),
            DATE_ADD(NOW(), INTERVAL 1 HOUR)
        );
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `employees`
--

CREATE TABLE `employees` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `first_name` varchar(100) NOT NULL,
  `middle_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) NOT NULL,
  `birth_date` date NOT NULL,
  `age` int(11) NOT NULL,
  `gender` enum('Male','Female') NOT NULL,
  `email` varchar(150) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `address` text NOT NULL,
  `role` enum('admin','doctor','dentist','nurse','staff','employee') NOT NULL,
  `username` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `photo` longtext DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `employees`
--

INSERT INTO `employees` (`id`, `user_id`, `first_name`, `middle_name`, `last_name`, `birth_date`, `age`, `gender`, `email`, `phone`, `address`, `role`, `username`, `password`, `photo`, `status`, `created_at`, `updated_at`) VALUES
(6, 5, 'Neo', 'Cornejo', 'Rangel', '2003-12-02', 21, 'Male', '23-32379@g.batstate-u.edu.ph', '09453734193', 'San Jose, Batangas', 'doctor', '23-32379', '$2y$10$yZQWwNY0xvS18TlQ2kBLpeK2Ck71FFTtF1vUmGizXTvDxECgoC4F6', NULL, 'active', '2025-11-05 11:27:25', '2025-11-05 17:21:18'),
(7, 6, 'Joana', '', 'Briones', '2003-08-04', 22, 'Female', 'joanabriones@g.batstate-u.edu.ph', '09123456789', 'Pinagtungulan, San Jose', 'dentist', 'joanabriones', '$2y$10$WguPETAl.C8upDmFNz/9iOkvlHrOkWLAdJxNzBzu.CGk.GbGC9Tju', NULL, 'active', '2025-11-11 04:27:58', '2025-11-11 04:33:38');

-- --------------------------------------------------------

--
-- Table structure for table `employee_attendance`
--

CREATE TABLE `employee_attendance` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `check_in_time` datetime NOT NULL,
  `check_out_time` datetime DEFAULT NULL,
  `status` enum('in','out','break') NOT NULL DEFAULT 'in',
  `date` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `employee_schedules`
--

CREATE TABLE `employee_schedules` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `day_of_week` enum('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday') NOT NULL,
  `time_in` time NOT NULL,
  `time_out` time NOT NULL,
  `break_start` time DEFAULT NULL,
  `break_end` time DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `inventory`
--

CREATE TABLE `inventory` (
  `id` int(11) NOT NULL,
  `batchId` varchar(50) NOT NULL,
  `code` varchar(50) NOT NULL,
  `name` varchar(255) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 0,
  `dispensed` int(11) NOT NULL DEFAULT 0,
  `expiry` date NOT NULL,
  `description` text NOT NULL,
  `status` enum('active','bod','archive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `inventory`
--

INSERT INTO `inventory` (`id`, `batchId`, `code`, `name`, `quantity`, `dispensed`, `expiry`, `description`, `status`, `created_at`, `updated_at`) VALUES
(1, 'BATCH001', 'MED001', 'PVRV', 70, 30, '2025-12-31', 'Anti Rabies Vaccine', 'active', '2025-11-04 23:27:00', '2025-11-04 23:27:00'),
(2, 'BATCH002', 'MED002', 'TETANUS TOXOID', 5, 20, '2025-11-15', 'Anti Tetanus Vaccine', 'active', '2025-11-04 23:27:00', '2025-11-04 23:27:00'),
(3, 'BATCH003', 'MED003', 'ERIG', 0, 15, '2025-10-15', 'Immuno Globulin', 'archive', '2025-11-04 23:27:00', '2025-11-04 23:27:26'),
(4, 'BATCH004', 'MED001', 'Paracetamol', 8, 50, '2025-11-01', 'Pain reliever - Batch 1', 'archive', '2025-11-04 23:27:00', '2025-11-04 23:27:26'),
(5, 'BATCH005', 'MED004', 'Amoxicillin', 150, 35, '2024-09-20', 'Antibiotic', 'archive', '2025-11-04 23:27:00', '2025-11-04 23:27:26'),
(6, 'BATCH006', 'MED005', 'Ibuprofen', 0, 40, '2024-08-10', 'Anti-inflammatory', 'archive', '2025-11-04 23:27:00', '2025-11-04 23:27:00'),
(7, 'BATCH007', 'MED001', 'Paracetamol', 100, 10, '2026-03-15', 'Pain reliever - Batch 2', 'active', '2025-11-04 23:27:00', '2025-11-04 23:27:00');

-- --------------------------------------------------------

--
-- Table structure for table `medical_records`
--

CREATE TABLE `medical_records` (
  `id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `employee_id` int(11) DEFAULT NULL,
  `visit_date` date NOT NULL,
  `visit_time` time NOT NULL,
  `chief_complaint` text NOT NULL,
  `diagnosis` text NOT NULL,
  `treatment_instructions` text DEFAULT NULL,
  `blood_pressure` varchar(20) DEFAULT NULL,
  `heart_rate` int(11) DEFAULT NULL,
  `temperature` decimal(4,1) DEFAULT NULL,
  `weight` decimal(5,2) DEFAULT NULL,
  `height` int(11) DEFAULT NULL,
  `bmi` decimal(4,1) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `medicine_dispensed`
--

CREATE TABLE `medicine_dispensed` (
  `id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `inventory_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `dispensed_date` date NOT NULL,
  `dispensed_time` time NOT NULL,
  `dispensed_by` int(11) NOT NULL,
  `purpose` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `otp_verifications`
--

CREATE TABLE `otp_verifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `otp_code` varchar(6) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `is_verified` tinyint(1) DEFAULT 0,
  `expires_at` datetime DEFAULT NULL,
  `verified_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `patients`
--

CREATE TABLE `patients` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `full_name` varchar(255) NOT NULL,
  `sr_code` varchar(50) NOT NULL,
  `position` enum('Student','Faculty','Staff') NOT NULL,
  `date_of_birth` date NOT NULL,
  `age` int(11) NOT NULL,
  `gender` enum('Male','Female') NOT NULL,
  `email` varchar(255) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `address` text NOT NULL,
  `emergency_contact_name` varchar(255) NOT NULL,
  `emergency_contact_relationship` varchar(100) NOT NULL,
  `emergency_contact_phone` varchar(20) NOT NULL,
  `blood_type` varchar(5) DEFAULT NULL,
  `allergies` text DEFAULT NULL,
  `medical_conditions` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `patients`
--

INSERT INTO `patients` (`id`, `user_id`, `full_name`, `sr_code`, `position`, `date_of_birth`, `age`, `gender`, `email`, `phone`, `address`, `emergency_contact_name`, `emergency_contact_relationship`, `emergency_contact_phone`, `blood_type`, `allergies`, `medical_conditions`, `created_at`, `updated_at`) VALUES
(1, NULL, 'Glysel Sales', '23-31123', 'Student', '2003-03-15', 22, 'Female', 'glysel.sales@g.batstate-u.edu.ph', '+63 912 345 6789', '123 Main Street, Batangas City, Batangas', 'Maria Sales', 'Mother', '+63 917 654 3210', 'O+', 'Penicillin, Shellfish', 'Asthma', '2025-11-04 23:55:14', '2025-11-04 23:55:14'),
(2, NULL, 'Joana Santos', '23-14507', 'Student', '2004-07-22', 21, 'Female', 'joana.santos@g.batstate-u.edu.ph', '+63 923 456 7890', '456 Secondary Road, Lipa City, Batangas', 'Roberto Santos', 'Father', '+63 918 765 4321', 'A+', 'None', 'None', '2025-11-04 23:55:14', '2025-11-04 23:55:14'),
(3, NULL, 'Josh Matibag', '23-15409', 'Student', '2003-11-08', 21, 'Male', 'josh.matibag@g.batstate-u.edu.ph', '+63 934 567 8901', '789 University Ave, Batangas City, Batangas', 'Linda Matibag', 'Mother', '+63 919 876 5432', 'B+', 'Peanuts', 'None', '2025-11-04 23:55:14', '2025-11-04 23:55:14'),
(4, NULL, 'Maria Cruz', '23-16720', 'Faculty', '1985-01-12', 40, 'Female', 'maria.cruz@batstate-u.edu.ph', '+63 945 678 9012', '321 Faculty Village, Batangas City, Batangas', 'Pedro Cruz', 'Spouse', '+63 920 987 6543', 'AB+', 'Latex', 'Hypertension', '2025-11-04 23:55:14', '2025-11-04 23:55:14'),
(5, NULL, 'Juan Dela Cruz', '23-18901', 'Staff', '1990-05-30', 35, 'Male', 'juan.delacruz@batstate-u.edu.ph', '+63 956 789 0123', '654 Staff Housing, Lipa City, Batangas', 'Ana Dela Cruz', 'Spouse', '+63 921 098 7654', 'O-', 'None', 'Diabetes Type 2', '2025-11-04 23:55:14', '2025-11-04 23:55:14');

-- --------------------------------------------------------

--
-- Table structure for table `prescriptions`
--

CREATE TABLE `prescriptions` (
  `id` int(11) NOT NULL,
  `medical_record_id` int(11) NOT NULL,
  `inventory_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `dosage_instructions` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `schedule_change_log`
--

CREATE TABLE `schedule_change_log` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `schedule_id` int(11) DEFAULT NULL,
  `action` enum('create','update','delete') NOT NULL,
  `old_data` text DEFAULT NULL,
  `new_data` text DEFAULT NULL,
  `changed_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `schedule_change_log`
--

INSERT INTO `schedule_change_log` (`id`, `user_id`, `schedule_id`, `action`, `old_data`, `new_data`, `changed_at`) VALUES
(1, 5, NULL, 'create', NULL, '{\"schedule_date\":\"2025-11-12\",\"start_time\":null,\"end_time\":null,\"schedule_type\":\"unavailable\",\"reason\":\"\"}', '2025-11-08 12:44:39'),
(2, 5, NULL, 'create', NULL, '{\"schedule_date\":\"2025-11-13\",\"start_time\":\"07:00\",\"end_time\":\"19:00\",\"schedule_type\":\"available\",\"reason\":null}', '2025-11-08 12:45:16'),
(3, 5, NULL, 'create', NULL, '{\"schedule_date\":\"2025-11-27\",\"start_time\":\"08:00\",\"end_time\":\"15:00\",\"schedule_type\":\"available\",\"reason\":null}', '2025-11-08 23:10:25'),
(4, 5, NULL, 'create', NULL, '{\"schedule_date\":\"2025-11-27\",\"start_time\":\"18:00\",\"end_time\":\"19:00\",\"schedule_type\":\"available\",\"reason\":null}', '2025-11-08 23:10:40'),
(5, 5, NULL, 'create', NULL, '{\"schedule_date\":\"2025-11-26\",\"start_time\":null,\"end_time\":null,\"schedule_type\":\"unavailable\",\"reason\":\"\"}', '2025-11-08 23:11:13'),
(6, 5, NULL, 'create', NULL, '{\"schedule_date\":\"2025-11-25\",\"start_time\":\"13:00\",\"end_time\":\"19:00\",\"schedule_type\":\"available\",\"reason\":null}', '2025-11-08 23:11:24'),
(7, 5, NULL, 'delete', '{\"id\":6,\"user_id\":5,\"schedule_date\":\"2025-11-07\",\"start_time\":null,\"end_time\":null,\"is_available\":1,\"schedule_type\":\"unavailable\",\"notes\":null,\"reason\":\"\",\"created_at\":\"2025-11-06 23:37:53\",\"updated_at\":\"2025-11-06 23:37:53\"}', NULL, '2025-11-08 23:19:45'),
(8, 5, NULL, 'delete', '{\"id\":7,\"user_id\":5,\"schedule_date\":\"2025-11-12\",\"start_time\":null,\"end_time\":null,\"is_available\":1,\"schedule_type\":\"unavailable\",\"notes\":null,\"reason\":\"\",\"created_at\":\"2025-11-08 12:44:39\",\"updated_at\":\"2025-11-08 12:44:39\"}', NULL, '2025-11-08 23:20:09');

-- --------------------------------------------------------

--
-- Table structure for table `schedule_notifications`
--

CREATE TABLE `schedule_notifications` (
  `id` int(11) NOT NULL,
  `notification_data` text NOT NULL,
  `created_at` datetime NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `expires_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `schedule_notifications`
--

INSERT INTO `schedule_notifications` (`id`, `notification_data`, `created_at`, `is_read`, `expires_at`) VALUES
(1, '{\"type\": \"schedule_change\", \"action\": \"create\", \"schedule_id\": 7, \"user_id\": 5, \"schedule_date\": \"2025-11-12\", \"schedule_type\": \"unavailable\", \"start_time\": null, \"end_time\": null}', '2025-11-08 12:44:39', 1, '2025-11-08 13:44:39'),
(2, '{\"type\": \"schedule_change\", \"action\": \"create\", \"schedule_id\": 8, \"user_id\": 5, \"schedule_date\": \"2025-11-13\", \"schedule_type\": \"available\", \"start_time\": \"07:00:00\", \"end_time\": \"19:00:00\"}', '2025-11-08 12:45:16', 1, '2025-11-08 13:45:16'),
(3, '{\"type\": \"appointment_change\", \"action\": \"create\", \"appointment_id\": 23, \"patient_id\": 3, \"appointment_date\": \"2025-11-10\", \"appointment_time\": \"06:00:00\", \"status\": \"scheduled\"}', '2025-11-08 22:21:44', 1, '2025-11-08 23:21:44'),
(4, '{\"type\": \"schedule_change\", \"action\": \"create\", \"schedule_id\": 9, \"user_id\": 5, \"schedule_date\": \"2025-11-27\", \"schedule_type\": \"available\", \"start_time\": \"08:00:00\", \"end_time\": \"15:00:00\"}', '2025-11-08 23:10:25', 0, '2025-11-09 00:10:25'),
(5, '{\"type\": \"schedule_change\", \"action\": \"create\", \"schedule_id\": 10, \"user_id\": 5, \"schedule_date\": \"2025-11-27\", \"schedule_type\": \"available\", \"start_time\": \"18:00:00\", \"end_time\": \"19:00:00\"}', '2025-11-08 23:10:40', 0, '2025-11-09 00:10:40'),
(6, '{\"type\": \"schedule_change\", \"action\": \"create\", \"schedule_id\": 11, \"user_id\": 5, \"schedule_date\": \"2025-11-26\", \"schedule_type\": \"unavailable\", \"start_time\": null, \"end_time\": null}', '2025-11-08 23:11:13', 0, '2025-11-09 00:11:13'),
(7, '{\"type\": \"schedule_change\", \"action\": \"create\", \"schedule_id\": 12, \"user_id\": 5, \"schedule_date\": \"2025-11-25\", \"schedule_type\": \"available\", \"start_time\": \"13:00:00\", \"end_time\": \"19:00:00\"}', '2025-11-08 23:11:24', 0, '2025-11-09 00:11:24'),
(8, '{\"type\": \"schedule_change\", \"action\": \"update\", \"schedule_id\": 6, \"user_id\": 5, \"schedule_date\": \"2025-11-07\", \"old_is_available\": 1, \"new_is_available\": 0, \"old_schedule_type\": \"unavailable\", \"new_schedule_type\": \"unavailable\"}', '2025-11-08 23:19:45', 0, '2025-11-09 00:19:45'),
(9, '{\"type\": \"schedule_change\", \"action\": \"update\", \"schedule_id\": 7, \"user_id\": 5, \"schedule_date\": \"2025-11-12\", \"old_is_available\": 1, \"new_is_available\": 0, \"old_schedule_type\": \"unavailable\", \"new_schedule_type\": \"unavailable\"}', '2025-11-08 23:20:09', 0, '2025-11-09 00:20:09'),
(10, '{\"type\": \"appointment_change\", \"action\": \"create\", \"appointment_id\": 24, \"patient_id\": 3, \"appointment_date\": \"2025-11-11\", \"appointment_time\": \"09:58:00\", \"status\": \"scheduled\"}', '2025-11-10 23:21:36', 0, '2025-11-11 00:21:36'),
(11, '{\"type\": \"schedule_change\", \"action\": \"create\", \"schedule_id\": 13, \"user_id\": 5, \"schedule_date\": \"2025-11-26\", \"schedule_type\": \"available\", \"start_time\": \"08:00:00\", \"end_time\": \"18:00:00\"}', '2025-11-11 16:26:16', 0, '2025-11-11 17:26:16'),
(12, '{\"type\": \"schedule_change\", \"action\": \"create\", \"schedule_id\": 14, \"user_id\": 6, \"schedule_date\": \"2025-11-18\", \"schedule_type\": \"available\", \"start_time\": \"08:00:00\", \"end_time\": \"18:00:00\"}', '2025-11-11 16:26:53', 0, '2025-11-11 17:26:53'),
(13, '{\"type\": \"schedule_change\", \"action\": \"create\", \"schedule_id\": 15, \"user_id\": 6, \"schedule_date\": \"2025-11-19\", \"schedule_type\": \"available\", \"start_time\": \"08:00:00\", \"end_time\": \"18:00:00\"}', '2025-11-11 16:28:48', 0, '2025-11-11 17:28:48'),
(14, '{\"type\": \"schedule_change\", \"action\": \"create\", \"schedule_id\": 16, \"user_id\": 6, \"schedule_date\": \"2025-11-20\", \"schedule_type\": \"available\", \"start_time\": \"08:00:00\", \"end_time\": \"18:00:00\"}', '2025-11-11 16:37:55', 0, '2025-11-11 17:37:55'),
(15, '{\"type\": \"schedule_change\", \"action\": \"create\", \"schedule_id\": 17, \"user_id\": 6, \"schedule_date\": \"2025-11-06\", \"schedule_type\": \"unavailable\", \"start_time\": null, \"end_time\": null}', '2025-11-11 16:38:52', 0, '2025-11-11 17:38:52'),
(16, '{\"type\": \"schedule_change\", \"action\": \"create\", \"schedule_id\": 18, \"user_id\": 6, \"schedule_date\": \"2025-11-06\", \"schedule_type\": \"unavailable\", \"start_time\": null, \"end_time\": null}', '2025-11-11 16:39:17', 0, '2025-11-11 17:39:17'),
(17, '{\"type\": \"schedule_change\", \"action\": \"create\", \"schedule_id\": 19, \"user_id\": 6, \"schedule_date\": \"2025-11-26\", \"schedule_type\": \"available\", \"start_time\": \"08:00:00\", \"end_time\": \"18:00:00\"}', '2025-11-11 16:39:24', 0, '2025-11-11 17:39:24'),
(18, '{\"type\": \"appointment_change\", \"action\": \"create\", \"appointment_id\": 25, \"patient_id\": 3, \"appointment_date\": \"2025-11-19\", \"appointment_time\": \"08:00:00\", \"status\": \"scheduled\"}', '2025-11-11 22:01:13', 0, '2025-11-11 23:01:13'),
(19, '{\"type\": \"schedule_change\", \"action\": \"create\", \"schedule_id\": 20, \"user_id\": 5, \"schedule_date\": \"2025-11-20\", \"schedule_type\": \"available\", \"start_time\": \"08:00:00\", \"end_time\": \"18:00:00\"}', '2025-11-11 23:47:37', 0, '2025-11-12 00:47:37'),
(20, '{\"type\": \"schedule_change\", \"action\": \"create\", \"schedule_id\": 21, \"user_id\": 5, \"schedule_date\": \"2025-11-12\", \"schedule_type\": \"available\", \"start_time\": \"08:00:00\", \"end_time\": \"18:00:00\"}', '2025-11-12 00:06:05', 0, '2025-11-12 01:06:05'),
(21, '{\"type\": \"schedule_change\", \"action\": \"create\", \"schedule_id\": 22, \"user_id\": 5, \"schedule_date\": \"2025-11-11\", \"schedule_type\": \"available\", \"start_time\": \"08:00:00\", \"end_time\": \"18:00:00\"}', '2025-11-12 00:09:48', 0, '2025-11-12 01:09:48'),
(22, '{\"type\": \"schedule_change\", \"action\": \"update\", \"schedule_id\": 22, \"user_id\": 5, \"schedule_date\": \"2025-11-11\", \"old_is_available\": 1, \"new_is_available\": 0, \"old_schedule_type\": \"available\", \"new_schedule_type\": \"unavailable\"}', '2025-11-12 00:24:05', 0, '2025-11-12 01:24:05');

-- --------------------------------------------------------

--
-- Table structure for table `stock_entries`
--

CREATE TABLE `stock_entries` (
  `id` int(11) NOT NULL,
  `dr_number` varchar(100) NOT NULL,
  `delivery_date` date NOT NULL,
  `supplier` varchar(255) NOT NULL,
  `inventory_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `received_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `fname` varchar(100) NOT NULL,
  `mname` varchar(100) DEFAULT NULL,
  `lname` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password` varchar(255) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `role` enum('admin','doctor','dentist','nurse','staff','employee','patient') DEFAULT 'patient',
  `date_of_birth` date DEFAULT NULL,
  `gender` enum('Male','Female','Other') DEFAULT NULL,
  `address` text DEFAULT NULL,
  `blood_type` varchar(10) DEFAULT NULL,
  `photo` varchar(255) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `fname`, `mname`, `lname`, `email`, `password`, `phone`, `created_at`, `role`, `date_of_birth`, `gender`, `address`, `blood_type`, `photo`, `updated_at`) VALUES
(1, 'glysel', NULL, 'sales', 'glyselannesales@gmail.com', '$2y$10$jICGq8/WuVOJ7egez9Zt1e63hj2OOqZnTS2HvRJNWuk5NcNDbW5xy', '', '2025-10-29 17:40:49', 'patient', NULL, NULL, NULL, NULL, NULL, '2025-11-05 16:59:28'),
(3, 'Glysel', 'Tundag', 'Sales', 'salesglysel@gmail.com', '$2y$10$MKudDSs5lTzGFWYYAyGla.D9FXvIOSotDHHxXKgO3fNm/YtnqDBzi', '09923177049', '2025-11-04 05:04:39', 'patient', NULL, NULL, NULL, NULL, NULL, '2025-11-07 02:08:23'),
(4, 'Super', NULL, 'Admin', 'superadmin@g.batstate-u.edu.ph', '$2y$10$PL0lpAjnpUoYL6vW0TpHMebtfIYJHID3KBygHffokkERWPm/sicUa', '09123456789', '2025-11-04 14:59:35', 'admin', NULL, NULL, NULL, NULL, NULL, '2025-11-05 16:59:28'),
(5, 'Marcus Neo', 'Cornejo', 'Rangel', 'employee@g.batstate-u.edu.ph', '$2y$10$4aKG0ba1t/KwrNHAUV5cBeAAIOfl.PlbBmlfnJ59tdUYe/hzC27TG', '09453734193', '2025-11-05 11:27:25', 'doctor', '2003-12-02', 'Male', 'San Jose, Batangas', '', NULL, '2025-11-07 02:15:43'),
(6, 'Joana', '', 'Briones', 'joanabriones@g.batstate-u.edu.ph', '$2y$10$WguPETAl.C8upDmFNz/9iOkvlHrOkWLAdJxNzBzu.CGk.GbGC9Tju', '09123456789', '2025-11-11 04:27:58', 'dentist', '2003-08-04', 'Female', 'Pinagtungulan, San Jose', NULL, NULL, '2025-11-11 04:33:38');

-- --------------------------------------------------------

--
-- Table structure for table `user_profiles`
--

CREATE TABLE `user_profiles` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `dob` date DEFAULT NULL,
  `blood_type` varchar(10) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `course` varchar(100) DEFAULT NULL,
  `year_level` varchar(20) DEFAULT NULL,
  `guardian_name` varchar(255) DEFAULT NULL,
  `guardian_relationship` varchar(100) DEFAULT NULL,
  `guardian_contact` varchar(20) DEFAULT NULL,
  `profile_photo` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_profiles`
--

INSERT INTO `user_profiles` (`id`, `user_id`, `dob`, `blood_type`, `address`, `course`, `year_level`, `guardian_name`, `guardian_relationship`, `guardian_contact`, `profile_photo`, `created_at`, `updated_at`) VALUES
(1, 3, '2005-04-04', 'A+', 'San Jose, Batangas', 'BS Information Technology', '3rd Year', 'Russell Sales', 'Father', '09923177049', '../uploads/profiles/profile_3_1762480393.png', '2025-11-07 01:51:10', '2025-11-07 02:05:58');

-- --------------------------------------------------------

--
-- Table structure for table `visit_logs`
--

CREATE TABLE `visit_logs` (
  `id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `medical_record_id` int(11) DEFAULT NULL,
  `purpose` varchar(255) NOT NULL,
  `physician_name` varchar(255) NOT NULL,
  `visit_date` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `visit_logs`
--

INSERT INTO `visit_logs` (`id`, `patient_id`, `medical_record_id`, `purpose`, `physician_name`, `visit_date`, `created_at`) VALUES
(1, 1, NULL, 'Medical Check-up', 'Dr. Grey', '2025-09-10', '2025-11-04 23:55:14'),
(2, 2, NULL, 'Dental Cleaning', 'Dr. Avery', '2025-09-08', '2025-11-04 23:55:14'),
(3, 3, NULL, 'Follow-up', 'Dr. Sheperd', '2025-09-07', '2025-11-04 23:55:14');

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_appointment_availability`
-- (See below for the actual view)
--
CREATE TABLE `v_appointment_availability` (
`schedule_id` int(11)
,`doctor_id` int(11)
,`fname` varchar(100)
,`lname` varchar(100)
,`role` enum('admin','doctor','dentist','nurse','staff','employee','patient')
,`schedule_date` date
,`start_time` time
,`end_time` time
,`schedule_type` enum('available','unavailable')
,`is_available` tinyint(1)
,`time_slot` varchar(13)
,`booked_count` bigint(21)
,`is_slot_available` int(1)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_employee_schedules`
-- (See below for the actual view)
--
CREATE TABLE `v_employee_schedules` (
`user_id` int(11)
,`fname` varchar(100)
,`mname` varchar(100)
,`lname` varchar(100)
,`full_name` varchar(302)
,`email` varchar(150)
,`role` enum('admin','doctor','dentist','nurse','staff','employee','patient')
,`photo` varchar(255)
,`schedule_id` int(11)
,`schedule_date` date
,`start_time` time
,`end_time` time
,`is_available` tinyint(1)
,`notes` text
,`start_time_formatted` varchar(8)
,`end_time_formatted` varchar(8)
,`duration_hours` bigint(21)
,`attendance_time_in` time
,`attendance_time_out` time
,`attendance_status` enum('present','absent','late','half_day')
,`current_status` varchar(14)
);

-- --------------------------------------------------------

--
-- Structure for view `v_appointment_availability`
--
DROP TABLE IF EXISTS `v_appointment_availability`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_appointment_availability`  AS SELECT `ds`.`id` AS `schedule_id`, `ds`.`user_id` AS `doctor_id`, `u`.`fname` AS `fname`, `u`.`lname` AS `lname`, `u`.`role` AS `role`, `ds`.`schedule_date` AS `schedule_date`, `ds`.`start_time` AS `start_time`, `ds`.`end_time` AS `end_time`, `ds`.`schedule_type` AS `schedule_type`, `ds`.`is_available` AS `is_available`, time_format(addtime(`ds`.`start_time`,sec_to_time(`slot_num`.`n` * 30 * 60)),'%H:%i:00') AS `time_slot`, (select count(0) from `appointments` `a` where cast(`a`.`appointment_date` as date) = `ds`.`schedule_date` and `a`.`appointment_time` = time_format(addtime(`ds`.`start_time`,sec_to_time(`slot_num`.`n` * 30 * 60)),'%H:%i:00') and `a`.`status` in ('scheduled','confirmed')) AS `booked_count`, CASE WHEN `ds`.`schedule_type` = 'unavailable' THEN 0 WHEN (select count(0) from `appointments` `a` where cast(`a`.`appointment_date` as date) = `ds`.`schedule_date` AND `a`.`appointment_time` = time_format(addtime(`ds`.`start_time`,sec_to_time(`slot_num`.`n` * 30 * 60)),'%H:%i:00') AND `a`.`status` in ('scheduled','confirmed')) >= 1 THEN 0 ELSE 1 END AS `is_slot_available` FROM ((`doctor_schedules` `ds` join `users` `u` on(`ds`.`user_id` = `u`.`id`)) join (select 0 AS `n` union all select 1 AS `1` union all select 2 AS `2` union all select 3 AS `3` union all select 4 AS `4` union all select 5 AS `5` union all select 6 AS `6` union all select 7 AS `7` union all select 8 AS `8` union all select 9 AS `9` union all select 10 AS `10` union all select 11 AS `11` union all select 12 AS `12` union all select 13 AS `13` union all select 14 AS `14` union all select 15 AS `15` union all select 16 AS `16` union all select 17 AS `17` union all select 18 AS `18` union all select 19 AS `19` union all select 20 AS `20` union all select 21 AS `21` union all select 22 AS `22` union all select 23 AS `23`) `slot_num`) WHERE `ds`.`is_available` = 1 AND addtime(`ds`.`start_time`,sec_to_time(`slot_num`.`n` * 30 * 60)) < `ds`.`end_time` ORDER BY `ds`.`schedule_date` ASC, time_format(addtime(`ds`.`start_time`,sec_to_time(`slot_num`.`n` * 30 * 60)),'%H:%i:00') ASC ;

-- --------------------------------------------------------

--
-- Structure for view `v_employee_schedules`
--
DROP TABLE IF EXISTS `v_employee_schedules`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_employee_schedules`  AS SELECT `u`.`id` AS `user_id`, `u`.`fname` AS `fname`, `u`.`mname` AS `mname`, `u`.`lname` AS `lname`, concat(`u`.`fname`,' ',ifnull(`u`.`mname`,''),' ',`u`.`lname`) AS `full_name`, `u`.`email` AS `email`, `u`.`role` AS `role`, `u`.`photo` AS `photo`, `ds`.`id` AS `schedule_id`, `ds`.`schedule_date` AS `schedule_date`, `ds`.`start_time` AS `start_time`, `ds`.`end_time` AS `end_time`, `ds`.`is_available` AS `is_available`, `ds`.`notes` AS `notes`, date_format(`ds`.`start_time`,'%h:%i %p') AS `start_time_formatted`, date_format(`ds`.`end_time`,'%h:%i %p') AS `end_time_formatted`, timestampdiff(HOUR,`ds`.`start_time`,`ds`.`end_time`) AS `duration_hours`, `att`.`time_in` AS `attendance_time_in`, `att`.`time_out` AS `attendance_time_out`, `att`.`status` AS `attendance_status`, CASE WHEN `att`.`time_in` is not null AND `att`.`time_out` is null THEN 'in' WHEN `att`.`time_out` is not null THEN 'out' ELSE 'not_checked_in' END AS `current_status` FROM ((`users` `u` left join `doctor_schedules` `ds` on(`u`.`id` = `ds`.`user_id` and `ds`.`is_available` = 1)) left join `attendance` `att` on(`u`.`id` = `att`.`user_id` and `att`.`date` = `ds`.`schedule_date`)) WHERE `u`.`role` in ('doctor','dentist','nurse','staff','employee') ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `appointments`
--
ALTER TABLE `appointments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `employee_id` (`employee_id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_appointment_date` (`appointment_date`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_patient_id` (`patient_id`),
  ADD KEY `idx_calendar_event` (`calendar_event_id`),
  ADD KEY `fk_user_id` (`user_id`),
  ADD KEY `idx_appointment_datetime` (`appointment_date`,`appointment_time`),
  ADD KEY `idx_last_synced` (`last_synced`),
  ADD KEY `idx_appointment_lookup` (`appointment_date`,`appointment_time`,`status`),
  ADD KEY `idx_appointment_date_time` (`appointment_date`,`appointment_time`);

--
-- Indexes for table `appointment_history`
--
ALTER TABLE `appointment_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_appointment` (`appointment_id`),
  ADD KEY `fk_changed_by` (`changed_by`);

--
-- Indexes for table `appointment_notifications`
--
ALTER TABLE `appointment_notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_appointment_id` (`appointment_id`);

--
-- Indexes for table `attendance`
--
ALTER TABLE `attendance`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_date` (`user_id`,`date`),
  ADD KEY `idx_attendance_date` (`date`),
  ADD KEY `idx_attendance_user_date` (`user_id`,`date`);

--
-- Indexes for table `doctor_schedules`
--
ALTER TABLE `doctor_schedules`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_schedule` (`user_id`,`schedule_date`,`start_time`),
  ADD KEY `idx_schedules_date` (`schedule_date`),
  ADD KEY `idx_schedules_user_date` (`user_id`,`schedule_date`),
  ADD KEY `idx_schedule_type` (`schedule_type`),
  ADD KEY `idx_schedule_datetime` (`schedule_date`,`start_time`),
  ADD KEY `idx_schedule_lookup` (`user_id`,`schedule_date`,`is_available`),
  ADD KEY `idx_schedule_date_range` (`schedule_date`,`start_time`,`end_time`);

--
-- Indexes for table `employees`
--
ALTER TABLE `employees`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_employees_status` (`status`);

--
-- Indexes for table `employee_attendance`
--
ALTER TABLE `employee_attendance`
  ADD PRIMARY KEY (`id`),
  ADD KEY `employee_id` (`employee_id`),
  ADD KEY `date` (`date`);

--
-- Indexes for table `employee_schedules`
--
ALTER TABLE `employee_schedules`
  ADD PRIMARY KEY (`id`),
  ADD KEY `employee_id` (`employee_id`);

--
-- Indexes for table `inventory`
--
ALTER TABLE `inventory`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `batchId` (`batchId`),
  ADD KEY `code` (`code`),
  ADD KEY `status` (`status`),
  ADD KEY `expiry` (`expiry`);

--
-- Indexes for table `medical_records`
--
ALTER TABLE `medical_records`
  ADD PRIMARY KEY (`id`),
  ADD KEY `patient_id` (`patient_id`),
  ADD KEY `physician_id` (`employee_id`),
  ADD KEY `idx_visit_date` (`visit_date`);

--
-- Indexes for table `medicine_dispensed`
--
ALTER TABLE `medicine_dispensed`
  ADD PRIMARY KEY (`id`),
  ADD KEY `patient_id` (`patient_id`),
  ADD KEY `inventory_id` (`inventory_id`),
  ADD KEY `dispensed_by` (`dispensed_by`),
  ADD KEY `dispensed_date` (`dispensed_date`);

--
-- Indexes for table `otp_verifications`
--
ALTER TABLE `otp_verifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_phone_otp` (`phone`,`otp_code`);

--
-- Indexes for table `patients`
--
ALTER TABLE `patients`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `sr_code` (`sr_code`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_full_name` (`full_name`),
  ADD KEY `idx_position` (`position`);

--
-- Indexes for table `prescriptions`
--
ALTER TABLE `prescriptions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `medical_record_id` (`medical_record_id`),
  ADD KEY `inventory_id` (`inventory_id`);

--
-- Indexes for table `schedule_change_log`
--
ALTER TABLE `schedule_change_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_schedule_id` (`schedule_id`),
  ADD KEY `idx_changed_at` (`changed_at`);

--
-- Indexes for table `schedule_notifications`
--
ALTER TABLE `schedule_notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_is_read` (`is_read`),
  ADD KEY `idx_expires_at` (`expires_at`);

--
-- Indexes for table `stock_entries`
--
ALTER TABLE `stock_entries`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `dr_number` (`dr_number`),
  ADD KEY `inventory_id` (`inventory_id`),
  ADD KEY `received_by` (`received_by`),
  ADD KEY `delivery_date` (`delivery_date`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_users_role` (`role`);

--
-- Indexes for table `user_profiles`
--
ALTER TABLE `user_profiles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`);

--
-- Indexes for table `visit_logs`
--
ALTER TABLE `visit_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `patient_id` (`patient_id`),
  ADD KEY `medical_record_id` (`medical_record_id`),
  ADD KEY `idx_visit_date` (`visit_date`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `appointments`
--
ALTER TABLE `appointments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `appointment_history`
--
ALTER TABLE `appointment_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `appointment_notifications`
--
ALTER TABLE `appointment_notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `attendance`
--
ALTER TABLE `attendance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `doctor_schedules`
--
ALTER TABLE `doctor_schedules`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `employees`
--
ALTER TABLE `employees`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `employee_attendance`
--
ALTER TABLE `employee_attendance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `employee_schedules`
--
ALTER TABLE `employee_schedules`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `inventory`
--
ALTER TABLE `inventory`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `medical_records`
--
ALTER TABLE `medical_records`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `medicine_dispensed`
--
ALTER TABLE `medicine_dispensed`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `otp_verifications`
--
ALTER TABLE `otp_verifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `patients`
--
ALTER TABLE `patients`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `prescriptions`
--
ALTER TABLE `prescriptions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `schedule_change_log`
--
ALTER TABLE `schedule_change_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `schedule_notifications`
--
ALTER TABLE `schedule_notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `stock_entries`
--
ALTER TABLE `stock_entries`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `user_profiles`
--
ALTER TABLE `user_profiles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `visit_logs`
--
ALTER TABLE `visit_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `appointments`
--
ALTER TABLE `appointments`
  ADD CONSTRAINT `appointments_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `appointments_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_patient` FOREIGN KEY (`patient_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `appointment_history`
--
ALTER TABLE `appointment_history`
  ADD CONSTRAINT `appointment_history_ibfk_1` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `appointment_history_ibfk_2` FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_appointment` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`),
  ADD CONSTRAINT `fk_changed_by` FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `appointment_notifications`
--
ALTER TABLE `appointment_notifications`
  ADD CONSTRAINT `appointment_notifications_ibfk_1` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `attendance`
--
ALTER TABLE `attendance`
  ADD CONSTRAINT `attendance_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `doctor_schedules`
--
ALTER TABLE `doctor_schedules`
  ADD CONSTRAINT `doctor_schedules_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `employees`
--
ALTER TABLE `employees`
  ADD CONSTRAINT `fk_employee_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `employee_attendance`
--
ALTER TABLE `employee_attendance`
  ADD CONSTRAINT `employee_attendance_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `employee_schedules`
--
ALTER TABLE `employee_schedules`
  ADD CONSTRAINT `employee_schedules_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `medical_records`
--
ALTER TABLE `medical_records`
  ADD CONSTRAINT `medical_records_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `medicine_dispensed`
--
ALTER TABLE `medicine_dispensed`
  ADD CONSTRAINT `medicine_dispensed_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `medicine_dispensed_ibfk_2` FOREIGN KEY (`inventory_id`) REFERENCES `inventory` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `medicine_dispensed_ibfk_3` FOREIGN KEY (`dispensed_by`) REFERENCES `employees` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `otp_verifications`
--
ALTER TABLE `otp_verifications`
  ADD CONSTRAINT `otp_verifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `patients`
--
ALTER TABLE `patients`
  ADD CONSTRAINT `patients_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `prescriptions`
--
ALTER TABLE `prescriptions`
  ADD CONSTRAINT `prescriptions_ibfk_1` FOREIGN KEY (`medical_record_id`) REFERENCES `medical_records` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `prescriptions_ibfk_2` FOREIGN KEY (`inventory_id`) REFERENCES `inventory` (`id`);

--
-- Constraints for table `schedule_change_log`
--
ALTER TABLE `schedule_change_log`
  ADD CONSTRAINT `schedule_change_log_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `schedule_change_log_ibfk_2` FOREIGN KEY (`schedule_id`) REFERENCES `doctor_schedules` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `stock_entries`
--
ALTER TABLE `stock_entries`
  ADD CONSTRAINT `stock_entries_ibfk_1` FOREIGN KEY (`inventory_id`) REFERENCES `inventory` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `stock_entries_ibfk_2` FOREIGN KEY (`received_by`) REFERENCES `employees` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_profiles`
--
ALTER TABLE `user_profiles`
  ADD CONSTRAINT `user_profiles_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `visit_logs`
--
ALTER TABLE `visit_logs`
  ADD CONSTRAINT `visit_logs_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `visit_logs_ibfk_2` FOREIGN KEY (`medical_record_id`) REFERENCES `medical_records` (`id`) ON DELETE SET NULL;

DELIMITER $$
--
-- Events
--
CREATE DEFINER=`root`@`localhost` EVENT `cleanup_old_notifications` ON SCHEDULE EVERY 1 HOUR STARTS '2025-11-08 12:39:41' ON COMPLETION NOT PRESERVE ENABLE DO DELETE FROM schedule_notifications 
    WHERE expires_at < NOW() OR created_at < DATE_SUB(NOW(), INTERVAL 2 HOUR)$$

DELIMITER ;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

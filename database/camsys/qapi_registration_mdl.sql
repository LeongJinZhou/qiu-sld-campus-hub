-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 21, 2026 at 11:22 AM
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
-- Database: `camsys`
--

-- --------------------------------------------------------

--
-- Table structure for table `qapi_registration_mdl`
--

CREATE TABLE `qapi_registration_mdl` (
  `INSTITUTE` varchar(100) DEFAULT NULL,
  `STUDENT_NUMBER` varchar(100) DEFAULT NULL,
  `SALUTATION` varchar(100) DEFAULT NULL,
  `STUDENT_NAME` text DEFAULT NULL,
  `IC_PASSPORT` varchar(100) DEFAULT NULL,
  `GENDER` varchar(100) DEFAULT NULL,
  `DATE_OF_BIRTH` varchar(100) DEFAULT NULL,
  `PLACE_OF_BIRTH` varchar(100) DEFAULT NULL,
  `TELPHONE_NUMBER` varchar(100) DEFAULT NULL,
  `STUDENT_MOBILE_NO` varchar(100) DEFAULT NULL,
  `E_MAIL` text DEFAULT NULL,
  `ADDRESS` text DEFAULT NULL,
  `POSTCODE` varchar(100) DEFAULT NULL,
  `CITY_TOWN` varchar(200) DEFAULT NULL,
  `STATE` varchar(200) DEFAULT NULL,
  `COUNTRY` varchar(200) DEFAULT NULL,
  `MARITAL_STATUS` varchar(200) DEFAULT NULL,
  `LOCAL_INTERNATIONAL` varchar(200) DEFAULT NULL,
  `NATIONALITY` varchar(200) DEFAULT NULL,
  `RELIGION` varchar(200) DEFAULT NULL,
  `RACE` varchar(200) DEFAULT NULL,
  `PROFILE_STATUS` varchar(200) DEFAULT NULL,
  `PROGRAMME_STATUS` varchar(200) DEFAULT NULL,
  `PROGRAMME` varchar(200) DEFAULT NULL,
  `PROGRAMME_CODE` varchar(200) DEFAULT NULL,
  `TYPE_OF_PROGRAMME` varchar(200) DEFAULT NULL,
  `HIGHEST_QUALIFICATION` text DEFAULT NULL,
  `SCORE` varchar(100) DEFAULT NULL,
  `YEAR_ENTRY` int(100) DEFAULT NULL,
  `INTAKE_NUMBER` int(100) DEFAULT NULL,
  `COURSE_DURATION` varchar(100) DEFAULT NULL,
  `DATE_REGISTERED` varchar(100) DEFAULT NULL,
  `PROGRAMME_LUD` varchar(100) DEFAULT NULL,
  `PROFILE_CREATION_DATE` varchar(100) DEFAULT NULL,
  `STUDENT_LUD` varchar(100) DEFAULT NULL,
  `GRADUATED_DATE` varchar(100) DEFAULT NULL,
  `BM` varchar(100) DEFAULT NULL,
  `BI` varchar(100) DEFAULT NULL,
  `ENGLISH_PROFICIENCY` varchar(200) DEFAULT NULL,
  `CGPA` varchar(100) DEFAULT NULL,
  `OVERALL_SCORE` varchar(100) DEFAULT NULL,
  `ID_CARD_NUMBER` varchar(100) DEFAULT NULL,
  `icpassport` varchar(100) GENERATED ALWAYS AS (substr(`IC_PASSPORT`,2)) STORED,
  `intakeNumber` int(100) GENERATED ALWAYS AS (concat(`YEAR_ENTRY`,lpad(`INTAKE_NUMBER`,2,0))) STORED,
  `is_active` tinyint(100) GENERATED ALWAYS AS (`PROGRAMME_STATUS` in ('Active','Deferred','Suspended')) STORED
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `qapi_registration_mdl`
--

INSERT INTO `qapi_registration_mdl` (`INSTITUTE`, `STUDENT_NUMBER`, `SALUTATION`, `STUDENT_NAME`, `IC_PASSPORT`, `GENDER`, `DATE_OF_BIRTH`, `PLACE_OF_BIRTH`, `TELPHONE_NUMBER`, `STUDENT_MOBILE_NO`, `E_MAIL`, `ADDRESS`, `POSTCODE`, `CITY_TOWN`, `STATE`, `COUNTRY`, `MARITAL_STATUS`, `LOCAL_INTERNATIONAL`, `NATIONALITY`, `RELIGION`, `RACE`, `PROFILE_STATUS`, `PROGRAMME_STATUS`, `PROGRAMME`, `PROGRAMME_CODE`, `TYPE_OF_PROGRAMME`, `HIGHEST_QUALIFICATION`, `SCORE`, `YEAR_ENTRY`, `INTAKE_NUMBER`, `COURSE_DURATION`, `DATE_REGISTERED`, `PROGRAMME_LUD`, `PROFILE_CREATION_DATE`, `STUDENT_LUD`, `GRADUATED_DATE`, `BM`, `BI`, `ENGLISH_PROFICIENCY`, `CGPA`, `OVERALL_SCORE`, `ID_CARD_NUMBER`) VALUES
('Main Campus', 'STD20240001', 'Mr', 'Ahmad Bin Razak', '010203-08-1234', 'Male', '2001-02-03', 'Ipoh', '05-2345678', '012-3456789', 'ahmad.razak@example.com', '12, Jalan Sultan Azlan Shah', '31400', 'Ipoh', 'Perak', 'Malaysia', 'Single', 'Local', 'Malaysian', 'Islam', 'Malay', 'Active', 'Enrolled', 'Bachelor of Computer Science', 'CS101', 'Degree', 'SPM 8As', '3.80', 2024, 1, '3 Years', '2024-01-15', '2024-01-15', '2024-01-15', '2024-01-15', '2027-12-31', 'A', 'A-', 'Band 4', '3.75', '3.75', '010203-08-1234'),
('Branch Campus', 'STD20240002', 'Ms', 'Tan Mei Ling', '020510-10-5678', 'Female', '2002-05-10', 'Kuala Lumpur', '03-9876543', '016-9876543', 'meiling.tan@example.com', '45, Lorong Damai 3', '50450', 'Kuala Lumpur', 'WPKL', 'Malaysia', 'Single', 'Local', 'Malaysian', 'Buddhism', 'Chinese', 'Active', 'Enrolled', 'Diploma in Business Administration', 'BA202', 'Diploma', 'SPM 6As', '3.50', 2024, 1, '2 Years', '2024-01-16', '2024-01-16', '2024-01-16', '2024-01-16', '2026-12-31', 'B+', 'A', 'Band 4.5', '3.60', '3.60', '020510-10-5678'),
('Main Campus', 'STD20240003', 'Mr', 'Suresh A/L Subramaniam', '011120-02-9012', 'Male', '2001-11-20', 'Alor Setar', '04-1234567', '017-1234567', 'suresh.s@example.com', '8, Taman Perdana', '05150', 'Alor Setar', 'Kedah', 'Malaysia', 'Single', 'Local', 'Malaysian', 'Hinduism', 'Indian', 'Active', 'Enrolled', 'Bachelor of Mechanical Engineering', 'ENG301', 'Degree', 'STPM 3.50', '3.42', 2023, 2, '4 Years', '2023-09-01', '2023-09-01', '2023-09-01', '2023-09-01', '2027-08-31', 'A-', 'B', 'Band 4', '3.42', '3.42', '011120-02-9012'),
('Main Campus', 'STD20240004', 'Ms', 'Nurul Aisyah Binti Hassan', '030415-12-5432', 'Female', '2003-04-15', 'Kota Kinabalu', '088-765432', '013-8889900', 'nurul.aisyah@example.com', '88, Taman Kingfisher', '88450', 'Kota Kinabalu', 'Sabah', 'Malaysia', 'Single', 'Local', 'Malaysian', 'Islam', 'Bumiputera Sabah', 'Active', 'Enrolled', 'Diploma in Nursing', 'NUR102', 'Diploma', 'SPM 5As', '3.20', 2024, 2, '3 Years', '2024-05-10', '2024-05-10', '2024-05-10', '2024-05-10', '2027-05-01', 'A', 'B+', 'Band 3.5', '3.35', '3.35', '030415-12-5432'),
('City Campus', 'STD20240005', 'Mr', 'Li Wei Zhang', 'E12345678', 'Male', '1999-08-22', 'Beijing', '03-2111222', '011-2345678', 'liwei.zhang@example.com', 'Suite 12-3, Platinum Suites', '50250', 'Kuala Lumpur', 'WPKL', 'China', 'Single', 'International', 'Chinese', 'Others', 'Chinese', 'Active', 'Enrolled', 'Master of Information Technology', 'MIT501', 'Master', 'Bachelor Degree', '3.65', 2024, 1, '1.5 Years', '2024-02-01', '2024-02-01', '2024-02-01', '2024-02-01', '2025-08-31', 'N/A', 'N/A', 'IELTS 6.5', '3.80', '3.80', 'E12345678'),
('Main Campus', 'STD20230006', 'Mr', 'David Anak Francis', '000912-13-6789', 'Male', '2000-09-12', 'Kuching', '082-334455', '019-8765432', 'david.francis@example.com', '23, Lorong Tabuan Jaya', '93350', 'Kuching', 'Sarawak', 'Malaysia', 'Single', 'Local', 'Malaysian', 'Christianity', 'Iban', 'Active', 'Enrolled', 'Bachelor of Accounting (Hons)', 'ACC301', 'Degree', 'STPM 3.25', '3.10', 2023, 1, '4 Years', '2023-02-15', '2023-02-15', '2023-02-15', '2023-02-15', '2027-01-31', 'B+', 'A', 'Band 4', '3.28', '3.28', '000912-13-6789'),
('City Campus', 'STD20240007', 'Ms', 'Siti Nurhaliza Binti Wijaya', 'A98765432', 'Female', '2002-12-05', 'Jakarta', '03-4040505', '014-5556677', 'siti.wijaya@example.com', 'B-08-02, Residency Condominium', '53300', 'Setapak', 'WPKL', 'Indonesia', 'Single', 'International', 'Indonesian', 'Islam', 'Malay', 'Active', 'Enrolled', 'Bachelor of Mass Communication', 'BMC101', 'Degree', 'SMA High School Diploma', '85.0', 2024, 1, '3 Years', '2024-01-20', '2024-01-20', '2024-01-20', '2024-01-20', '2027-01-15', 'N/A', 'N/A', 'TOEFL 550', '3.55', '3.55', 'A98765432'),
('Main Campus', 'STD20220008', 'Mr', 'Chong Wei Keat', '990318-07-3321', 'Male', '1999-03-18', 'Georgetown', '04-2266778', '012-4455667', 'weikeat.chong@example.com', '102, Jalan Burmah', '10050', 'Georgetown', 'Pulau Pinang', 'Malaysia', 'Single', 'Local', 'Malaysian', 'Buddhism', 'Chinese', 'Inactive', 'Graduated', 'Diploma in Graphic Design', 'DGD101', 'Diploma', 'SPM 4As', '3.40', 2022, 1, '2 Years', '2022-01-10', '2022-01-10', '2022-01-10', '2022-01-10', '2024-01-10', 'A', 'A-', 'Band 4', '3.82', '3.82', '990318-07-3321'),
('Branch Campus', 'STD20240009', 'Mr', 'Muhammad Amirul Bin Zulkifli', '020707-01-4433', 'Male', '2002-07-07', 'Johor Bahru', '07-3311223', '018-9988776', 'amirul.z@example.com', '55, Jalan Molek 1/28', '81100', 'Johor Bahru', 'Johor', 'Malaysia', 'Single', 'Local', 'Malaysian', 'Islam', 'Malay', 'Active', 'Enrolled', 'Bachelor of Electrical Engineering', 'EEE201', 'Degree', 'Matriculation 3.60', '3.60', 2024, 1, '4 Years', '2024-02-15', '2024-02-15', '2024-02-15', '2024-02-15', '2028-01-31', 'A', 'B+', 'Band 4', '3.12', '3.12', '020707-01-4433'),
('City Campus', 'STD20240010', 'Ms', 'Kavitha A/P Gopal', '010505-08-7788', 'Female', '2001-05-05', 'Teluk Intan', '05-6211234', '011-1098765', 'kavitha.g@example.com', '14, Jalan Pasir Puteh', '31650', 'Ipoh', 'Perak', 'Malaysia', 'Single', 'Local', 'Malaysian', 'Hinduism', 'Indian', 'Active', 'Enrolled', 'Bachelor of Hospitality Management', 'BHM101', 'Degree', 'STPM 2.90', '2.90', 2024, 2, '3 Years', '2024-06-01', '2024-06-01', '2024-06-01', '2024-06-01', '2027-05-31', 'B', 'A-', 'Band 3.5', '3.05', '3.05', '010505-08-7788');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `qapi_registration_mdl`
--
ALTER TABLE `qapi_registration_mdl`
  ADD KEY `index_1` (`IC_PASSPORT`,`PROGRAMME_CODE`,`intakeNumber`),
  ADD KEY `STUDENT_NUMBER` (`STUDENT_NUMBER`),
  ADD KEY `icpassport` (`icpassport`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Oct 05, 2026 at 09:21 AM
-- Server version: 10.11.19-MariaDB-cll-lve
-- PHP Version: 8.4.25

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `edrsccup_plan`
--

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `employee_id` varchar(255) DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `department_id` bigint(20) UNSIGNED DEFAULT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `must_change_password` tinyint(1) NOT NULL DEFAULT 0,
  `remember_token` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `role` enum('system_admin','department_lead','employee','auditor') NOT NULL DEFAULT 'employee',
  `is_active` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `employee_id`, `name`, `email`, `department_id`, `email_verified_at`, `password`, `must_change_password`, `remember_token`, `created_at`, `updated_at`, `role`, `is_active`) VALUES
(1, NULL, 'System Administrator', 'admin@iprpms.gov.et', 10, NULL, '$2y$12$U01Ho2j4TJaY1BCUbq2oPuwCNPLLQ.RiOiyAYFgbf17CFRmWb9ycK', 0, NULL, '2026-01-13 13:40:09', '2026-03-20 07:03:52', 'system_admin', 1),
(3, NULL, 'HR Manager', 'hr@iprpms.gov.et', 24, NULL, '$2y$12$mdBsgt/1xqIvIGXxJoqRteaAg5b8CXVXF7pvpVHig64rML0JP9U8C', 0, NULL, '2026-01-13 13:50:23', '2026-03-20 07:03:52', 'employee', 1),
(4, NULL, 'Department Head', 'depthead@iprpms.gov.et', 25, NULL, '$2y$12$IBTOnoIY0BOOf1y.Wd4eW.oTP121EoXr9OIcr/Z9pfMJmb9IfUpQq', 0, NULL, '2026-01-13 13:50:23', '2026-03-20 07:03:52', 'employee', 1),
(5, NULL, 'Supervisor', 'supervisor@iprpms.gov.et', 25, NULL, '$2y$12$4uU5gQtWvRONCAwSHDIOBuk.fxxTOEN41joFufzsBET3cRSEyfeke', 0, NULL, '2026-01-13 13:50:24', '2026-03-20 07:03:52', 'employee', 1),
(6, NULL, 'Employee', 'employee@iprpms.gov.et', 25, NULL, '$2y$12$hBkTBfshAjuL6vrIkM2JFeg4U5jFoFHhjzKDfa8XWxyH5OpsuSs6O', 0, NULL, '2026-01-13 13:50:24', '2026-03-20 07:03:53', 'employee', 1),
(7, NULL, 'Abdulkerim Seid', 'ak47seid@gmail.com', NULL, NULL, '$2y$12$MkxbbWNFwukAz8XNAtzKsOIhngoVHKx0bIIyWB5W28EaEp1gfWubq', 0, NULL, '2026-01-13 14:01:54', '2026-01-13 15:05:54', 'employee', 1),
(8, NULL, 'System Auditor', 'auditor@iprpms.gov.et', 22, NULL, '$2y$12$P./057LO9V4peBwGyrvDBOcohNo4aEMQmD/9r7QdHHi6Rqy80p2Qm', 0, NULL, '2026-01-13 17:18:23', '2026-03-20 07:03:53', 'employee', 1),
(9, '1', 'Daniel Russom', 'sample@gmail.com', NULL, NULL, '$2y$12$nRJ7YW8EeH9AosAbu68Irej8cQh3LK/yH.i4wFjRcwZJU4PsxYz4e', 0, NULL, '2026-02-23 09:21:39', '2026-02-23 09:21:39', 'employee', 1),
(10, '2', 'Abdulkerim Seid', 'ak77seid@gmail.com', NULL, NULL, '$2y$12$MACtL67tAiPYM3QgWnBXHeh8TSxYRtPpqFnPXzJ46f1JDmn5QSz.W', 0, NULL, '2026-03-15 07:03:33', '2026-03-15 07:03:33', 'employee', 1),
(11, NULL, 'Planning Department Expert', 'planning.expert@iprpms.gov.et', 25, NULL, '$2y$12$pgU3i4Oye3184IsxnU7C4OH1hEzB7JdpBeQfUREuZ81mDsXZuW47S', 0, NULL, '2026-03-20 07:03:38', '2026-03-20 11:23:29', 'employee', 1),
(12, NULL, 'Planning Directorate Reviewer', 'planning.directorate@iprpms.gov.et', 25, NULL, '$2y$12$Vbr6455FgT1U57EToSxTUeQYvBn1NwguqQEvBtjE8tZr18i8.3/EO', 0, NULL, '2026-03-20 07:03:39', '2026-03-20 11:23:29', 'employee', 1),
(13, NULL, 'Planning Department Chief', 'planning.chief@iprpms.gov.et', 25, NULL, '$2y$12$SYT4rBKSmFv17DhtivmsluzAWg1VdpFzcYOgEvmKL68FvJcs8WS8C', 0, NULL, '2026-03-20 07:03:39', '2026-03-20 11:23:29', 'employee', 1),
(14, NULL, 'Planning Expert Placeholder', 'placeholder.expert@iprpms.gov.et', 25, NULL, '$2y$12$YwsSnIPj34APCh5U.DDIKupXQC4QWZcHAXTzFtHeNQplKO7acR17C', 0, NULL, '2026-03-20 07:03:39', '2026-03-25 04:21:52', 'employee', 1),
(15, NULL, 'Planning Directorate Placeholder', 'placeholder.directorate@iprpms.gov.et', 25, NULL, '$2y$12$Tth31qR8LQ4IQTd5N3DqQegk4/A/EPwESbFysSsFyanNLPCZ.XsqO', 0, NULL, '2026-03-20 07:03:40', '2026-03-25 04:21:36', 'employee', 1),
(16, NULL, 'Planning Chief Placeholder', 'placeholder.chief@iprpms.gov.et', 25, NULL, '$2y$12$wZzH4bxYz5/u3vAzj3QB2eqSfoRm1iK5.cX1NrHpXEA4mnw9I.Asu', 0, NULL, '2026-03-20 07:03:40', '2026-03-25 04:23:58', 'employee', 1),
(17, NULL, 'Head Office Viewer', 'headoffice@iprpms.gov.et', 10, NULL, '$2y$12$N1rv1QiFPH/a9c.U9fRHf.dnnWFcCXc25BSKodvKvwI8b7RHvDuLC', 0, NULL, '2026-03-20 08:14:57', '2026-09-02 04:04:19', 'employee', 1),
(18, NULL, 'Plan and Report Head Office', 'planreport.headoffice@iprpms.gov.et', 25, NULL, '$2y$12$4GwBdTOxvi.Mr.2l2zPUlOrX6GXrPV8ZzC.xc/QBz9IoeohPbKJkC', 0, NULL, '2026-03-20 08:14:58', '2026-03-20 11:23:31', 'employee', 1),
(19, NULL, 'Plan and Report Department Expert', 'planreport.department@iprpms.gov.et', 25, NULL, '$2y$12$tTfUmDA0HDcrMfmtCvPRUed62qorRlFJ3j2g/cEiL9WWZOEFaHp5K', 0, NULL, '2026-03-20 08:14:58', '2026-03-20 11:23:31', 'employee', 1),
(20, NULL, 'Mulu Hassen', 'Muluhassen12@gmail.com', 11, NULL, '$2y$12$yrTawOEHwGK3lNWxVwwz0exGIEUwEabgzBfpdZJouAYCfjLrwb.Uq', 0, NULL, '2026-03-25 04:21:23', '2026-03-25 04:21:23', 'employee', 1),
(21, NULL, 'Filimon', 'Filimon@gmail.com', 11, NULL, '$2y$12$7gPnrRndG5mBieWuwFk1P..cTWJszJ.lFYe0Y1exo78vYwfdtWFmi', 0, NULL, '2026-03-25 04:24:52', '2026-03-25 04:24:52', 'employee', 1),
(22, NULL, 'Gebere', 'Gebere@gmail.com', 11, NULL, '$2y$12$6O9uuLyLnhxaZK/S7JCXqO6686mVLKLxeD1.d2x8SkpCrcxSu.4ca', 0, NULL, '2026-03-25 04:27:14', '2026-03-25 04:27:14', 'employee', 1),
(23, NULL, 'Operation test', 'testoperationchief@gmail.com', 12, NULL, '$2y$12$6JHOeAdKaBr6v1DOKtk4DeI5midUoVesPz.CCEv9AgUXmypIPdXAC', 0, NULL, '2026-07-29 02:57:31', '2026-07-29 02:57:31', 'employee', 1),
(24, NULL, 'Operation director', 'operationdirector@gmail.com', 12, NULL, '$2y$12$MuMED5FbbxzPDTMCjnxpqOZuual/8O2ed91ZjjlxW.n4v0RFw5/E.', 0, NULL, '2026-07-29 02:59:01', '2026-07-29 02:59:01', 'employee', 1),
(25, NULL, 'operation employee', 'operationemployee@gmail.com', 2, NULL, '$2y$12$Nq6m2dzT3DS4unueqvPG0.Mx1xkzHFBApC.MsWCVZcQG972npM1Qm', 0, NULL, '2026-07-29 03:01:15', '2026-07-29 03:01:15', 'employee', 1),
(26, '4', 'Mulu Hassen', 'mulu@gmail.com', 12, NULL, '$2y$12$7m1CYeWA1xhA/1JIGQfRxuDzR9PRJrN4Yo0hpY3b/lEy3epIP5WlS', 0, NULL, '2026-07-29 03:28:13', '2026-07-29 03:29:03', 'employee', 1),
(27, '5', 'employee test', 'employeeoperation@gmail.com', 12, NULL, '$2y$12$Z1H6DDUy/rRFTIulY.ihYOfbYg59otYhoKE.PHFcJsm4RsQZZUEYO', 0, NULL, '2026-07-29 10:27:12', '2026-07-29 10:28:25', 'employee', 1),
(28, NULL, 'Abdulkerim Seid', 'ak@iprpms.com', 32, NULL, '$2y$12$NwPR6PPPWwehD0lD7vGSL.OcfyVFOVfOHOM8Oq9gtydIUUG6e7W6.', 0, NULL, '2026-08-20 09:05:30', '2026-08-20 09:05:30', 'employee', 1),
(29, NULL, 'Daniel Russom', 'Dani@iprpms.com', 32, NULL, '$2y$12$t9xbNJH0ULd/QISNTH50Ju226R50SrRr.bpbonrkSnYVnVHzxPtEW', 0, NULL, '2026-08-20 09:06:51', '2026-08-20 09:06:51', 'employee', 1),
(30, NULL, 'Kedir Ketema', 'Kedir@iprpms.com', 32, NULL, '$2y$12$nCIaH6.xTFYiRX5X.IRviua8zzZBUORiFT6NEp1mNyMmp7SL5Puci', 0, NULL, '2026-08-20 09:07:44', '2026-08-20 09:07:44', 'employee', 1),
(31, NULL, 'Security expert', 'SecurityE@gmail.com', 18, NULL, '$2y$12$ce3KMIHLKM.B5ABe53j4MeMVKRDebdxBevd6w.ZArLyDckOjHKyqC', 0, NULL, '2026-08-21 09:34:56', '2026-08-21 09:34:56', 'employee', 1),
(32, NULL, 'Security Dir', 'SecurityD@gmail.com', 18, NULL, '$2y$12$rn0bfRpz3WPxZPBAUjRHk.Z1ymhTAZ2FAFBWtjrVsuP41iPDVJ9ga', 0, NULL, '2026-08-21 09:35:48', '2026-08-21 09:35:48', 'employee', 1),
(33, NULL, 'Security Chiff', 'SecurityC@gmail.com', 18, NULL, '$2y$12$BR4E33VYrRJt4Ot5nMuNJOImodMhD5.wESrkTyqXFBhYAtYUhiaYC', 0, NULL, '2026-08-21 09:36:34', '2026-08-21 09:36:34', 'employee', 1),
(34, NULL, 'PR directorate', 'PRdirectorate@gmail.cpm', 7, NULL, '$2y$12$offsEdf6Z/Xqs5.VFJf/zuy3gVJmC7oJaYB77hcxZUdXQj4D27xK2', 0, NULL, '2026-08-26 10:55:47', '2026-08-26 10:55:47', 'employee', 1),
(35, NULL, 'OD', 'OD@gmail.com', 12, NULL, '$2y$12$zAKV/vSmj4qlwQnpIamhnuRS8HVpv5WX0ATAtc6FaQ9E8fyBMHkHG', 0, NULL, '2026-08-28 09:58:58', '2026-08-28 09:58:58', 'employee', 1),
(36, NULL, 'Auditing Department Expert', 'aud.expert@iprpms.gov.et', 22, NULL, '$2y$12$M75ej80uvuqnX2yD45w7x.bJ/vA0k5M58s.NNYbGMKr339g1Tc5xe', 1, NULL, '2026-09-25 14:12:29', '2026-09-25 14:14:06', 'employee', 1),
(37, NULL, 'Auditing Department Directorate', 'aud.directorate@iprpms.gov.et', 22, NULL, '$2y$12$d1ybYMyGLh//B1GL3VFWouQaluKUTInsjHAjTkegcQZ0hXn/u5RRK', 1, NULL, '2026-09-25 14:12:30', '2026-09-25 14:14:07', 'employee', 1),
(38, NULL, 'Auditing Department Chief (Head)', 'aud.chief@iprpms.gov.et', 22, NULL, '$2y$12$/lbXbdIxjqsOmJqCYiPgx.610Diss9QcjY1Caz61Eg36oD0RweBAS', 1, NULL, '2026-09-25 14:12:30', '2026-09-25 14:14:07', 'employee', 1),
(42, NULL, 'Capacity Building Department Expert', 'cb.expert@iprpms.gov.et', 31, NULL, '$2y$12$VhA7qfNM1kVakBWXzzXnk.hAblk448sADiN9ah.u7LKItTcMg3hMi', 1, NULL, '2026-09-25 14:12:32', '2026-09-25 14:14:09', 'employee', 1),
(43, NULL, 'Capacity Building Department Directorate', 'cb.directorate@iprpms.gov.et', 31, NULL, '$2y$12$6tUlZ.66v8VYiHWv3oqStu5qdlcwDOSaCzmTabHNrFKdFQ2.V1Orm', 1, NULL, '2026-09-25 14:12:32', '2026-09-25 14:14:10', 'employee', 1),
(44, NULL, 'Capacity Building Department Chief (Head)', 'cb.chief@iprpms.gov.et', 31, NULL, '$2y$12$yZ5rfIWLXfYQCJLhoHH.z.zc99srVNcsIISJjbWvKBFyeG/JGCCxS', 1, NULL, '2026-09-25 14:12:32', '2026-09-25 14:14:10', 'employee', 1),
(45, NULL, 'Communication Department Expert', 'com.expert@iprpms.gov.et', 28, NULL, '$2y$12$t0gyEekMk6nEgO3dC4g6kO1fA.CkbkfEkynU1m4.lL3dLyavtGtwu', 1, NULL, '2026-09-25 14:12:33', '2026-09-25 14:14:11', 'employee', 1),
(46, NULL, 'Communication Department Directorate', 'com.directorate@iprpms.gov.et', 28, NULL, '$2y$12$/WzeX0Dq87M9SfhaVED0ze3NJh0wwSp90Ab/0wOZHc5FIKQ7tECmu', 1, NULL, '2026-09-25 14:12:33', '2026-09-25 14:14:11', 'employee', 1),
(47, NULL, 'Communication Department Chief (Head)', 'com.chief@iprpms.gov.et', 28, NULL, '$2y$12$i8pEsIB5dAfqzw5NlgyQ2ORzt7h6lMyx57sd30EZTRer3j.uCOcyS', 1, NULL, '2026-09-25 14:12:34', '2026-09-25 14:14:11', 'employee', 1),
(48, NULL, 'Digital Office Department Expert', 'do.expert@iprpms.gov.et', 10, NULL, '$2y$12$qpldYqD4GauK/UlbNIG7lOnvb6cGuDfucjaMw2wx2BWPYCJsZn72C', 1, NULL, '2026-09-25 14:12:34', '2026-09-25 14:14:12', 'employee', 1),
(49, NULL, 'Digital Office Department Directorate', 'do.directorate@iprpms.gov.et', 10, NULL, '$2y$12$1Fh6aFw8.nDC2e7cNjl2p.we39n8U/CyDKNsHLKSKQqZUQKbkD4Ru', 1, NULL, '2026-09-25 14:12:34', '2026-09-25 14:14:12', 'employee', 1),
(50, NULL, 'Digital Office Department Chief (Head)', 'do.chief@iprpms.gov.et', 10, NULL, '$2y$12$hoGq.IRPj/qndGfEOwwA6OqrVJZrptJr.Vx0iXsyp6TQaopOenoyq', 1, NULL, '2026-09-25 14:12:35', '2026-09-25 14:14:13', 'employee', 1),
(51, NULL, 'Engineering Department Expert', 'eng.expert@iprpms.gov.et', 17, NULL, '$2y$12$bo/AhZvvN1IQMPE5IFsqY.jBg8aCJWBkknwhGG1LxjFfcLszTAshq', 1, NULL, '2026-09-25 14:12:35', '2026-09-25 14:14:13', 'employee', 1),
(52, NULL, 'Engineering Department Directorate', 'eng.directorate@iprpms.gov.et', 17, NULL, '$2y$12$Y3wgXpbehFcLaCJ6eGe2Du6SXHRHMB6nDo6uoYncFIwrvRKSW25ca', 1, NULL, '2026-09-25 14:12:36', '2026-09-25 14:14:14', 'employee', 1),
(53, NULL, 'Engineering Department Chief (Head)', 'eng.chief@iprpms.gov.et', 17, NULL, '$2y$12$6xzjbntkfhM3MZUa3.lkG.GaGPmh62u7P0.j/8YwxfnMF/QOiWH9.', 1, NULL, '2026-09-25 14:12:36', '2026-09-25 14:14:14', 'employee', 1),
(54, NULL, 'Ethics Department Expert', 'eth.expert@iprpms.gov.et', 27, NULL, '$2y$12$h6RmP9Yfp8QP9MyLjTAxl.0YiAAsZullg/Ela73qBww5f3zrv3.gW', 1, NULL, '2026-09-25 14:12:36', '2026-09-25 14:14:15', 'employee', 1),
(55, NULL, 'Ethics Department Directorate', 'eth.directorate@iprpms.gov.et', 27, NULL, '$2y$12$nNICkawnjwcFjGknM2jBT.RAtTYfwlDbJf3TmnxRCFSmCRwi2rUAS', 1, NULL, '2026-09-25 14:12:37', '2026-09-25 14:14:15', 'employee', 1),
(56, NULL, 'Ethics Department Chief (Head)', 'eth.chief@iprpms.gov.et', 27, NULL, '$2y$12$/yp078/7Issnbtz07xHExupHyrGXIMnhuN4m5CZhXnx50HT/cwGoW', 1, NULL, '2026-09-25 14:12:37', '2026-09-25 14:14:16', 'employee', 1),
(57, NULL, 'Finance Department Expert', 'fin.expert@iprpms.gov.et', 20, NULL, '$2y$12$ur07vG4SD7QVKldypa.XOuFkzJMOy43W2H57ufcoDWrkkvOumWybK', 1, NULL, '2026-09-25 14:12:38', '2026-09-25 14:14:16', 'employee', 1),
(58, NULL, 'Finance Department Directorate', 'fin.directorate@iprpms.gov.et', 20, NULL, '$2y$12$.Gx8dD7PEVnm7QlF/ElUaeFo0b.nK48WCNDpCPIdtlDO00H4WcTru', 1, NULL, '2026-09-25 14:12:38', '2026-09-25 14:14:16', 'employee', 1),
(59, NULL, 'Finance Department Chief (Head)', 'fin.chief@iprpms.gov.et', 20, NULL, '$2y$12$iuBTeFfX55qIC3lPgaBkDu.4m/WUpxYEj.wg/H8Kklxdzv8xiyrKy', 1, NULL, '2026-09-25 14:12:38', '2026-09-25 14:14:17', 'employee', 1),
(66, NULL, 'H.R Department Expert', 'hr.expert@iprpms.gov.et', 24, NULL, '$2y$12$jw/kG8TlK05KlFQ3LwKw3OSNoSPCLqd6GOZ8wfR95Lx1u2sGNmg2e', 1, NULL, '2026-09-25 14:12:41', '2026-09-25 14:14:20', 'employee', 1),
(67, NULL, 'H.R Department Directorate', 'hr.directorate@iprpms.gov.et', 24, NULL, '$2y$12$7GVsBB1Ky6hiJTffU9v91e0TWH6IO.eYQSJta29TBNUVPWgqXowe2', 1, NULL, '2026-09-25 14:12:42', '2026-09-25 14:14:21', 'employee', 1),
(68, NULL, 'H.R Department Chief (Head)', 'hr.chief@iprpms.gov.et', 24, NULL, '$2y$12$4.nkUy77XpC6mxo3RHnTt.0b8jtgoKDtKSagEYQ5H1EgAEOVsviZq', 1, NULL, '2026-09-25 14:12:42', '2026-09-25 14:14:21', 'employee', 1),
(72, NULL, 'Legal Department Expert', 'leg.expert@iprpms.gov.et', 26, NULL, '$2y$12$qBeC57zrc23DJZdK9XTdTuWDQeKbqnWvFishExEzlbV2y.1g2fvSm', 1, NULL, '2026-09-25 14:12:44', '2026-09-25 14:14:23', 'employee', 1),
(73, NULL, 'Legal Department Directorate', 'leg.directorate@iprpms.gov.et', 26, NULL, '$2y$12$85vxOCrMmp6mO64mpF/Eo.cCmU/lPhtHxvvyeeOCSdUOtwaYXBAIu', 1, NULL, '2026-09-25 14:12:44', '2026-09-25 14:14:24', 'employee', 1),
(74, NULL, 'Legal Department Chief (Head)', 'leg.chief@iprpms.gov.et', 26, NULL, '$2y$12$Vl080lL3tjBrNlH38UBZce7GNSbc4cAVpgcfAG7G2.9DN7W2sPUSe', 1, NULL, '2026-09-25 14:12:44', '2026-09-25 14:14:24', 'employee', 1),
(75, NULL, 'Marketing Department Expert', 'mkt.expert@iprpms.gov.et', 23, NULL, '$2y$12$fk/CHfBwz8kdWzs9vHc.8eieECi4U6GjiB8xqxpFGWIcfsrQ70cim', 1, NULL, '2026-09-25 14:12:45', '2026-09-25 14:14:24', 'employee', 1),
(76, NULL, 'Marketing Department Directorate', 'mkt.directorate@iprpms.gov.et', 23, NULL, '$2y$12$WfrzTa.OjFYg/tYGKSBdTuGZu/LzG4Six6lF2K9OdOajE0iyOOi2a', 1, NULL, '2026-09-25 14:12:45', '2026-09-25 14:14:25', 'employee', 1),
(77, NULL, 'Marketing Department Chief (Head)', 'mkt.chief@iprpms.gov.et', 23, NULL, '$2y$12$aPv8tx85Bj0xXB0/qN49KuLM5591ffeLYl5Me0T0dKByPpopjROVm', 1, NULL, '2026-09-25 14:12:46', '2026-09-25 14:14:25', 'employee', 1),
(78, NULL, 'OCC Department Expert', 'occ.expert@iprpms.gov.et', 29, NULL, '$2y$12$E45xrAG3tFH9s8UIV0OP0eukLfgCGHYy9xOxNYp3ZCtpklFEXgE6G', 1, NULL, '2026-09-25 14:12:46', '2026-09-25 14:14:26', 'employee', 1),
(79, NULL, 'OCC Department Directorate', 'occ.directorate@iprpms.gov.et', 29, NULL, '$2y$12$7n5oFUK96fO2GXgG0bqMbulJwMsj4SumNfM0oM2OqAbSD.HWk4E/m', 1, NULL, '2026-09-25 14:12:46', '2026-09-25 14:14:26', 'employee', 1),
(80, NULL, 'OCC Department Chief (Head)', 'occ.chief@iprpms.gov.et', 29, NULL, '$2y$12$rtCviNh28kSgFndH23ufTeNajb1YiAy5Y1Fv2rHa4lydf97PfSA8.', 1, NULL, '2026-09-25 14:12:47', '2026-09-25 14:14:27', 'employee', 1),
(81, NULL, 'Operation Department Department Expert', 'it.expert@iprpms.gov.et', 12, NULL, '$2y$12$h08Hro.X7PUJv8iE1mY.QuQyRkR8C3kQf57ND.gyk4IaXDuuqJIx.', 0, NULL, '2026-09-25 14:12:47', '2026-09-29 12:12:40', 'employee', 1),
(82, NULL, 'Operation Department Department Directorate', 'it.directorate@iprpms.gov.et', 12, NULL, '$2y$12$dS0YQIwLwZ3DGW4e3SNfL.ZCgi8C7/nLsNYrXN1gAZzmxQH8KT4r6', 0, NULL, '2026-09-25 14:12:48', '2026-09-29 12:01:44', 'employee', 1),
(83, NULL, 'Operation Department Department Chief (Head)', 'it.chief@iprpms.gov.et', 12, NULL, '$2y$12$Ux8u0POTW/XZ/KFnJoDlzOY3w6.gVV7C7oFKvIpwo2iTH2f2DgWO2', 0, NULL, '2026-09-25 14:12:48', '2026-09-29 11:57:05', 'employee', 1),
(84, NULL, 'Passenger Department Expert', 'psg.expert@iprpms.gov.et', 15, NULL, '$2y$12$zYRsiIcnlOLtT6noQRnoOe0THLjqtm.XtWDTM2ey.5hvQNBKu59H6', 1, NULL, '2026-09-25 14:12:48', '2026-09-25 14:14:29', 'employee', 1),
(85, NULL, 'Passenger Department Directorate', 'psg.directorate@iprpms.gov.et', 15, NULL, '$2y$12$6dlQyven5pz3tBm5ZNiUcOaDlR1RYP7wXhyL1mOljFsF1jbRHwy2i', 1, NULL, '2026-09-25 14:12:49', '2026-09-25 14:14:29', 'employee', 1),
(86, NULL, 'Passenger Department Chief (Head)', 'psg.chief@iprpms.gov.et', 15, NULL, '$2y$12$7mVq5kgVPrUPwIIzLE5Zuew88y1Z6S6i5z5vdfaFKRVZZfcS9XEKm', 1, NULL, '2026-09-25 14:12:49', '2026-09-25 14:14:30', 'employee', 1),
(87, NULL, 'Plan and Evaluation Department Expert', 'pe.expert@iprpms.gov.et', 7, NULL, '$2y$12$a6G6Lq/Xmq1vReMktiBVZuZCkJqAP44I/287Anaihl2nvMnh0hMey', 1, NULL, '2026-09-25 14:12:50', '2026-09-25 14:14:30', 'employee', 1),
(88, NULL, 'Plan and Evaluation Department Directorate', 'pe.directorate@iprpms.gov.et', 7, NULL, '$2y$12$ClKnRbZJhQAaCi9wyjdjp.jzzLMvjN2hZj7PzYJhiiA62hwrBpAXu', 1, NULL, '2026-09-25 14:12:50', '2026-09-25 14:14:31', 'employee', 1),
(89, NULL, 'Plan and Evaluation Department Chief (Head)', 'pe.chief@iprpms.gov.et', 7, NULL, '$2y$12$dFtAAiXcZuMlKsSUQsquWukR7B4RYi0l0FncOAsk4PlhjKy9700PO', 1, NULL, '2026-09-25 14:12:50', '2026-09-25 14:14:31', 'employee', 1),
(90, NULL, 'Procurement Department Expert', 'pro.expert@iprpms.gov.et', 21, NULL, '$2y$12$CT6Fh.zBp3V5DOnw3AO9VuQCJ1Cedi8ydJXA7be/h4dWzcUU/tnRa', 1, NULL, '2026-09-25 14:12:51', '2026-09-25 14:14:32', 'employee', 1),
(91, NULL, 'Procurement Department Directorate', 'pro.directorate@iprpms.gov.et', 21, NULL, '$2y$12$VFukqoB06xhX.07vXC20Ge30R9sTztD6qJ9QbuZIJx5uVk.I4UrNq', 1, NULL, '2026-09-25 14:12:51', '2026-09-25 14:14:32', 'employee', 1),
(92, NULL, 'Procurement Department Chief (Head)', 'pro.chief@iprpms.gov.et', 21, NULL, '$2y$12$/Lp4g6/AByg42jrxtPqmaO3U7lNyPcNEUgk2.MmDLjjJlbSqzqeU.', 1, NULL, '2026-09-25 14:12:52', '2026-09-25 14:14:32', 'employee', 1),
(93, NULL, 'Research Department Expert', 'res.expert@iprpms.gov.et', 19, NULL, '$2y$12$RFFlJHGOebC3mOj.Q2vuw.yUjqwyIJem4D1.T68SMJL51.dgloE2G', 1, NULL, '2026-09-25 14:12:52', '2026-09-25 14:14:33', 'employee', 1),
(94, NULL, 'Research Department Directorate', 'res.directorate@iprpms.gov.et', 19, NULL, '$2y$12$nsOwJUfhDuj0W2nLhqFkvuSSoVjBa3Sp5YElcbAyZk8apDZXhe27e', 1, NULL, '2026-09-25 14:12:53', '2026-09-25 14:14:33', 'employee', 1),
(95, NULL, 'Research Department Chief (Head)', 'res.chief@iprpms.gov.et', 19, NULL, '$2y$12$d0l8mEI31/VOZ/KMzu9HmONcAjWQU.1hS.Q4T3MlI0V5reJnTyKnO', 1, NULL, '2026-09-25 14:12:53', '2026-09-25 14:14:34', 'employee', 1),
(96, NULL, 'Rolling Stock Department Expert', 'rs.expert@iprpms.gov.et', 3, NULL, '$2y$12$0eOSG6cVpSWlM74fRqiOi.HCJEg2PtaD3LJrRRbbNQFbMy.uvMh8O', 1, NULL, '2026-09-25 14:12:53', '2026-09-25 14:14:34', 'employee', 1),
(97, NULL, 'Rolling Stock Department Directorate', 'rs.directorate@iprpms.gov.et', 3, NULL, '$2y$12$/zGulueTSj0Xi9f9BtzLKe3T3OM5lnzwkNq6qLDQRngXGoreaf/Q2', 1, NULL, '2026-09-25 14:12:54', '2026-09-25 14:14:35', 'employee', 1),
(98, NULL, 'Rolling Stock Department Chief (Head)', 'rs.chief@iprpms.gov.et', 3, NULL, '$2y$12$8DzQAKC5716TbbnzUKsXt.6WQ7PGMAPVSsSutFYOMilQCYcj3kf5i', 1, NULL, '2026-09-25 14:12:54', '2026-09-25 14:14:35', 'employee', 1),
(105, NULL, 'Training and Consultancy Department Expert', 'tc.expert@iprpms.gov.et', 4, NULL, '$2y$12$F9Dua4GzTRmpycACdrkIjOJrYb0pFWkeEK4rMARzsoTQN2TX4dKpm', 1, NULL, '2026-09-25 14:12:57', '2026-09-25 14:14:38', 'employee', 1),
(106, NULL, 'Training and Consultancy Department Directorate', 'tc.directorate@iprpms.gov.et', 4, NULL, '$2y$12$60i.3QO8Wfdf0PZ1a9BRiO1iq4bqVK1hqmbFBhvBXq0ajTanXVBqG', 1, NULL, '2026-09-25 14:12:57', '2026-09-25 14:14:39', 'employee', 1),
(107, NULL, 'Training and Consultancy Department Chief (Head)', 'tc.chief@iprpms.gov.et', 4, NULL, '$2y$12$i1Dy6E4zIXNVDWlgd0wwbuU1CRESbS39A6lLuu7dTdeF/L4t7k4cG', 1, NULL, '2026-09-25 14:12:58', '2026-09-25 14:14:39', 'employee', 1);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `users_email_unique` (`email`),
  ADD UNIQUE KEY `users_employee_id_unique` (`employee_id`),
  ADD KEY `users_department_id_foreign` (`department_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=111;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_department_id_foreign` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

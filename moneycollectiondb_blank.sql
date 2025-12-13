-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Feb 16, 2025 at 10:05 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.1.25

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `moneycollectiondb`
--

-- --------------------------------------------------------

--
-- Table structure for table `companies`
--

CREATE TABLE `companies` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `company_name` varchar(255) NOT NULL,
  `owner_name` varchar(255) NOT NULL,
  `mobile` varchar(255) DEFAULT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `aadhar_no` varchar(255) DEFAULT NULL,
  `total_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `advance_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `status` enum('active','inactive') NOT NULL,
  `main_logo` varchar(255) DEFAULT NULL,
  `sidebar_logo` varchar(255) DEFAULT NULL,
  `favicon_icon` varchar(255) DEFAULT NULL,
  `owner_image` varchar(255) DEFAULT NULL,
  `adhar_front` varchar(255) DEFAULT NULL,
  `adhar_back` varchar(255) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `details` text DEFAULT NULL,
  `primary_color` varchar(255) DEFAULT NULL,
  `secondary_color` varchar(255) DEFAULT NULL,
  `prefix` varchar(255) DEFAULT NULL,
  `member_count` int(11) NOT NULL DEFAULT 100,
  `customer_count` int(11) NOT NULL DEFAULT 100,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `company_plans`
--

CREATE TABLE `company_plans` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `plan` varchar(255) NOT NULL,
  `company_id` bigint(20) UNSIGNED NOT NULL,
  `total_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `advance_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `full_paid` int(11) NOT NULL DEFAULT 0 COMMENT '1 = yes, 0 = no',
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `status` enum('active','inactive','pending') NOT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `company_plan_history`
--

CREATE TABLE `company_plan_history` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `plan_id` bigint(20) UNSIGNED NOT NULL,
  `amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `pay_date` date DEFAULT NULL,
  `detail` text DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `customers`
--

CREATE TABLE `customers` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `company_id` bigint(20) UNSIGNED NOT NULL,
  `customer_no` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `mobile` varchar(255) DEFAULT NULL,
  `email` varchar(255) NOT NULL,
  `join_date` date NOT NULL,
  `aadhar_no` varchar(255) DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `adhar_front` varchar(255) DEFAULT NULL,
  `adhar_back` varchar(255) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `created_by` int(11) NOT NULL DEFAULT 0,
  `status` enum('active','inactive') NOT NULL,
  `loan_count` int(11) NOT NULL DEFAULT 100,
  `deposit_count` int(11) NOT NULL DEFAULT 100,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `customer_deposits`
--

CREATE TABLE `customer_deposits` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `company_id` bigint(20) UNSIGNED NOT NULL,
  `customer_id` bigint(20) UNSIGNED NOT NULL,
  `deposit_no` varchar(255) DEFAULT NULL,
  `assigned_member_id` int(11) NOT NULL DEFAULT 0,
  `member_changed_reason` text DEFAULT NULL,
  `details` text DEFAULT NULL,
  `created_by` int(11) NOT NULL DEFAULT 0,
  `status` enum('active','inactive') NOT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `status_changed_reason` varchar(255) DEFAULT NULL,
  `status_changed_by` int(11) NOT NULL DEFAULT 0,
  `status_changed_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `customer_loans`
--

CREATE TABLE `customer_loans` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `company_id` bigint(20) UNSIGNED NOT NULL,
  `customer_id` bigint(20) UNSIGNED NOT NULL,
  `loan_no` varchar(255) DEFAULT NULL,
  `loan_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `installment_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `no_of_days` int(11) NOT NULL DEFAULT 0,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `apply_date` date DEFAULT NULL,
  `assigned_member_id` int(11) NOT NULL DEFAULT 0,
  `member_changed_reason` text DEFAULT NULL,
  `details` text DEFAULT NULL,
  `created_by` int(11) NOT NULL DEFAULT 0,
  `applied_by` int(11) NOT NULL DEFAULT 0,
  `applied_user_type` int(11) DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL,
  `loan_status` enum('pending','approved','cancelled','completed','other','paid') NOT NULL,
  `loan_status_message` text DEFAULT NULL,
  `loan_status_changed_by` int(11) NOT NULL DEFAULT 0,
  `loan_status_change_date` datetime DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `deposit_history`
--

CREATE TABLE `deposit_history` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `deposit_id` bigint(20) UNSIGNED NOT NULL,
  `amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `action_type` enum('credit','debit') DEFAULT NULL,
  `action_date` datetime NOT NULL,
  `receiver_member_id` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `deposit_member_history`
--

CREATE TABLE `deposit_member_history` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `deposit_id` bigint(20) UNSIGNED NOT NULL,
  `member_id` bigint(20) UNSIGNED NOT NULL,
  `member_changed_reason` text DEFAULT NULL,
  `assigned_date` date NOT NULL,
  `assigned_by` int(11) NOT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `deposit_requests`
--

CREATE TABLE `deposit_requests` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `deposit_id` bigint(20) UNSIGNED NOT NULL,
  `request_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `reason` text DEFAULT NULL,
  `requested_by` int(11) NOT NULL DEFAULT 0,
  `status` enum('pending','approved') NOT NULL DEFAULT 'pending',
  `request_date` timestamp NULL DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `replied_message` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `failed_jobs`
--

CREATE TABLE `failed_jobs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `uuid` varchar(255) NOT NULL,
  `connection` text NOT NULL,
  `queue` text NOT NULL,
  `payload` longtext NOT NULL,
  `exception` longtext NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `fixed_deposits`
--

CREATE TABLE `fixed_deposits` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `company_id` bigint(20) UNSIGNED NOT NULL,
  `customer_id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `apply_date` date NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `deposit_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `refund_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `details` text DEFAULT NULL,
  `deposit_status` enum('started','completed','cancelled') NOT NULL DEFAULT 'started',
  `reason` varchar(255) DEFAULT NULL,
  `status_change_date` date DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `fixed_deposits_history`
--

CREATE TABLE `fixed_deposits_history` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `fixed_deposit_id` bigint(20) UNSIGNED NOT NULL,
  `amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `action_type` enum('debit') DEFAULT NULL,
  `action_date` date DEFAULT NULL,
  `debit_type` enum('monthly','money back') NOT NULL DEFAULT 'monthly',
  `details` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `loan_documents`
--

CREATE TABLE `loan_documents` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `loan_id` bigint(20) UNSIGNED NOT NULL,
  `document_url` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `loan_history`
--

CREATE TABLE `loan_history` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `loan_id` bigint(20) UNSIGNED NOT NULL,
  `amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `receive_date` datetime DEFAULT NULL,
  `receiver_member_id` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `loan_member_history`
--

CREATE TABLE `loan_member_history` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `loan_id` bigint(20) UNSIGNED NOT NULL,
  `member_id` int(11) NOT NULL,
  `member_changed_reason` text DEFAULT NULL,
  `assigned_date` date NOT NULL,
  `assigned_by` int(11) NOT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `loan_status_history`
--

CREATE TABLE `loan_status_history` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `loan_id` bigint(20) UNSIGNED NOT NULL,
  `loan_status` enum('pending','approved','cancelled','completed','other','paid') NOT NULL,
  `loan_status_message` text DEFAULT NULL,
  `loan_status_changed_by` int(11) NOT NULL,
  `loan_status_change_date` datetime DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `members`
--

CREATE TABLE `members` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `company_id` bigint(20) UNSIGNED NOT NULL,
  `member_no` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `mobile` varchar(255) DEFAULT NULL,
  `email` varchar(255) NOT NULL,
  `join_date` date NOT NULL,
  `aadhar_no` varchar(255) DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `balance` decimal(15,2) NOT NULL DEFAULT 0.00,
  `status` enum('active','inactive') NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `member_finance`
--

CREATE TABLE `member_finance` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `member_id` bigint(20) UNSIGNED NOT NULL,
  `company_id` bigint(20) UNSIGNED NOT NULL,
  `collect_date` date NOT NULL,
  `balance` decimal(15,2) NOT NULL DEFAULT 0.00,
  `paid_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `payment_status` enum('paid','working','unpaid') NOT NULL DEFAULT 'working',
  `remaining_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `previous_balance` decimal(15,2) NOT NULL DEFAULT 0.00,
  `details` varchar(255) DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `paid_date` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `member_finance_history`
--

CREATE TABLE `member_finance_history` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `member_finance_id` bigint(20) UNSIGNED NOT NULL,
  `amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `amount_by` enum('advance','loan','deposit') NOT NULL,
  `amount_by_id` int(11) NOT NULL DEFAULT 0,
  `customer_id` int(11) NOT NULL DEFAULT 0,
  `amount_type` enum('credit','debit') NOT NULL,
  `amount_date` date NOT NULL,
  `details` text DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `history_id` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `migrations`
--

CREATE TABLE `migrations` (
  `id` int(10) UNSIGNED NOT NULL,
  `migration` varchar(255) NOT NULL,
  `batch` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `migrations`
--

INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES
(1, '2014_10_12_000000_create_users_table', 1),
(2, '2014_10_12_100000_create_password_reset_tokens_table', 1),
(3, '2019_08_19_000000_create_failed_jobs_table', 1),
(4, '2019_12_14_000001_create_personal_access_tokens_table', 1),
(5, '2024_09_30_051607_create_companies_table', 1),
(6, '2024_09_30_053618_add_company_id_and_user_type_to_user_table', 1),
(7, '2024_10_02_153419_create_company_plans_table', 1),
(8, '2024_10_02_162305_create_company_plan_history_table', 1),
(9, '2024_10_11_072153_create_members_table', 1),
(10, '2024_10_12_082107_create_customers_table', 1),
(11, '2024_10_15_065751_create_customer_loans_table', 1),
(12, '2024_10_15_072712_create_loan_status_history_table', 1),
(13, '2024_10_15_072934_create_loan_member_history_table', 1),
(14, '2024_10_16_080019_add_status_to_users_table', 1),
(15, '2024_10_17_075827_create_loan_documents_table', 1),
(16, '2024_10_17_083701_create_loan_history_table', 1),
(17, '2024_10_21_104904_create_customer_deposits_table', 1),
(18, '2024_10_21_112546_create_deposit_history_table', 1),
(19, '2024_10_21_113059_create_deposite_member_history_table', 1),
(20, '2024_10_21_114239_rename_deposite_member_history_to_deposit_member_history', 1),
(21, '2024_10_21_122117_add_deposit_count_to_customers', 1),
(22, '2024_10_22_093001_add_member_changed_reason_to_customer_loans_table', 1),
(23, '2024_10_22_093226_add_member_changed_reason_to_customer_deposits_table', 1),
(24, '2024_10_22_093423_add_member_changed_reason_to_loan_member_history_table', 1),
(25, '2024_10_22_093608_add_member_changed_reason_to_deposit_member_history_table', 1),
(26, '2024_10_24_064606_add_color_to_companies_table', 1),
(27, '2024_10_24_093509_add_balance_to_members_table', 1),
(28, '2024_10_24_095539_create_member_finance_table', 1),
(29, '2024_10_24_100720_create_member_finance_history_table', 1),
(30, '2024_11_04_070422_add_details_to_member_finance_history_table', 1),
(31, '2024_11_05_050429_add_customer_id_to_member_finance_history_table', 1),
(32, '2024_11_05_064406_add_previous_amount_and_details_to_member_finance_table', 1),
(33, '2024_11_06_060502_create_offers_table', 1),
(34, '2024_11_07_055716_create_fixed_deposits_table', 1),
(35, '2024_11_07_071918_create_fixed_deposits_history_table', 1),
(36, '2024_11_07_083045_update_enum_value_in_fixed_deposits_history_table', 1),
(37, '2024_11_08_063648_add_reason_to_fixed_deposits_table', 1),
(38, '2024_11_11_072011_add_details_to_fixed_deposits_history_table', 1),
(39, '2024_11_12_062033_add_deleted_at_to_fixed_deposits_history_table', 1),
(40, '2024_11_12_065920_add_adhar_fields_to_companies_table', 1),
(41, '2024_11_12_070133_add_adhar_fields_to_customers_table', 1),
(42, '2024_11_14_070541_create_report_backups_table', 1),
(43, '2024_11_19_060623_add_status_changed_reason_to_customer_deposits_table', 1),
(44, '2024_11_21_062518_create_deposit_requests_table', 1),
(45, '2024_11_22_085307_add_replied_message_to_deposit_requests_table', 1),
(46, '2024_12_07_062836_add_language_to_users_table', 1),
(47, '2024_12_21_091752_update_action_date_in_deposit_history_table', 1),
(48, '2024_12_21_115124_add_history_id_to_member_finance_history_table', 1),
(49, '2025_01_04_172800_add_paid_date_to_member_finance_table', 1);

-- --------------------------------------------------------

--
-- Table structure for table `offers`
--

CREATE TABLE `offers` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `company_id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `type` varchar(255) NOT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `details` text DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `default_offer` int(11) NOT NULL DEFAULT 0,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `password_reset_tokens`
--

CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `personal_access_tokens`
--

CREATE TABLE `personal_access_tokens` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tokenable_type` varchar(255) NOT NULL,
  `tokenable_id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `token` varchar(64) NOT NULL,
  `abilities` text DEFAULT NULL,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `personal_access_tokens`
--

INSERT INTO `personal_access_tokens` (`id`, `tokenable_type`, `tokenable_id`, `name`, `token`, `abilities`, `last_used_at`, `expires_at`, `created_at`, `updated_at`) VALUES
(1, 'App\\Models\\User', 1, 'API Token', '63529604348f96baaa8ad852f7d2647628aa4dbd44eab10761b910833292ff64', '[\"*\"]', '2025-02-16 09:04:42', NULL, '2025-02-16 09:04:17', '2025-02-16 09:04:42');

-- --------------------------------------------------------

--
-- Table structure for table `report_backups`
--

CREATE TABLE `report_backups` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `company_id` bigint(20) UNSIGNED NOT NULL,
  `backup_type` varchar(255) NOT NULL,
  `search_data` varchar(255) NOT NULL,
  `backup_date` date NOT NULL,
  `backup_by` int(11) NOT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `user_type` int(11) NOT NULL DEFAULT 0,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `password_hint` varchar(255) DEFAULT NULL,
  `mobile` varchar(255) DEFAULT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `language` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `user_type`, `email_verified_at`, `password`, `password_hint`, `mobile`, `remember_token`, `created_at`, `updated_at`, `status`, `language`) VALUES
(1, 'Pinku User', 'pinku@gmail.com', 0, NULL, '$2y$12$U7644OjOSP8ek9yk3gOpQudOYwBP8sJ9GT8Sg3nqQZEEz9ypg/D/.', NULL, NULL, NULL, '2025-02-16 09:03:09', '2025-02-16 09:03:09', 'active', NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `companies`
--
ALTER TABLE `companies`
  ADD PRIMARY KEY (`id`),
  ADD KEY `companies_user_id_foreign` (`user_id`);

--
-- Indexes for table `company_plans`
--
ALTER TABLE `company_plans`
  ADD PRIMARY KEY (`id`),
  ADD KEY `company_plans_company_id_foreign` (`company_id`);

--
-- Indexes for table `company_plan_history`
--
ALTER TABLE `company_plan_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `company_plan_history_plan_id_foreign` (`plan_id`);

--
-- Indexes for table `customers`
--
ALTER TABLE `customers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `customers_email_unique` (`email`),
  ADD KEY `customers_user_id_foreign` (`user_id`),
  ADD KEY `customers_company_id_foreign` (`company_id`);

--
-- Indexes for table `customer_deposits`
--
ALTER TABLE `customer_deposits`
  ADD PRIMARY KEY (`id`),
  ADD KEY `customer_deposits_company_id_foreign` (`company_id`),
  ADD KEY `customer_deposits_customer_id_foreign` (`customer_id`);

--
-- Indexes for table `customer_loans`
--
ALTER TABLE `customer_loans`
  ADD PRIMARY KEY (`id`),
  ADD KEY `customer_loans_company_id_foreign` (`company_id`),
  ADD KEY `customer_loans_customer_id_foreign` (`customer_id`);

--
-- Indexes for table `deposit_history`
--
ALTER TABLE `deposit_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `deposit_history_deposit_id_foreign` (`deposit_id`);

--
-- Indexes for table `deposit_member_history`
--
ALTER TABLE `deposit_member_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `deposite_member_history_deposit_id_foreign` (`deposit_id`),
  ADD KEY `deposite_member_history_member_id_foreign` (`member_id`);

--
-- Indexes for table `deposit_requests`
--
ALTER TABLE `deposit_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `deposit_requests_deposit_id_foreign` (`deposit_id`);

--
-- Indexes for table `failed_jobs`
--
ALTER TABLE `failed_jobs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`);

--
-- Indexes for table `fixed_deposits`
--
ALTER TABLE `fixed_deposits`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fixed_deposits_customer_id_foreign` (`customer_id`),
  ADD KEY `fixed_deposits_company_id_foreign` (`company_id`);

--
-- Indexes for table `fixed_deposits_history`
--
ALTER TABLE `fixed_deposits_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fixed_deposits_history_fixed_deposit_id_foreign` (`fixed_deposit_id`);

--
-- Indexes for table `loan_documents`
--
ALTER TABLE `loan_documents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `loan_documents_loan_id_foreign` (`loan_id`);

--
-- Indexes for table `loan_history`
--
ALTER TABLE `loan_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `loan_history_loan_id_foreign` (`loan_id`);

--
-- Indexes for table `loan_member_history`
--
ALTER TABLE `loan_member_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `loan_member_history_loan_id_foreign` (`loan_id`);

--
-- Indexes for table `loan_status_history`
--
ALTER TABLE `loan_status_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `loan_status_history_loan_id_foreign` (`loan_id`);

--
-- Indexes for table `members`
--
ALTER TABLE `members`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `members_email_unique` (`email`),
  ADD KEY `members_user_id_foreign` (`user_id`),
  ADD KEY `members_company_id_foreign` (`company_id`);

--
-- Indexes for table `member_finance`
--
ALTER TABLE `member_finance`
  ADD PRIMARY KEY (`id`),
  ADD KEY `member_finance_company_id_foreign` (`company_id`),
  ADD KEY `member_finance_member_id_foreign` (`member_id`);

--
-- Indexes for table `member_finance_history`
--
ALTER TABLE `member_finance_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `member_finance_history_member_finance_id_foreign` (`member_finance_id`);

--
-- Indexes for table `migrations`
--
ALTER TABLE `migrations`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `offers`
--
ALTER TABLE `offers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `offers_company_id_foreign` (`company_id`);

--
-- Indexes for table `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  ADD PRIMARY KEY (`email`);

--
-- Indexes for table `personal_access_tokens`
--
ALTER TABLE `personal_access_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
  ADD KEY `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`,`tokenable_id`);

--
-- Indexes for table `report_backups`
--
ALTER TABLE `report_backups`
  ADD PRIMARY KEY (`id`),
  ADD KEY `report_backups_company_id_foreign` (`company_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `users_email_unique` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `companies`
--
ALTER TABLE `companies`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `company_plans`
--
ALTER TABLE `company_plans`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `company_plan_history`
--
ALTER TABLE `company_plan_history`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `customers`
--
ALTER TABLE `customers`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `customer_deposits`
--
ALTER TABLE `customer_deposits`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `customer_loans`
--
ALTER TABLE `customer_loans`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `deposit_history`
--
ALTER TABLE `deposit_history`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `deposit_member_history`
--
ALTER TABLE `deposit_member_history`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `deposit_requests`
--
ALTER TABLE `deposit_requests`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `failed_jobs`
--
ALTER TABLE `failed_jobs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `fixed_deposits`
--
ALTER TABLE `fixed_deposits`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `fixed_deposits_history`
--
ALTER TABLE `fixed_deposits_history`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `loan_documents`
--
ALTER TABLE `loan_documents`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `loan_history`
--
ALTER TABLE `loan_history`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `loan_member_history`
--
ALTER TABLE `loan_member_history`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `loan_status_history`
--
ALTER TABLE `loan_status_history`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `members`
--
ALTER TABLE `members`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `member_finance`
--
ALTER TABLE `member_finance`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `member_finance_history`
--
ALTER TABLE `member_finance_history`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `migrations`
--
ALTER TABLE `migrations`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=50;

--
-- AUTO_INCREMENT for table `offers`
--
ALTER TABLE `offers`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `personal_access_tokens`
--
ALTER TABLE `personal_access_tokens`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `report_backups`
--
ALTER TABLE `report_backups`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `companies`
--
ALTER TABLE `companies`
  ADD CONSTRAINT `companies_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `company_plans`
--
ALTER TABLE `company_plans`
  ADD CONSTRAINT `company_plans_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `company_plan_history`
--
ALTER TABLE `company_plan_history`
  ADD CONSTRAINT `company_plan_history_plan_id_foreign` FOREIGN KEY (`plan_id`) REFERENCES `company_plans` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `customers`
--
ALTER TABLE `customers`
  ADD CONSTRAINT `customers_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `customers_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `customer_deposits`
--
ALTER TABLE `customer_deposits`
  ADD CONSTRAINT `customer_deposits_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `customer_deposits_customer_id_foreign` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `customer_loans`
--
ALTER TABLE `customer_loans`
  ADD CONSTRAINT `customer_loans_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `customer_loans_customer_id_foreign` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `deposit_history`
--
ALTER TABLE `deposit_history`
  ADD CONSTRAINT `deposit_history_deposit_id_foreign` FOREIGN KEY (`deposit_id`) REFERENCES `customer_deposits` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `deposit_member_history`
--
ALTER TABLE `deposit_member_history`
  ADD CONSTRAINT `deposite_member_history_deposit_id_foreign` FOREIGN KEY (`deposit_id`) REFERENCES `customer_deposits` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `deposite_member_history_member_id_foreign` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `deposit_requests`
--
ALTER TABLE `deposit_requests`
  ADD CONSTRAINT `deposit_requests_deposit_id_foreign` FOREIGN KEY (`deposit_id`) REFERENCES `customer_deposits` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `fixed_deposits`
--
ALTER TABLE `fixed_deposits`
  ADD CONSTRAINT `fixed_deposits_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fixed_deposits_customer_id_foreign` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `fixed_deposits_history`
--
ALTER TABLE `fixed_deposits_history`
  ADD CONSTRAINT `fixed_deposits_history_fixed_deposit_id_foreign` FOREIGN KEY (`fixed_deposit_id`) REFERENCES `fixed_deposits` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `loan_documents`
--
ALTER TABLE `loan_documents`
  ADD CONSTRAINT `loan_documents_loan_id_foreign` FOREIGN KEY (`loan_id`) REFERENCES `customer_loans` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `loan_history`
--
ALTER TABLE `loan_history`
  ADD CONSTRAINT `loan_history_loan_id_foreign` FOREIGN KEY (`loan_id`) REFERENCES `customer_loans` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `loan_member_history`
--
ALTER TABLE `loan_member_history`
  ADD CONSTRAINT `loan_member_history_loan_id_foreign` FOREIGN KEY (`loan_id`) REFERENCES `customer_loans` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `loan_status_history`
--
ALTER TABLE `loan_status_history`
  ADD CONSTRAINT `loan_status_history_loan_id_foreign` FOREIGN KEY (`loan_id`) REFERENCES `customer_loans` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `members`
--
ALTER TABLE `members`
  ADD CONSTRAINT `members_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `members_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `member_finance`
--
ALTER TABLE `member_finance`
  ADD CONSTRAINT `member_finance_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `member_finance_member_id_foreign` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `member_finance_history`
--
ALTER TABLE `member_finance_history`
  ADD CONSTRAINT `member_finance_history_member_finance_id_foreign` FOREIGN KEY (`member_finance_id`) REFERENCES `member_finance` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `offers`
--
ALTER TABLE `offers`
  ADD CONSTRAINT `offers_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `report_backups`
--
ALTER TABLE `report_backups`
  ADD CONSTRAINT `report_backups_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

-- Distributor registration schema (aligned with reg-baby + WordPress plugin inserts).
-- Replace `wp_` with your WordPress $table_prefix if you run this manually in phpMyAdmin.
-- Prefer running the plugin migration: it uses dbDelta() and your real prefix automatically.

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------------
-- country
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `wp_country` (
  `country_id` int unsigned NOT NULL AUTO_INCREMENT,
  `country_title` varchar(190) NOT NULL,
  `status` tinyint NOT NULL DEFAULT 1,
  PRIMARY KEY (`country_id`),
  KEY `idx_country_title` (`country_title`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `wp_country` (`country_id`, `country_title`, `status`)
VALUES (1, 'Canada', 1)
ON DUPLICATE KEY UPDATE `country_title` = VALUES(`country_title`), `status` = VALUES(`status`);

-- ---------------------------------------------------------------------------
-- province
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `wp_province` (
  `province_id` int unsigned NOT NULL AUTO_INCREMENT,
  `country_id` int unsigned NOT NULL DEFAULT 1,
  `province_name` varchar(190) NOT NULL,
  `province_abbrev` varchar(8) NOT NULL,
  `status` tinyint NOT NULL DEFAULT 1,
  PRIMARY KEY (`province_id`),
  UNIQUE KEY `uq_province_country_abbrev` (`country_id`,`province_abbrev`),
  KEY `idx_province_country` (`country_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- city
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `wp_city` (
  `city_id` int unsigned NOT NULL AUTO_INCREMENT,
  `province_id` int unsigned NOT NULL,
  `city_name` varchar(190) NOT NULL,
  `status` tinyint NOT NULL DEFAULT 2,
  PRIMARY KEY (`city_id`),
  KEY `idx_city_province_name` (`province_id`,`city_name`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- address
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `wp_address` (
  `address_id` int unsigned NOT NULL AUTO_INCREMENT,
  `suite_number` varchar(64) DEFAULT NULL,
  `address1` varchar(255) NOT NULL,
  `address2` varchar(255) NOT NULL DEFAULT '',
  `city_id` int unsigned NOT NULL,
  `postal_code` varchar(32) NOT NULL,
  PRIMARY KEY (`address_id`),
  KEY `idx_address_city` (`city_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- distributors
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `wp_distributors` (
  `distributor_id` int unsigned NOT NULL AUTO_INCREMENT,
  `firstname` varchar(120) NOT NULL,
  `lastname` varchar(120) NOT NULL,
  `email` varchar(190) NOT NULL,
  `password` varchar(255) DEFAULT NULL,
  `phone` varchar(40) NOT NULL DEFAULT '',
  `ext` varchar(32) NOT NULL DEFAULT '',
  `fax` varchar(40) DEFAULT NULL,
  `job_title` varchar(190) NOT NULL DEFAULT '',
  `organization_name` varchar(255) NOT NULL DEFAULT '',
  `department` varchar(190) NOT NULL DEFAULT '',
  `address_id` int unsigned NOT NULL,
  `language` tinyint unsigned NOT NULL DEFAULT 1,
  `inst` varchar(32) NOT NULL DEFAULT '',
  `terms_reception` varchar(32) NOT NULL DEFAULT '',
  `patient_see_weekly` varchar(64) NOT NULL DEFAULT '',
  `freight_cost` decimal(10,2) DEFAULT NULL,
  `display_organization_name` varchar(255) DEFAULT NULL,
  `created_by` int NOT NULL DEFAULT 0,
  `created_date` datetime NOT NULL,
  `last_updated_by` int NOT NULL DEFAULT 0,
  `last_updated_date` datetime NOT NULL,
  PRIMARY KEY (`distributor_id`),
  UNIQUE KEY `uq_distributors_email` (`email`),
  KEY `idx_distributors_address` (`address_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- distributors_status
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `wp_distributors_status` (
  `distributor_status_id` int unsigned NOT NULL AUTO_INCREMENT,
  `distributor_id` int unsigned NOT NULL,
  `account_status` tinyint NOT NULL DEFAULT 2,
  `admin_status` tinyint NOT NULL DEFAULT 0,
  `ship_by` varchar(64) DEFAULT NULL,
  `created_by` int NOT NULL DEFAULT 0,
  `approved_by` int DEFAULT NULL,
  `created_date` datetime NOT NULL,
  `last_updated_by` int NOT NULL DEFAULT 0,
  `last_updated_date` datetime NOT NULL,
  `verified_date` datetime DEFAULT NULL,
  PRIMARY KEY (`distributor_status_id`),
  UNIQUE KEY `uq_status_distributor` (`distributor_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- distributors_distribution_plan
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `wp_distributors_distribution_plan` (
  `distribution_plan_id` int unsigned NOT NULL AUTO_INCREMENT,
  `distributor_id` int unsigned NOT NULL,
  `yearly_total` int NOT NULL DEFAULT 0,
  `january` int NOT NULL DEFAULT 0,
  `february` int NOT NULL DEFAULT 0,
  `march` int NOT NULL DEFAULT 0,
  `april` int NOT NULL DEFAULT 0,
  `may` int NOT NULL DEFAULT 0,
  `june` int NOT NULL DEFAULT 0,
  `july` int NOT NULL DEFAULT 0,
  `august` int NOT NULL DEFAULT 0,
  `september` int NOT NULL DEFAULT 0,
  `october` int NOT NULL DEFAULT 0,
  `november` int NOT NULL DEFAULT 0,
  `december` int NOT NULL DEFAULT 0,
  `bottle` tinyint unsigned NOT NULL DEFAULT 0,
  `vitamins` tinyint unsigned NOT NULL DEFAULT 0,
  `formula` tinyint unsigned NOT NULL DEFAULT 0,
  `category` varchar(190) NOT NULL DEFAULT '',
  `frequency` varchar(64) NOT NULL DEFAULT '',
  `patient_type` varchar(190) NOT NULL DEFAULT '',
  `admin_comments` text,
  `shipping_information` text,
  `special_requirements` text,
  `created_by` int NOT NULL DEFAULT 0,
  `created_date` datetime NOT NULL,
  `last_updated_by` int NOT NULL DEFAULT 0,
  `last_updated_date` datetime NOT NULL,
  PRIMARY KEY (`distribution_plan_id`),
  UNIQUE KEY `uq_plan_distributor` (`distributor_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- distributors_doctors
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `wp_distributors_doctors` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `distributor_id` int unsigned NOT NULL,
  `prefix` varchar(64) NOT NULL DEFAULT '',
  `first_name` varchar(120) NOT NULL,
  `last_name` varchar(120) NOT NULL,
  `status` tinyint unsigned NOT NULL DEFAULT 1,
  `created_by` int NOT NULL DEFAULT 0,
  `created_date` datetime NOT NULL,
  `deleted_by` int DEFAULT NULL,
  `deleted_date` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_doctors_distributor` (`distributor_id`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

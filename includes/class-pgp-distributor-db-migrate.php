<?php
/**
 * Creates distributor-related tables using dbDelta (WordPress-safe).
 * Table names: {$wpdb->prefix}dis_{suffix} (e.g. wp_dis_distributors).
 */

if (!defined('ABSPATH')) {
    exit;
}

class PGP_Distributor_Db_Migrate
{
    const OPTION_VERSION = 'pgp_distributor_db_version';

    const VERSION = '1.0.0';

    public static function maybe_install()
    {
        if (!function_exists('pgp_find_distributor_table_name')) {
            return;
        }
        if (pgp_find_distributor_table_name('distributors') !== '') {
            return;
        }
        self::install();
    }

    public static function install()
    {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charsetCollate = $wpdb->get_charset_collate();
        $p = $wpdb->prefix . 'dis_';

        $tables = [
            self::sql_country($p, $charsetCollate),
            self::sql_province($p, $charsetCollate),
            self::sql_city($p, $charsetCollate),
            self::sql_address($p, $charsetCollate),
            self::sql_distributors($p, $charsetCollate),
            self::sql_distributors_status($p, $charsetCollate),
            self::sql_distributors_distribution_plan($p, $charsetCollate),
            self::sql_distributors_doctors($p, $charsetCollate),
        ];

        foreach ($tables as $sql) {
            dbDelta($sql);
        }

        $countryTable = $p . 'country';
        $wpdb->replace(
            $countryTable,
            [
                'country_id' => 1,
                'country_title' => 'Canada',
                'status' => 1,
            ],
            ['%d', '%s', '%d']
        );

        update_option(self::OPTION_VERSION, self::VERSION, false);
    }

    private static function sql_country($p, $charsetCollate)
    {
        return "CREATE TABLE {$p}country (
                country_id int unsigned NOT NULL auto_increment,
                country_title varchar(190) NOT NULL,
                status tinyint NOT NULL default 1,
                PRIMARY KEY  (country_id),
                KEY country_title (country_title(64))
                ) {$charsetCollate};";
    }

    private static function sql_province($p, $charsetCollate)
    {
        return "CREATE TABLE {$p}province (
                province_id int unsigned NOT NULL auto_increment,
                country_id int unsigned NOT NULL default 1,
                province_name varchar(190) NOT NULL,
                province_abbrev varchar(8) NOT NULL,
                status tinyint NOT NULL default 1,
                PRIMARY KEY  (province_id),
                KEY country_abbrev (country_id,province_abbrev),
                KEY country_id (country_id)
                ) {$charsetCollate};";
    }

    private static function sql_city($p, $charsetCollate)
    {
        return "CREATE TABLE {$p}city (
                city_id int unsigned NOT NULL auto_increment,
                province_id int unsigned NOT NULL,
                city_name varchar(190) NOT NULL,
                status tinyint NOT NULL default 2,
                PRIMARY KEY  (city_id),
                KEY province_city (province_id,city_name(64))
                ) {$charsetCollate};";
    }

    private static function sql_address($p, $charsetCollate)
    {
        return "CREATE TABLE {$p}address (
                address_id int unsigned NOT NULL auto_increment,
                suite_number varchar(64) NULL,
                address1 varchar(255) NOT NULL,
                address2 varchar(255) NOT NULL default '',
                city_id int unsigned NOT NULL,
                postal_code varchar(32) NOT NULL,
                PRIMARY KEY  (address_id),
                KEY city_id (city_id)
                ) {$charsetCollate};";
    }

    private static function sql_distributors($p, $charsetCollate)
    {
        return "CREATE TABLE {$p}distributors (
                distributor_id int unsigned NOT NULL auto_increment,
                firstname varchar(120) NOT NULL,
                lastname varchar(120) NOT NULL,
                email varchar(190) NOT NULL,
                password varchar(255) NULL,
                phone varchar(40) NOT NULL default '',
                ext varchar(32) NOT NULL default '',
                fax varchar(40) NULL,
                job_title varchar(190) NOT NULL default '',
                organization_name varchar(255) NOT NULL default '',
                department varchar(190) NOT NULL default '',
                address_id int unsigned NOT NULL,
                language tinyint unsigned NOT NULL default 1,
                inst varchar(32) NOT NULL default '',
                terms_reception varchar(32) NOT NULL default '',
                patient_see_weekly varchar(64) NOT NULL default '',
                freight_cost decimal(10,2) NULL,
                display_organization_name varchar(255) NULL,
                created_by int NOT NULL default 0,
                created_date datetime NOT NULL,
                last_updated_by int NOT NULL default 0,
                last_updated_date datetime NOT NULL,
                PRIMARY KEY  (distributor_id),
                UNIQUE KEY email (email),
                KEY address_id (address_id)
                ) {$charsetCollate};";
    }

    private static function sql_distributors_status($p, $charsetCollate)
    {
        return "CREATE TABLE {$p}distributors_status (
                distributor_status_id int unsigned NOT NULL auto_increment,
                distributor_id int unsigned NOT NULL,
                account_status tinyint NOT NULL default 2,
                admin_status tinyint NOT NULL default 0,
                ship_by varchar(64) NULL,
                created_by int NOT NULL default 0,
                approved_by int NULL,
                created_date datetime NOT NULL,
                last_updated_by int NOT NULL default 0,
                last_updated_date datetime NOT NULL,
                verified_date datetime NULL,
                PRIMARY KEY  (distributor_status_id),
                UNIQUE KEY distributor_id (distributor_id)
                ) {$charsetCollate};";
    }

    private static function sql_distributors_distribution_plan($p, $charsetCollate)
    {
        return "CREATE TABLE {$p}distributors_distribution_plan (
                distribution_plan_id int unsigned NOT NULL auto_increment,
                distributor_id int unsigned NOT NULL,
                yearly_total int NOT NULL default 0,
                january int NOT NULL default 0,
                february int NOT NULL default 0,
                march int NOT NULL default 0,
                april int NOT NULL default 0,
                may int NOT NULL default 0,
                june int NOT NULL default 0,
                july int NOT NULL default 0,
                august int NOT NULL default 0,
                september int NOT NULL default 0,
                october int NOT NULL default 0,
                november int NOT NULL default 0,
                december int NOT NULL default 0,
                bottle tinyint unsigned NOT NULL default 0,
                vitamins tinyint unsigned NOT NULL default 0,
                formula tinyint unsigned NOT NULL default 0,
                category varchar(190) NOT NULL default '',
                frequency varchar(64) NOT NULL default '',
                patient_type varchar(190) NOT NULL default '',
                admin_comments text NULL,
                shipping_information text NULL,
                special_requirements text NULL,
                created_by int NOT NULL default 0,
                created_date datetime NOT NULL,
                last_updated_by int NOT NULL default 0,
                last_updated_date datetime NOT NULL,
                PRIMARY KEY  (distribution_plan_id),
                UNIQUE KEY distributor_id (distributor_id)
                ) {$charsetCollate};";
    }

    private static function sql_distributors_doctors($p, $charsetCollate)
    {
        return "CREATE TABLE {$p}distributors_doctors (
                id int unsigned NOT NULL auto_increment,
                distributor_id int unsigned NOT NULL,
                prefix varchar(64) NOT NULL default '',
                first_name varchar(120) NOT NULL,
                last_name varchar(120) NOT NULL,
                status tinyint unsigned NOT NULL default 1,
                created_by int NOT NULL default 0,
                created_date datetime NOT NULL,
                deleted_by int NULL,
                deleted_date datetime NULL,
                PRIMARY KEY  (id),
                KEY distributor_status (distributor_id,status)
                ) {$charsetCollate};";
    }
}

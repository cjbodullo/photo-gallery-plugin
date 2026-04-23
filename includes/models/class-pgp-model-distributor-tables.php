<?php
/**
 * Resolved MySQL table names for distributor registration ({prefix}dis_* first, then legacy names).
 */

if (!defined('ABSPATH')) {
    exit;
}

class PGP_Model_Distributor_Tables
{
    /** @var string */
    public $country = '';

    /** @var string */
    public $distributors = '';

    /** @var string */
    public $distributors_status = '';

    /** @var string */
    public $distributors_distribution_plan = '';

    /** @var string */
    public $distributors_doctors = '';

    /** @var string */
    public $address = '';

    /** @var string */
    public $city = '';

    /** @var string */
    public $province = '';

    public function is_complete()
    {
        return $this->distributors !== ''
            && $this->distributors_status !== ''
            && $this->distributors_distribution_plan !== ''
            && $this->distributors_doctors !== ''
            && $this->address !== ''
            && $this->city !== ''
            && $this->province !== ''
            && $this->country !== '';
    }

    /**
     * @return self
     */
    public static function resolve()
    {
        $t = new self();
        if (!function_exists('pgp_find_distributor_table_name')) {
            return $t;
        }
        $t->country = pgp_find_distributor_table_name('country');
        $t->distributors = pgp_find_distributor_table_name('distributors');
        $t->distributors_status = pgp_find_distributor_table_name('distributors_status');
        $t->distributors_distribution_plan = pgp_find_distributor_table_name('distributors_distribution_plan');
        $t->distributors_doctors = pgp_find_distributor_table_name('distributors_doctors');
        $t->address = pgp_find_distributor_table_name('address');
        $t->city = pgp_find_distributor_table_name('city');
        $t->province = pgp_find_distributor_table_name('province');

        return $t;
    }
}

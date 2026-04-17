<?php
/**
 * Data access for distributor registration (model / repository layer).
 */

if (!defined('ABSPATH')) {
    exit;
}

class PGP_Distributor_Repository
{
    /** @var wpdb */
    private $db;

    /** @var PGP_Model_Distributor_Tables */
    private $tables;

    public function __construct(PGP_Model_Distributor_Tables $tables)
    {
        global $wpdb;
        $this->db = $wpdb;
        $this->tables = $tables;
    }

    public function email_exists($email)
    {
        $email = sanitize_email($email);
        if ($email === '') {
            return false;
        }
        $found = $this->db->get_var(
            $this->db->prepare(
                "SELECT distributor_id FROM {$this->tables->distributors} WHERE email = %s LIMIT 1",
                $email
            )
        );

        return !empty($found);
    }

    /**
     * @param int $countryId
     * @return int|false province_id
     */
    public function find_or_create_province($abbrev, $provinceName, $countryId = 1)
    {
        $abbrev = sanitize_text_field($abbrev);
        $provinceName = sanitize_text_field($provinceName);
        $pid = $this->db->get_var(
            $this->db->prepare(
                "SELECT province_id FROM {$this->tables->province} WHERE province_abbrev = %s AND country_id = %d LIMIT 1",
                $abbrev,
                $countryId
            )
        );
        if (!empty($pid)) {
            return (int) $pid;
        }
        $ok = $this->db->insert(
            $this->tables->province,
            [
                'country_id' => $countryId,
                'province_name' => $provinceName,
                'province_abbrev' => $abbrev,
                'status' => 1,
            ],
            ['%d', '%s', '%s', '%d']
        );

        return $ok ? (int) $this->db->insert_id : false;
    }

    /**
     * @return int|false city_id
     */
    public function find_or_create_city($cityName, $provinceId)
    {
        $cityName = sanitize_text_field($cityName);
        $cid = $this->db->get_var(
            $this->db->prepare(
                "SELECT city_id FROM {$this->tables->city} WHERE city_name = %s AND province_id = %d LIMIT 1",
                $cityName,
                $provinceId
            )
        );
        if (!empty($cid)) {
            return (int) $cid;
        }
        $ok = $this->db->insert(
            $this->tables->city,
            [
                'province_id' => $provinceId,
                'city_name' => $cityName,
                'status' => 2,
            ],
            ['%d', '%s', '%d']
        );

        return $ok ? (int) $this->db->insert_id : false;
    }

    /**
     * @return int|false address_id
     */
    public function insert_address($suite, $address1, $cityId, $postalCode)
    {
        $ok = $this->db->insert(
            $this->tables->address,
            [
                'suite_number' => sanitize_text_field($suite),
                'address1' => sanitize_text_field($address1),
                'address2' => '',
                'city_id' => $cityId,
                'postal_code' => sanitize_text_field($postalCode),
            ],
            ['%s', '%s', '%s', '%d', '%s']
        );

        return $ok ? (int) $this->db->insert_id : false;
    }

    /**
     * @return int|false distributor_id
     */
    public function insert_distributor(array $row)
    {
        $ok = $this->db->insert($this->tables->distributors, $row['data'], $row['format']);

        return $ok ? (int) $this->db->insert_id : false;
    }

    /**
     * @return bool
     */
    public function insert_status(array $row)
    {
        return (bool) $this->db->insert($this->tables->distributors_status, $row['data'], $row['format']);
    }

    /**
     * @return bool
     */
    public function insert_distribution_plan(array $row)
    {
        return (bool) $this->db->insert($this->tables->distributors_distribution_plan, $row['data'], $row['format']);
    }

    public function insert_doctor_row($distributorId, $prefix, $firstName, $lastName, $createdBy, $createdDate)
    {
        $this->db->insert(
            $this->tables->distributors_doctors,
            [
                'distributor_id' => $distributorId,
                'prefix' => sanitize_text_field($prefix),
                'first_name' => sanitize_text_field($firstName),
                'last_name' => sanitize_text_field($lastName),
                'created_by' => $createdBy,
                'created_date' => $createdDate,
            ],
            ['%d', '%s', '%s', '%s', '%d', '%s']
        );
    }

    /**
     * @return array{yearly_total:int, months:array<string,int>}
     */
    public static function compute_plan_months($sampleFrequency, $nuOfSamples)
    {
        $months = [
            'january' => 0, 'february' => 0, 'march' => 0, 'april' => 0, 'may' => 0, 'june' => 0,
            'july' => 0, 'august' => 0, 'september' => 0, 'october' => 0, 'november' => 0, 'december' => 0,
        ];
        $yearlyTotal = 0;
        $nuOfSamples = (int) $nuOfSamples;

        if ($sampleFrequency === 'Monthly') {
            $yearlyTotal = $nuOfSamples * 12;
            foreach ($months as $k => $_) {
                $months[$k] = $nuOfSamples;
            }
        } elseif ($sampleFrequency === 'BiMonthly') {
            $yearlyTotal = $nuOfSamples * 6;
            $months['january'] = $nuOfSamples;
            $months['march'] = $nuOfSamples;
            $months['may'] = $nuOfSamples;
            $months['july'] = $nuOfSamples;
            $months['september'] = $nuOfSamples;
            $months['november'] = $nuOfSamples;
        } elseif ($sampleFrequency === 'Quarterly') {
            $yearlyTotal = $nuOfSamples * 4;
            $months['february'] = $nuOfSamples;
            $months['may'] = $nuOfSamples;
            $months['august'] = $nuOfSamples;
            $months['november'] = $nuOfSamples;
        } elseif ($sampleFrequency === 'SemiAnnually') {
            $yearlyTotal = $nuOfSamples * 2;
            $currentMonth = (int) date('n');
            $firstMonth = ($currentMonth % 12) + 1;
            $secondMonth = ($firstMonth + 6) % 12;
            if ($secondMonth === 0) {
                $secondMonth = 12;
            }
            $firstKey = strtolower(date('F', mktime(0, 0, 0, $firstMonth, 10)));
            $secondKey = strtolower(date('F', mktime(0, 0, 0, $secondMonth, 10)));
            if (isset($months[$firstKey])) {
                $months[$firstKey] = $nuOfSamples;
            }
            if (isset($months[$secondKey])) {
                $months[$secondKey] = $nuOfSamples;
            }
        } elseif ($sampleFrequency === 'Annual') {
            $yearlyTotal = $nuOfSamples;
            $currentMonth = (int) date('n');
            $distributionMonth = ($currentMonth % 12) + 1;
            $key = strtolower(date('F', mktime(0, 0, 0, $distributionMonth, 10)));
            if (isset($months[$key])) {
                $months[$key] = $nuOfSamples;
            }
        } else {
            return ['yearly_total' => 0, 'months' => $months, 'valid' => false];
        }

        return ['yearly_total' => $yearlyTotal, 'months' => $months, 'valid' => true];
    }
}

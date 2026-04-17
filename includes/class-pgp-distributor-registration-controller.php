<?php
/**
 * Handles distributor registration POST flow (controller layer).
 */

if (!defined('ABSPATH')) {
    exit;
}

class PGP_Distributor_Registration_Controller
{
    /**
     * Process registration. Returns [ 'status' => 'success'|'error', 'message' => string ].
     *
     * @param array<string,mixed> $post
     * @return array{status:string,message:string}
     */
    public function handle(array $post)
    {
        $tables = PGP_Model_Distributor_Tables::resolve();
        if (!$tables->is_complete()) {
            $missing = [];
            foreach (
                [
                    'distributors' => $tables->distributors,
                    'distributors_status' => $tables->distributors_status,
                    'distributors_distribution_plan' => $tables->distributors_distribution_plan,
                    'distributors_doctors' => $tables->distributors_doctors,
                    'address' => $tables->address,
                    'city' => $tables->city,
                    'province' => $tables->province,
                ] as $label => $name
            ) {
                if ($name === '') {
                    $missing[] = $label;
                }
            }

            return [
                'status' => 'error',
                'message' => 'Database table not found: ' . implode(', ', $missing),
            ];
        }

        $email = isset($post['email']) ? sanitize_email(wp_unslash($post['email'])) : '';
        $firstName = isset($post['firstName']) ? sanitize_text_field(wp_unslash($post['firstName'])) : '';
        $lastName = isset($post['lastName']) ? sanitize_text_field(wp_unslash($post['lastName'])) : '';
        $phoneRaw = isset($post['phone']) ? sanitize_text_field(wp_unslash($post['phone'])) : '';
        $phoneDigits = preg_replace('/\D+/', '', (string) $phoneRaw);
        $extension = isset($post['extension']) ? sanitize_text_field(wp_unslash($post['extension'])) : '';
        $job = isset($post['job']) ? sanitize_text_field(wp_unslash($post['job'])) : '';
        $orgName = isset($post['name']) ? sanitize_text_field(wp_unslash($post['name'])) : '';
        $department = isset($post['department']) ? sanitize_text_field(wp_unslash($post['department'])) : '';
        $address1 = isset($post['address1']) ? sanitize_text_field(wp_unslash($post['address1'])) : '';
        $suite = isset($post['suite']) ? sanitize_text_field(wp_unslash($post['suite'])) : '';
        $city = isset($post['city']) ? sanitize_text_field(wp_unslash($post['city'])) : '';
        $provinceAbbrev = isset($post['province']) ? sanitize_text_field(wp_unslash($post['province'])) : '';
        $postalCode = isset($post['postalCode']) ? sanitize_text_field(wp_unslash($post['postalCode'])) : '';
        $category = isset($post['category']) ? sanitize_text_field(wp_unslash($post['category'])) : '';
        $patientsType = isset($post['patientsType']) ? sanitize_text_field(wp_unslash($post['patientsType'])) : '';

        $bottles = isset($post['bottles']) ? sanitize_text_field(wp_unslash($post['bottles'])) : '';
        $vitamins = isset($post['vitamins']) ? sanitize_text_field(wp_unslash($post['vitamins'])) : '';
        $formula = isset($post['formula']) ? sanitize_text_field(wp_unslash($post['formula'])) : '';
        $language = isset($post['language']) ? absint($post['language']) : 0;
        $sampleFrequency = isset($post['sampleFrequency']) ? sanitize_text_field(wp_unslash($post['sampleFrequency'])) : '';
        $numberOfPatients = isset($post['numberOfPatients']) ? sanitize_text_field(wp_unslash($post['numberOfPatients'])) : '';
        $nuOfSamples = isset($post['nuOfSamples']) ? absint($post['nuOfSamples']) : 0;
        $specialShippingInstruction = isset($post['specialShippingInstruction'])
            ? sanitize_text_field(wp_unslash($post['specialShippingInstruction']))
            : '';

        $terms1 = isset($post['terms1']) ? 'on' : '';
        $terms2 = isset($post['terms2']) ? 'on' : '';

        if (
            $orgName === '' ||
            $firstName === '' ||
            $lastName === '' ||
            $email === '' ||
            $job === '' ||
            $department === '' ||
            $phoneDigits === '' ||
            $address1 === '' ||
            $city === '' ||
            $provinceAbbrev === '' ||
            $postalCode === '' ||
            $category === '' ||
            $patientsType === '' ||
            $bottles === '' ||
            $vitamins === '' ||
            $formula === '' ||
            $language < 1 ||
            $sampleFrequency === '' ||
            $numberOfPatients === '' ||
            $nuOfSamples < 1 ||
            $terms1 === '' ||
            $terms2 === ''
        ) {
            return ['status' => 'error', 'message' => 'Please complete all required fields.'];
        }

        if (!is_email($email)) {
            return ['status' => 'error', 'message' => 'Please enter a valid email address.'];
        }

        $repo = new PGP_Distributor_Repository($tables);
        if ($repo->email_exists($email)) {
            return ['status' => 'error', 'message' => 'Distributor with email already exists.'];
        }

        $provinces = pgp_get_canadian_provinces();
        $provinceName = isset($provinces[$provinceAbbrev]) ? $provinces[$provinceAbbrev] : $provinceAbbrev;

        $provinceId = $repo->find_or_create_province($provinceAbbrev, $provinceName, 1);
        if ($provinceId === false) {
            return ['status' => 'error', 'message' => 'Unable to save province.'];
        }

        $cityId = $repo->find_or_create_city($city, $provinceId);
        if ($cityId === false) {
            return ['status' => 'error', 'message' => 'Unable to save city.'];
        }

        $addressId = $repo->insert_address($suite, $address1, $cityId, $postalCode);
        if ($addressId === false) {
            return ['status' => 'error', 'message' => 'Unable to save address.'];
        }

        $now = current_time('mysql');
        $distributorId = $repo->insert_distributor([
            'data' => [
                'firstname' => $firstName,
                'lastname' => $lastName,
                'email' => $email,
                'password' => null,
                'phone' => $phoneDigits,
                'ext' => $extension,
                'fax' => null,
                'job_title' => $job,
                'organization_name' => $orgName,
                'department' => $department,
                'address_id' => $addressId,
                'language' => $language,
                'inst' => $terms1,
                'created_by' => 0,
                'created_date' => $now,
                'last_updated_by' => 0,
                'last_updated_date' => $now,
                'terms_reception' => $terms2,
                'patient_see_weekly' => $numberOfPatients,
            ],
            'format' => [
                '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s',
                '%d', '%d', '%s', '%d', '%s', '%d', '%s', '%s', '%s',
            ],
        ]);
        if ($distributorId === false) {
            return ['status' => 'error', 'message' => 'Unable to save distributor.'];
        }

        $ok = $repo->insert_status([
            'data' => [
                'distributor_id' => $distributorId,
                'account_status' => 2,
                'admin_status' => 0,
                'ship_by' => null,
                'created_by' => 0,
                'approved_by' => null,
                'created_date' => $now,
                'last_updated_by' => 0,
                'last_updated_date' => $now,
                'verified_date' => null,
            ],
            'format' => ['%d', '%d', '%d', '%s', '%d', '%s', '%s', '%d', '%s', '%s'],
        ]);
        if (!$ok) {
            return ['status' => 'error', 'message' => 'Unable to save distributor status.'];
        }

        $plan = PGP_Distributor_Repository::compute_plan_months($sampleFrequency, $nuOfSamples);
        if (empty($plan['valid'])) {
            return ['status' => 'error', 'message' => 'Invalid sample frequency.'];
        }
        $months = $plan['months'];

        $ok = $repo->insert_distribution_plan([
            'data' => [
                'distributor_id' => $distributorId,
                'yearly_total' => $plan['yearly_total'],
                'january' => $months['january'],
                'february' => $months['february'],
                'march' => $months['march'],
                'april' => $months['april'],
                'may' => $months['may'],
                'june' => $months['june'],
                'july' => $months['july'],
                'august' => $months['august'],
                'september' => $months['september'],
                'october' => $months['october'],
                'november' => $months['november'],
                'december' => $months['december'],
                'bottle' => ($bottles === 'Yes') ? 1 : 0,
                'vitamins' => ($vitamins === 'Yes') ? 1 : 0,
                'formula' => ($formula === 'Yes') ? 1 : 0,
                'category' => $category,
                'frequency' => $sampleFrequency,
                'patient_type' => $patientsType,
                'admin_comments' => null,
                'shipping_information' => $specialShippingInstruction,
                'special_requirements' => null,
                'created_by' => $distributorId,
                'created_date' => $now,
                'last_updated_by' => $distributorId,
                'last_updated_date' => $now,
            ],
            'format' => [
                '%d', '%d',
                '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d',
                '%d', '%d', '%d',
                '%s', '%s', '%s',
                '%s', '%s', '%s',
                '%d', '%s', '%d', '%s',
            ],
        ]);
        if (!$ok) {
            return ['status' => 'error', 'message' => 'Unable to save distribution plan.'];
        }

        $prefixes = isset($post['prefix']) && is_array($post['prefix'])
            ? array_map('sanitize_text_field', wp_unslash($post['prefix']))
            : [];
        $fNames = isset($post['fName']) && is_array($post['fName'])
            ? array_map('sanitize_text_field', wp_unslash($post['fName']))
            : [];
        $lNames = isset($post['lName']) && is_array($post['lName'])
            ? array_map('sanitize_text_field', wp_unslash($post['lName']))
            : [];
        $count = min(count($prefixes), count($fNames), count($lNames));
        for ($i = 0; $i < $count; $i++) {
            $fn = trim((string) $fNames[$i]);
            $ln = trim((string) $lNames[$i]);
            $px = trim((string) $prefixes[$i]);
            if ($fn === '' || $ln === '') {
                continue;
            }
            $repo->insert_doctor_row($distributorId, $px, $fn, $ln, $distributorId, $now);
        }

        return ['status' => 'success', 'message' => ''];
    }
}

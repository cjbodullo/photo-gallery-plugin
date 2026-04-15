<?php
/**
 * Plugin Name: Photo Gallery Plugin
 * Description: Winner photo gallery with admin approval workflow.
 * Version: 1.1.0
 * Author: BabyBrands
 * Text Domain: photo-gallery-plugin
 */

if (!defined('ABSPATH')) {
    exit;
}

define('PGP_VERSION', '1.1.0');
define('PGP_PLUGIN_URL', plugin_dir_url(__FILE__));
define('PGP_PLUGIN_PATH', plugin_dir_path(__FILE__));

function pgp_get_winner_table_name()
{
    global $wpdb;

    $canonicalTable = $wpdb->prefix . 'winner_photo_release_submissions';

    $tableCandidates = [
        $canonicalTable,
        'wp_winner_photo_release_submissions',
        'winner_photo_release_submissions',
    ];

    foreach ($tableCandidates as $candidate) {
        $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $candidate));
        if ($exists === $candidate) {
            return $candidate;
        }
    }

    // Not found: create the canonical table (plugin-managed) and return it if creation succeeds.
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $charsetCollate = $wpdb->get_charset_collate();

    // Keep schema aligned with the expected wp_winner_photo_release_submissions structure.
    $sql = "CREATE TABLE {$canonicalTable} (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        first_name VARCHAR(120) NOT NULL,
        last_name VARCHAR(120) NULL,
        email VARCHAR(190) NOT NULL,
        phone VARCHAR(40) NOT NULL,
        address_1 VARCHAR(255) NOT NULL,
        address_2 VARCHAR(255) NULL,
        city VARCHAR(120) NOT NULL,
        province VARCHAR(120) NOT NULL,
        postal_code VARCHAR(25) NULL,
        winner_photo_path VARCHAR(255) NOT NULL,
        status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
        agreed_terms TINYINT(1) NOT NULL DEFAULT 1,
        post_order VARCHAR(25) NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        KEY idx_status (status),
        KEY idx_created_at (created_at)
    ) {$charsetCollate};";

    dbDelta($sql);

    $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $canonicalTable));
    if ($exists === $canonicalTable) {
        return $canonicalTable;
    }

    return '';
}

function pgp_ensure_winner_table_schema()
{
    global $wpdb;

    $tableName = pgp_get_winner_table_name();
    if ($tableName === '') {
        return;
    }

    $statusColumn = $wpdb->get_var("SHOW COLUMNS FROM `{$tableName}` LIKE 'status'");
    if ($statusColumn === null) {
        $wpdb->query(
            "ALTER TABLE `{$tableName}`
             ADD COLUMN status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending' AFTER winner_photo_path"
        );
    }

    $statusIndex = $wpdb->get_var("SHOW INDEX FROM `{$tableName}` WHERE Key_name = 'idx_status'");
    if ($statusIndex === null) {
        $wpdb->query("ALTER TABLE `{$tableName}` ADD INDEX idx_status (status)");
    }
}
add_action('init', 'pgp_ensure_winner_table_schema');

function pgp_enqueue_assets()
{
    wp_enqueue_style(
        'pgp-style',
        PGP_PLUGIN_URL . 'assets/css/photo-gallery-plugin.css',
        [],
        PGP_VERSION
    );

    wp_enqueue_style(
        'glightbox-css',
        'https://cdn.jsdelivr.net/npm/glightbox/dist/css/glightbox.min.css'
    );

    wp_enqueue_script(
        'glightbox-js',
        'https://cdn.jsdelivr.net/npm/glightbox/dist/js/glightbox.min.js',
        [],
        null,
        true
    );

    wp_add_inline_script('glightbox-js', "
        document.addEventListener('DOMContentLoaded', function() {
            const lightbox = GLightbox({
                selector: '.glightbox',
                touchNavigation: true,
                loop: true,
                zoomable: true,
            });
        });
    ");
	
	wp_enqueue_style(
        'swiper-css',
        'https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css'
    );

    wp_enqueue_script(
        'swiper-js',
        'https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js',
        [],
        null,
        true
    );

     wp_add_inline_script('swiper-js', "
        document.addEventListener('DOMContentLoaded', function () {

            if (window.innerWidth <= 768) return;

            new Swiper('.pgp-winner-swiper', {
                slidesPerView: 1,
                spaceBetween: 20,
                pagination: {
                    el: '.swiper-pagination',
                    clickable: true,
                }
            });

        });
    ");

}
add_action('wp_enqueue_scripts', 'pgp_enqueue_assets');

/**
 * Shortcode: [photo_gallery_plugin ids="1,2,3"] (legacy/manual mode)
 */
function pgp_render_gallery_shortcode($atts)
{
    $atts = shortcode_atts(
        [
            'ids' => '',
            'columns' => 3,
        ],
        $atts,
        'photo_gallery_plugin'
    );

    $ids = array_filter(array_map('absint', explode(',', (string) $atts['ids'])));
    $columns = max(1, min(6, absint($atts['columns'])));

    if (empty($ids)) {
        return '<p>No images selected.</p>';
    }

    $output = '<div class="pgp-gallery pgp-cols-' . esc_attr((string) $columns) . '">';

    foreach ($ids as $id) {
        $full = wp_get_attachment_image_url($id, 'large');
        $thumb = wp_get_attachment_image_url($id, 'medium');

        if (!$full || !$thumb) {
            continue;
        }

        $alt = get_post_meta($id, '_wp_attachment_image_alt', true);
        $output .= '<a class="pgp-item" href="' . esc_url($full) . '" target="_blank" rel="noopener">';
        $output .= '<img src="' . esc_url($thumb) . '" alt="' . esc_attr($alt) . '">';
        $output .= '</a>';
    }

    $output .= '</div>';

    return $output;
}
add_shortcode('photo_gallery_plugin', 'pgp_render_gallery_shortcode');

function pgp_build_photo_url($rawPath)
{
    $rawPath = trim((string) $rawPath);
    if ($rawPath === '') {
        return '';
    }

    $uploadInfo = wp_upload_dir();
    $baseUrl = trailingslashit($uploadInfo['baseurl']);

    if (preg_match('#^https?://#i', $rawPath)) {
        return $rawPath;
    }
    if (strpos($rawPath, 'wp-content/uploads/') === 0) {
        // Supports stored path format: wp-content/uploads/YYYY/MM/filename.jpg
        return site_url('/' . ltrim($rawPath, '/'));
    }
    if (strpos($rawPath, 'uploads/') === 0) {
        // Supports stored path format: uploads/YYYY/MM/filename.jpg
        // Primary assumption: path is under wp-content/uploads
        return site_url('/wp-content/' . ltrim($rawPath, '/'));
    }
    if (strpos($rawPath, '/') === 0) {
        return $baseUrl . ltrim($rawPath, '/');
    }

    // Saved as "YYYY/MM/filename.jpg"
    if (preg_match('#^\d{4}/\d{2}/#', $rawPath)) {
        return $baseUrl . '/' . ltrim($rawPath, '/');
    }

    return $baseUrl . '/' . ltrim($rawPath, '/');
}

function pgp_get_winner_release_redirect_url()
{
    $redirectUrl = '';

    if (is_singular()) {
        $postId = get_queried_object_id();
        if ($postId) {
            $redirectUrl = get_permalink($postId);
        }
    }

    if ($redirectUrl === '') {
        $requestUri = isset($_SERVER['REQUEST_URI']) ? wp_unslash($_SERVER['REQUEST_URI']) : '/';
        $redirectUrl = home_url($requestUri);
    }

    return remove_query_arg(['pgp_wpr_status', 'pgp_wpr_message'], $redirectUrl);
}

function pgp_get_winner_release_feedback()
{
    return [
        'status' => isset($_GET['pgp_wpr_status']) ? sanitize_key(wp_unslash($_GET['pgp_wpr_status'])) : '',
        'message' => isset($_GET['pgp_wpr_message']) ? sanitize_text_field(wp_unslash($_GET['pgp_wpr_message'])) : '',
    ];
}

function pgp_build_winner_release_feedback_url($status, $message = '', $redirectUrl = '')
{
    if ($redirectUrl === '') {
        $redirectUrl = pgp_get_winner_release_redirect_url();
    }

    $args = ['pgp_wpr_status' => $status];
    if ($message !== '') {
        $args['pgp_wpr_message'] = $message;
    }

    return add_query_arg($args, $redirectUrl);
}
/**
 * Photo release form code 
 */
function pgp_get_recent_approved_winners($limit = 4)
{
    global $wpdb;

    $tableName = pgp_get_winner_table_name();
    if ($tableName === '') {
        return [];
    }

    $limit = max(1, min(12, absint($limit)));

    $query = "SELECT first_name, city, province, winner_photo_path
              FROM {$tableName}
              WHERE winner_photo_path IS NOT NULL
                AND winner_photo_path <> ''
                AND status = 'approved'
              ORDER BY created_at DESC
              LIMIT %d";

    return $wpdb->get_results($wpdb->prepare($query, 4));
}

function pgp_handle_winner_photo_release_submission()
{
    global $wpdb;

    $redirectUrl = isset($_POST['redirect_to']) ? esc_url_raw(wp_unslash($_POST['redirect_to'])) : home_url('/');
    if ($redirectUrl === '') {
        $redirectUrl = home_url('/');
    }

    if (
        !isset($_POST['pgp_wpr_nonce']) ||
        !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['pgp_wpr_nonce'])), 'pgp_submit_winner_photo_release')
    ) {
        wp_safe_redirect(pgp_build_winner_release_feedback_url('error', 'Security check failed. Please try again.', $redirectUrl));
        exit;
    }

    pgp_ensure_winner_table_schema();
    $tableName = pgp_get_winner_table_name();
    if ($tableName === '') {
        wp_safe_redirect(pgp_build_winner_release_feedback_url('error', 'Winner submissions table is not available.', $redirectUrl));
        exit;
    }

    $firstName = isset($_POST['first_name']) ? sanitize_text_field(wp_unslash($_POST['first_name'])) : '';
    $lastName = isset($_POST['last_name']) ? sanitize_text_field(wp_unslash($_POST['last_name'])) : '';
    $email = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';
    $phone = isset($_POST['phone']) ? sanitize_text_field(wp_unslash($_POST['phone'])) : '';
    $address1 = isset($_POST['address_1']) ? sanitize_text_field(wp_unslash($_POST['address_1'])) : '';
    $address2 = isset($_POST['address_2']) ? sanitize_text_field(wp_unslash($_POST['address_2'])) : '';
    $city = isset($_POST['city']) ? sanitize_text_field(wp_unslash($_POST['city'])) : '';
    $province = isset($_POST['province']) ? sanitize_text_field(wp_unslash($_POST['province'])) : '';
    $postalCode = isset($_POST['postal_code']) ? sanitize_text_field(wp_unslash($_POST['postal_code'])) : '';
    $agreeTerms = isset($_POST['agree_terms']) ? 1 : 0;

    if (
        $firstName === '' ||
        $email === '' ||
        $phone === '' ||
        $address1 === '' ||
        $city === '' ||
        $province === '' ||
        $agreeTerms !== 1
    ) {
        wp_safe_redirect(pgp_build_winner_release_feedback_url('error', 'Please complete all required fields.', $redirectUrl));
        exit;
    }

    if (!is_email($email)) {
        wp_safe_redirect(pgp_build_winner_release_feedback_url('error', 'Please enter a valid email address.', $redirectUrl));
        exit;
    }

    if (!isset($_FILES['winner_photo']) || !is_array($_FILES['winner_photo'])) {
        wp_safe_redirect(pgp_build_winner_release_feedback_url('error', 'Please upload your winner photo.', $redirectUrl));
        exit;
    }

    $photo = $_FILES['winner_photo'];
    if ((int) ($photo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        wp_safe_redirect(pgp_build_winner_release_feedback_url('error', 'Photo upload failed. Please try again.', $redirectUrl));
        exit;
    }

    $maxBytes = 10 * 1024 * 1024;
    if ((int) ($photo['size'] ?? 0) > $maxBytes) {
        wp_safe_redirect(pgp_build_winner_release_feedback_url('error', 'Photo exceeds the 10MB maximum size.', $redirectUrl));
        exit;
    }

    $tmpName = isset($photo['tmp_name']) ? $photo['tmp_name'] : '';
    if ($tmpName === '' || !is_uploaded_file($tmpName)) {
        wp_safe_redirect(pgp_build_winner_release_feedback_url('error', 'Invalid uploaded file.', $redirectUrl));
        exit;
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = $finfo ? finfo_file($finfo, $tmpName) : '';
    if ($finfo) {
        finfo_close($finfo);
    }

    $allowedMimeTypes = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    if (!isset($allowedMimeTypes[$mimeType])) {
        wp_safe_redirect(pgp_build_winner_release_feedback_url('error', 'Only PNG, JPG and WEBP images are allowed.', $redirectUrl));
        exit;
    }

    require_once ABSPATH . 'wp-admin/includes/file.php';

    $uploaded = wp_handle_upload(
        $photo,
        [
            'test_form' => false,
            'mimes' => [
                'jpg|jpeg' => 'image/jpeg',
                'png' => 'image/png',
                'webp' => 'image/webp',
            ],
        ]
    );

    if (!empty($uploaded['error'])) {
        wp_safe_redirect(pgp_build_winner_release_feedback_url('error', $uploaded['error'], $redirectUrl));
        exit;
    }

    $winnerPhotoPath = !empty($uploaded['url']) ? esc_url_raw($uploaded['url']) : '';
    if ($winnerPhotoPath === '') {
        if (!empty($uploaded['file']) && file_exists($uploaded['file'])) {
            wp_delete_file($uploaded['file']);
        }

        wp_safe_redirect(pgp_build_winner_release_feedback_url('error', 'Unable to save the uploaded photo.', $redirectUrl));
        exit;
    }

    $inserted = $wpdb->insert(
        $tableName,
        [
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email,
            'phone' => $phone,
            'address_1' => $address1,
            'address_2' => $address2,
            'city' => $city,
            'province' => $province,
            'postal_code' => $postalCode,
            'winner_photo_path' => $winnerPhotoPath,
            'status' => 'pending',
            'agreed_terms' => $agreeTerms,
            'post_order' => '1',
            'created_at' => current_time('mysql'),
        ],
        [
            '%s',
            '%s',
            '%s',
            '%s',
            '%s',
            '%s',
            '%s',
            '%s',
            '%s',
            '%s',
            '%s',
            '%d',
            '%s',
            '%s',
        ]
    );

    if ($inserted === false) {
        if (!empty($uploaded['file']) && file_exists($uploaded['file'])) {
            wp_delete_file($uploaded['file']);
        }

        wp_safe_redirect(pgp_build_winner_release_feedback_url('error', 'Database save failed. Please try again.', $redirectUrl));
        exit;
    }

    wp_safe_redirect(pgp_build_winner_release_feedback_url('success', '', $redirectUrl));
    exit;
}
add_action('admin_post_nopriv_pgp_submit_winner_photo_release', 'pgp_handle_winner_photo_release_submission');
add_action('admin_post_pgp_submit_winner_photo_release', 'pgp_handle_winner_photo_release_submission');

// [winner_photo_release_form show_winners="0" winner_limit="4"]
// shortcode for the winner photo release form
// show_winners: 1 to show winners, 0 to hide winners
// winner_limit: number of winners to show
// contact_email: email address to contact for questions

function pgp_render_winner_photo_release_form_shortcode($atts)
{
    pgp_ensure_winner_table_schema();

    $atts = shortcode_atts(
        [
            'show_winners' => '1',
            'winner_limit' => 4,
            'contact_email' => 'info@babybrands.com',
        ],
        $atts,
        'winner_photo_release_form'
    );

    $showWinners = !in_array(strtolower((string) $atts['show_winners']), ['0', 'false', 'no'], true);
    $winnerLimit = max(1, min(12, absint($atts['winner_limit'])));
    $contactEmail = sanitize_email((string) $atts['contact_email']);
    if ($contactEmail === '') {
        $contactEmail = 'info@babybrands.com';
    }

    $feedback = pgp_get_winner_release_feedback();
    $redirectUrl = pgp_get_winner_release_redirect_url();
    $winners = $showWinners ? pgp_get_recent_approved_winners($winnerLimit) : [];
    $isSuccess = $feedback['status'] === 'success';
    $errorMessage = $feedback['status'] === 'error' ? $feedback['message'] : '';

    ob_start();
    ?>
    <main class="wpr-page pgp-wpr-shell">
        <div class="container wpr-container">
        <div class="header pgp-wpr-header">
            <h1>WINNER PHOTO RELEASE FORM</h1>
            <?php if (!$isSuccess) : ?>
                <p class="wpr-subtitle">Congratulations on your win! Please complete this form to give us permission to share your joy with our community.</p>
            <?php endif; ?>
        </div>

        <?php if ($isSuccess) : ?>
            <div class="pgp-wpr-thank-you wpr-thank-you">
                <div class="wpr-thank-you-badge">Submission received</div>
                <h2>Thank You!</h2>
                <p>Your photo has been successfully submitted.</p>
                <p>We appreciate you sharing your special moment with us.</p>
                <a class="pgp-wpr-button btn-generate wpr-submit-btn" href="<?php echo esc_url($redirectUrl); ?>">Submit Another Photo</a>
            </div>
        <?php else : ?>
            <div class="pgp-wpr-card wpr-form-wrap">
                <?php if ($errorMessage !== '') : ?>
                    <div class="pgp-wpr-alert pgp-wpr-alert-error"><?php echo esc_html($errorMessage); ?></div>
                <?php endif; ?>

                <?php if (!empty($winners)) : ?>
                    <div class="pgp-wpr-winners winners-section">
                        <h2>Real winners from our community</h2>
                        <div class="pgp-wpr-winners-grid winners-gallery">
                            <?php foreach ($winners as $winner) : ?>
                                <?php
                                $photoUrl = pgp_build_photo_url($winner->winner_photo_path ?? '');
                                if ($photoUrl === '') {
                                    continue;
                                }
                                $winnerName = trim((string) ($winner->first_name ?? ''));
                                $winnerLocation = trim((string) ($winner->city ?? '') . ', ' . (string) ($winner->province ?? ''), ' ,');
                                ?>
                                <article class="pgp-wpr-winner-card gallery-item">
                                    <a href="<?php echo esc_url($photoUrl); ?>" class="pgp-wpr-lightbox glightbox" data-gallery="pgp-wpr-gallery">
                                        <img src="<?php echo esc_url($photoUrl); ?>" alt="<?php echo esc_attr($winnerName . ' winner photo'); ?>">
                                    </a>
                                    <div class="pgp-wpr-winner-meta winner-info">
                                        <h4><?php echo esc_html($winnerName); ?></h4>
                                        <p><?php echo esc_html($winnerLocation); ?></p>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <form id="winner-photo-release-form" class="pgp-wpr-form wpr-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="pgp_submit_winner_photo_release">
                    <input type="hidden" name="redirect_to" value="<?php echo esc_url($redirectUrl); ?>">
                    <?php wp_nonce_field('pgp_submit_winner_photo_release', 'pgp_wpr_nonce'); ?>

                    <div class="pgp-wpr-section form-section">
                        <h3>Personal Information</h3>
                        <div class="pgp-wpr-grid pgp-wpr-grid-two wpr-grid two-col">
                            <div class="pgp-wpr-field wpr-field">
                                <label for="pgp-wpr-first-name">First Name <span>*</span></label>
                                <input class="pgp-wpr-input form-input" type="text" id="pgp-wpr-first-name" name="first_name" required>
                            </div>
                            <div class="pgp-wpr-field wpr-field">
                                <label for="pgp-wpr-last-name">Last Name</label>
                                <input class="pgp-wpr-input form-input" type="text" id="pgp-wpr-last-name" name="last_name">
                            </div>
                            <div class="pgp-wpr-field wpr-field">
                                <label for="pgp-wpr-email">Email Address <span>*</span></label>
                                <input class="pgp-wpr-input form-input" type="email" id="pgp-wpr-email" name="email" required>
                            </div>
                            <div class="pgp-wpr-field wpr-field">
                                <label for="pgp-wpr-phone">Phone Number <span>*</span></label>
                                <input class="pgp-wpr-input pgp-wpr-phone form-input" type="tel" id="pgp-wpr-phone" name="phone" placeholder="(123) 456-7890" required>
                            </div>
                        </div>
                    </div>

                    <div class="pgp-wpr-section form-section">
                        <h3>Address Information</h3>
                        <p class="pgp-wpr-note wpr-note">Your address and phone number are collected for internal records only and will not be displayed publicly.</p>
                        <div class="pgp-wpr-grid pgp-wpr-grid-two wpr-grid two-col">
                            <div class="pgp-wpr-field pgp-wpr-field-full wpr-field full">
                                <label for="pgp-wpr-address-1">Address Line 1 <span>*</span></label>
                                <input class="pgp-wpr-input form-input" type="text" id="pgp-wpr-address-1" name="address_1" required>
                            </div>
                            <div class="pgp-wpr-field wpr-field">
                                <label for="pgp-wpr-city">City <span>*</span></label>
                                <input class="pgp-wpr-input form-input" type="text" id="pgp-wpr-city" name="city" required>
                            </div>
                            <div class="pgp-wpr-field wpr-field">
                                <label for="pgp-wpr-province">Province / Territory <span>*</span></label>
                                <select class="pgp-wpr-input form-input form-select" id="pgp-wpr-province" name="province" required>
                                    <option value="" selected disabled>Select your province</option>
                                    <option value="AB">Alberta</option>
                                    <option value="BC">British Columbia</option>
                                    <option value="MB">Manitoba</option>
                                    <option value="NB">New Brunswick</option>
                                    <option value="NL">Newfoundland and Labrador</option>
                                    <option value="NS">Nova Scotia</option>
                                    <option value="ON">Ontario</option>
                                    <option value="PE">Prince Edward Island</option>
                                    <option value="QC">Quebec</option>
                                    <option value="SK">Saskatchewan</option>
                                    <option value="NT">Northwest Territories</option>
                                    <option value="NU">Nunavut</option>
                                    <option value="YT">Yukon</option>
                                </select>
                            </div>
                            <div class="pgp-wpr-field wpr-field">
                                <label for="pgp-wpr-postal-code">Postal Code</label>
                                <input class="pgp-wpr-input form-input" type="text" id="pgp-wpr-postal-code" name="postal_code">
                            </div>
                            <div class="pgp-wpr-field wpr-field">
                                <label for="pgp-wpr-address-2">Suite / Unit # (Optional)</label>
                                <input class="pgp-wpr-input form-input" type="text" id="pgp-wpr-address-2" name="address_2">
                            </div>
                        </div>
                    </div>

                    <div class="pgp-wpr-section form-section">
                        <h3>Upload Your Photo</h3>
                        <div class="pgp-wpr-field wpr-field">
                            <label for="winner_photo">Winner Photo <span>*</span></label>
                            <label class="pgp-wpr-upload-box wpr-upload-box" id="upload-box">
                                <input type="file" class="pgp-wpr-file-input" id="winner_photo" name="winner_photo" accept=".png,.jpg,.jpeg,.webp" required>
                                <strong>Click to upload or drag and drop</strong>
                                <span>PNG, JPG, JPEG or WEBP (MAX. 10MB)</span>
                                <em class="pgp-wpr-file-name" id="upload-filename">No file selected</em>
                            </label>
                            <p class="pgp-wpr-upload-error wpr-upload-error" id="upload-error" aria-live="polite"></p>
                            <img class="pgp-wpr-upload-preview wpr-upload-preview" id="upload-preview" alt="Uploaded winner photo preview">
                        </div>
                    </div>

                    <div class="pgp-wpr-section form-section">
                        <h3>Terms and Conditions</h3>
                        <div class="pgp-wpr-terms wpr-terms">
                            <label class="pgp-wpr-check wpr-check">
                                <input type="checkbox" name="agree_terms" required>
                                <span>I agree to the photo use terms. <b>*</b></span>
                            </label>
                            <p>By submitting this form, you agree that Baby Brands Gift Club / Samplits may use your first name, last initial, city, and province, and may use your approved photo on their website and social media as a monthly winner.</p>
                            <a href="#" class="pgp-wpr-toggle-terms toggle-terms">View full terms</a>
                            <div class="pgp-wpr-terms-box terms-box" hidden>
                                <p>By checking this box and submitting this form, I grant Baby Brands Gift Club / Samplits and its affiliates the perpetual, royalty-free, worldwide right to use, reproduce, modify, publish, and distribute the submitted photograph(s) in any media format, including but not limited to websites, social media platforms, marketing materials, and promotional content.</p>
                                <p>I understand that my first name, last initial, city, and province may be displayed alongside my photograph. I acknowledge that my full address and telephone number will be kept confidential and used solely for internal record-keeping purposes.</p>
                                <p>I confirm that I am the rightful owner of the photograph or have obtained necessary permissions from the copyright holder. I waive any right to inspect or approve the finished product or any promotional materials in which the photograph may appear.</p>
                                <p>I release Baby Brands Gift Club / Samplits from any claims, liabilities, or damages arising from the use of my photograph and information as described in these terms.</p>
                            </div>
                            <div class="pgp-wpr-privacy wpr-privacy">
                                <strong>Privacy Notice:</strong>
                                Your address and phone number are collected for internal records only and will not be displayed publicly.
                            </div>
                        </div>
                    </div>

                    <div class="pgp-wpr-actions button-group">
                        <button type="submit" class="pgp-wpr-button btn-generate wpr-submit-btn">Submit Photo Release Form</button>
                    </div>
                </form>
            </div>
        <?php endif; ?>

        <p class="pgp-wpr-contact wpr-contact">Questions? Contact us at <a href="mailto:<?php echo esc_attr($contactEmail); ?>"><?php echo esc_html($contactEmail); ?></a></p>
        </div>
    </main>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        if (window.GLightbox && !window.pgpWprLightbox) {
            window.pgpWprLightbox = GLightbox({ selector: '.pgp-wpr-lightbox' });
        }

        document.querySelectorAll('.pgp-wpr-form').forEach(function (form) {
            if (form.dataset.pgpReady === '1') {
                return;
            }
            form.dataset.pgpReady = '1';

            var shell = form.closest('.pgp-wpr-shell');
            var fileInput = form.querySelector('.pgp-wpr-file-input');
            var fileNameLabel = form.querySelector('.pgp-wpr-file-name');
            var uploadBox = form.querySelector('.pgp-wpr-upload-box');
            var uploadError = form.querySelector('.pgp-wpr-upload-error');
            var uploadPreview = form.querySelector('.pgp-wpr-upload-preview');
            var phoneInput = form.querySelector('.pgp-wpr-phone');
            var toggleTermsButton = shell ? shell.querySelector('.pgp-wpr-toggle-terms') : null;
            var termsBox = shell ? shell.querySelector('.pgp-wpr-terms-box') : null;
            var maxFileSize = 10 * 1024 * 1024;
            var allowedTypes = ['image/png', 'image/jpeg', 'image/jpg', 'image/webp'];

            function showUploadError(message) {
                if (uploadError) {
                    uploadError.textContent = message || '';
                }
            }

            function clearPreview() {
                if (!uploadPreview) {
                    return;
                }
                uploadPreview.style.display = 'none';
                uploadPreview.removeAttribute('src');
            }

            function showPreview(file) {
                if (!uploadPreview) {
                    return;
                }

                var reader = new FileReader();
                reader.onload = function (event) {
                    uploadPreview.src = event.target.result;
                    uploadPreview.style.display = 'block';
                };
                reader.readAsDataURL(file);
            }

            function validateFile(file) {
                if (!file) {
                    return 'Please select an image file.';
                }
                if (allowedTypes.indexOf(file.type) === -1) {
                    return 'Only PNG, JPG, JPEG or WEBP files are allowed.';
                }
                if (file.size > maxFileSize) {
                    return 'File is too large. Maximum size is 10MB.';
                }
                return '';
            }

            function setSelectedFile(file) {
                var validationMessage = validateFile(file);

                if (validationMessage) {
                    fileInput.value = '';
                    fileInput.setCustomValidity(validationMessage);
                    fileNameLabel.textContent = 'No file selected';
                    clearPreview();
                    showUploadError(validationMessage);
                    return false;
                }

                fileInput.setCustomValidity('');
                fileNameLabel.textContent = file.name;
                showUploadError('');
                showPreview(file);
                return true;
            }

            if (fileInput && fileNameLabel && uploadBox) {
                fileInput.addEventListener('change', function () {
                    if (!fileInput.files || !fileInput.files.length) {
                        fileNameLabel.textContent = 'No file selected';
                        clearPreview();
                        showUploadError('');
                        return;
                    }

                    setSelectedFile(fileInput.files[0]);
                });

                uploadBox.addEventListener('dragover', function (event) {
                    event.preventDefault();
                    uploadBox.classList.add('is-dragging');
                });

                uploadBox.addEventListener('dragleave', function () {
                    uploadBox.classList.remove('is-dragging');
                });

                uploadBox.addEventListener('drop', function (event) {
                    event.preventDefault();
                    uploadBox.classList.remove('is-dragging');

                    if (!event.dataTransfer || !event.dataTransfer.files || !event.dataTransfer.files.length) {
                        return;
                    }

                    var droppedFile = event.dataTransfer.files[0];
                    if (!setSelectedFile(droppedFile)) {
                        return;
                    }

                    var dataTransfer = new DataTransfer();
                    dataTransfer.items.add(droppedFile);
                    fileInput.files = dataTransfer.files;
                });
            }

            if (phoneInput) {
                phoneInput.addEventListener('input', function () {
                    var digits = phoneInput.value.replace(/\D/g, '').slice(0, 10);

                    if (digits.length <= 3) {
                        phoneInput.value = digits;
                    } else if (digits.length <= 6) {
                        phoneInput.value = '(' + digits.slice(0, 3) + ') ' + digits.slice(3);
                    } else {
                        phoneInput.value = '(' + digits.slice(0, 3) + ') ' + digits.slice(3, 6) + '-' + digits.slice(6);
                    }
                });
            }

            if (toggleTermsButton && termsBox) {
                toggleTermsButton.addEventListener('click', function (event) {
                    event.preventDefault();
                    var isHidden = termsBox.hasAttribute('hidden');
                    if (isHidden) {
                        termsBox.removeAttribute('hidden');
                        toggleTermsButton.textContent = 'Hide full terms';
                    } else {
                        termsBox.setAttribute('hidden', 'hidden');
                        toggleTermsButton.textContent = 'View full terms';
                    }
                });
            }

            form.addEventListener('submit', function () {
                if (!fileInput || !fileInput.files || !fileInput.files.length) {
                    return;
                }

                var validationMessage = validateFile(fileInput.files[0]);
                fileInput.setCustomValidity(validationMessage);
                showUploadError(validationMessage);
            });
        });
    });
    </script>
    <?php

    return ob_get_clean();
}
add_shortcode('winner_photo_release_form', 'pgp_render_winner_photo_release_form_shortcode');
add_shortcode('winnerphotorelease', 'pgp_render_winner_photo_release_form_shortcode');

function pgp_render_winner_photo_gallery($atts)
{
    global $wpdb;
    pgp_ensure_winner_table_schema();

    $onepage = 12;

    $atts = shortcode_atts(
        [
            'limit' => 24,
            'columns' => 3,
            'order' => 'DESC',
        ],
        $atts,
        'winner_photo_gallery'
    );

    $limit = max(0, min(60, absint($atts['limit'])));
    $columns = max(1, min(6, absint($atts['columns'])));
    $order = strtoupper((string) $atts['order']) === 'ASC' ? 'ASC' : 'DESC';

    $tableName = pgp_get_winner_table_name();
    if ($tableName === '') {
        return '<p>Winner submissions table not found.</p>';
    }

     if($limit < 1){
       $query = "SELECT id, first_name, city, province, winner_photo_path, created_at
              FROM {$tableName}
              WHERE winner_photo_path IS NOT NULL
                AND winner_photo_path <> ''
                AND status = 'approved'
              ORDER BY created_at {$order}";                         
    }else{
        $query = "SELECT id, first_name, city, province, winner_photo_path, created_at
              FROM {$tableName}
              WHERE winner_photo_path IS NOT NULL
                AND winner_photo_path <> ''
                AND status = 'approved'
              ORDER BY created_at {$order}
              LIMIT %d";
    }

    $rows = $wpdb->get_results($wpdb->prepare($query, $limit));

    if (empty($rows)) {
        return '<div class="pgp-border-warning">
                    <span class="pgp-warning-icon">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                            <path d="M12 9v4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                            <path d="M12 17h.01" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                            <path d="M10.29 3.86l-8.3 14.29A2 2 0 0 0 3.7 21h16.6a2 2 0 0 0 1.71-2.85l-8.3-14.29a2 2 0 0 0-3.42 0z" stroke="currentColor" stroke-width="2"/>
                        </svg>
                    </span>
                    <span>Winner photos are currently under review.</span>
                </div>';
    }

    $output  = '<div class="pgp-winner-wrapper">';
    $output .= '<div class="swiper pgp-winner-swiper">';
    $output .= '<div class="swiper-wrapper" style="padding-bottom: 28px;">';

    $count = 0;

    foreach ($rows as $row) {

        $firstName = trim((string) $row->first_name);
        $city = trim((string) $row->city);
        $province = trim((string) $row->province);
        $rawPath = trim((string) $row->winner_photo_path);

        if ($rawPath === '') continue;

        $photoUrl = pgp_build_photo_url($rawPath);
        if ($photoUrl === '') continue;

        $location = trim($city . (strlen($province) ? ', ' . $province : ''));
        $altText = $firstName . ' Winner Photo';

        // START SLIDE (12 items)
        if ($count % $onepage === 0) {
            $output .= '<div class="swiper-slide"><div class="pgp-winner-grid">';
        }

        $output .= '<article class="pgp-winner-card">';
        $output .= '<a href="' . esc_url($photoUrl) . '" class="glightbox pgp-winner-link"
                        data-gallery="pgp-winners"
                        data-type="image">';

        $output .= '<div class="pgp-winner-image-wrap">';
        $output .= '<img class="pgp-winner-image" src="' . esc_url($photoUrl) . '" alt="' . esc_attr($altText) . '">';
        $output .= '</div>';

        $output .= '</a>';

        $output .= '<div class="pgp-winner-meta">';
        $output .= '<h3 class="pgp-winner-name">' . esc_html($firstName) . '</h3>';
        $output .= '<p class="pgp-winner-location">' . esc_html($location) . '</p>';
        $output .= '</div>';
        $output .= '</article>';

        $count++;

        if ($count % $onepage === 0) {
            $output .= '</div></div>';
        }
    }

    if ($count % $onepage !== 0) {
        $output .= '</div></div>';
    }

    $output .= '</div>'; // swiper-wrapper
    $output .= '<div class="swiper-dot swiper-pagination"></div>';
    $output .= '</div>'; // swiper
    $output .= '</div>'; // wrapper

    return $output;
}
add_shortcode('winner_photo_gallery', 'pgp_render_winner_photo_gallery');

function pgp_register_admin_menu()
{
    add_menu_page(
        'Winners Gallery',
        'Winners Gallery',
        'manage_options',
        'pgp-winner-gallery',
        'pgp_render_admin_page',
        'dashicons-format-gallery',
        58
    );
}
add_action('admin_menu', 'pgp_register_admin_menu');

function pgp_handle_admin_actions()
{
    if (!is_admin() || !current_user_can('manage_options')) {
        return;
    }

    if (!isset($_GET['page']) || $_GET['page'] !== 'pgp-winner-gallery') {
        return;
    }

    if (!isset($_GET['pgp_action']) || !isset($_GET['submission_id']) || !isset($_GET['_wpnonce'])) {
        return;
    }

    $action = sanitize_key((string) $_GET['pgp_action']);
    $submissionId = absint($_GET['submission_id']);

    if (!wp_verify_nonce((string) $_GET['_wpnonce'], 'pgp_update_status_' . $submissionId)) {
        wp_die('Security check failed.');
    }

    if (!in_array($action, ['approve', 'reject', 'pending','delete'], true)) {
        wp_die('Invalid action.');
    }

    global $wpdb;
    pgp_ensure_winner_table_schema();
    $tableName = pgp_get_winner_table_name();
    if ($tableName === '') {
        wp_die('Winner submissions table not found.');
    }

    if ($action === 'delete'){
         $wpdb->delete(
            $tableName,
            ['id' => $submissionId],
            ['%d']
        );
        wp_safe_redirect(admin_url('admin.php?page=pgp-winner-gallery&updated=1'));
        exit;
    }

    $status = $action === 'approve' ? 'approved' : ($action === 'reject' ? 'rejected' : 'pending');
    $wpdb->update(
        $tableName,
        ['status' => $status],
        ['id' => $submissionId],
        ['%s'],
        ['%d']
    );

    wp_safe_redirect(admin_url('admin.php?page=pgp-winner-gallery&updated=1'));
    exit;
}
add_action('admin_init', 'pgp_handle_admin_actions');


function pgp_update_photo()
{
    global $wpdb;

    $table = pgp_get_winner_table_name();

    if (empty($_FILES['photo']['name'])) {
        wp_send_json_error();
    }

    require_once(ABSPATH . 'wp-admin/includes/file.php');

    $uploaded = wp_handle_upload($_FILES['photo'], ['test_form' => false]);

    if (isset($uploaded['error'])) {
        wp_send_json_error();
    }

    $file_url = $uploaded['url'];

    $wpdb->update(
        $table,
        ['winner_photo_path' => $file_url],
        ['id' => intval($_POST['submission_id'])]
    );

    wp_send_json_success(['url' => $file_url]);
}
add_action('wp_ajax_pgp_update_photo', 'pgp_update_photo');

function pgp_render_admin_page()
{
    if (!current_user_can('manage_options')) {
        return;
    }

    global $wpdb;
    pgp_ensure_winner_table_schema();
    $tableName = pgp_get_winner_table_name();

    echo '<div class="wrap"><h1>Winners Gallery Approvals</h1>';
    echo '<p>Approve or reject winner photo.</p>';

    if (isset($_GET['updated'])) {
        echo '<div class="notice notice-success is-dismissible"><p>Status updated.</p></div>';
    }

    if ($tableName === '') {
        echo '<div class="notice notice-error"><p>Winner submissions table not found.</p></div></div>';
        return;
    }

    // --- Pagination setup ---
    $per_page = 10; // submissions per page
    $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
    $offset = ($current_page - 1) * $per_page;

    // Get total rows
    $total_rows = $wpdb->get_var("SELECT COUNT(*) FROM `{$tableName}`");
    $total_pages = ceil($total_rows / $per_page);

    // Fetch current page rows
    $rows = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT * FROM `{$tableName}` ORDER BY created_at DESC LIMIT %d OFFSET %d",
            $per_page,
            $offset
        )
    );

    if (empty($rows)) {
        echo '<p>No submissions found.</p></div>';
        return;
    }

    echo '<table class="widefat striped">';
    echo '<thead><tr>';
    echo '<th>Photo</th><th>Name</th><th>Location</th><th>Status</th><th>Submitted</th><th>Actions</th>';
    echo '</tr></thead><tbody>';
    $hiddenDetailsHtml = '';

    $fieldNames = [
        'first_name' => 'First Name',
        'last_name' => 'Last Name',
        'email' => 'Email',
        'phone' => 'Phone Number',
        'address_1' => 'Address 1',
        'address_2' => 'Address 2',
        'city' => 'City',
        'province' => 'Province',
        'postal_code' => 'Postal Code',
        'winner_photo_path' => 'Photo File',
        'status' => 'Status',
        'agreed_terms' => 'Agree Terms',
        'created_at' => 'Created At',
    ];

    foreach ($rows as $key=>$row) {
        $photoUrl = pgp_build_photo_url($row->winner_photo_path ?? '');
        $name = trim((string) ($row->first_name ?? '') . ' ' . (string) ($row->last_name ?? ''));
        $location = trim((string) ($row->city ?? '') . ', ' . (string) ($row->province ?? ''), ' ,');
        $status = (string) ($row->status ?? 'pending');
        $id = (int) $row->id;

        $approveUrl = wp_nonce_url(admin_url('admin.php?page=pgp-winner-gallery&pgp_action=approve&submission_id=' . $id), 'pgp_update_status_' . $id);
        $rejectUrl  = wp_nonce_url(admin_url('admin.php?page=pgp-winner-gallery&pgp_action=reject&submission_id=' . $id), 'pgp_update_status_' . $id);
        $pendingUrl = wp_nonce_url(admin_url('admin.php?page=pgp-winner-gallery&pgp_action=pending&submission_id=' . $id), 'pgp_update_status_' . $id);
        $deleteUrl  = wp_nonce_url(admin_url('admin.php?page=pgp-winner-gallery&pgp_action=delete&submission_id=' . $id), 'pgp_update_status_' . $id);

        $detailRowsHtml = '';
        foreach ((array) $row as $field => $value) {
            if($field === 'agreed_terms') $value = $value==1?'Agree':'Disagree';
            if(isset($fieldNames[$field])){
                $detailRowsHtml .= '<tr>';
                $detailRowsHtml .= '<th style="width:220px;">' . esc_html((string) $fieldNames[$field]) . '</th>';
                $detailRowsHtml .= '<td>' . esc_html((string) $value) . '</td>';
                $detailRowsHtml .= '</tr>';
            }
        }

        echo '<tr>';
        echo '<td style="vertical-align: middle;">';
        if ($photoUrl) {
            echo '<img src="' . esc_url($photoUrl) . '" alt="" style="width:70px;height:70px;object-fit:cover;border-radius:8px;">';
        } else {
            echo 'N/A';
        }
        echo '</td>';
        echo '<td style="vertical-align: middle;">' . esc_html($name) . '</td>';
        echo '<td style="vertical-align: middle;">' . esc_html($location) . '</td>';
        echo '<td style="vertical-align: middle;"><strong>' . esc_html(ucfirst($status)) . '</strong></td>';
        echo '<td style="vertical-align: middle;">' . esc_html((string) $row->created_at) . '</td>';
        echo '<td style="vertical-align: middle;">';
        echo '<button type="button" class="button pgp-view-details-btn" data-target="pgp-detail-' . esc_attr((string) $id) . '">View Details</button> ';
        echo '<button type="button" 
        class="button button-secondary pgp-edit-btn" 
        data-id="' . esc_attr($id) . '" 
        data-photo="' . esc_url($photoUrl) . '">
        Edit
      </button> ';
        echo '<a class="button button-primary" href="' . esc_url($approveUrl) . '">Approve</a> ';
        echo '<a class="button" href="' . esc_url($rejectUrl) . '">Reject</a> ';
        echo '<a class="button" href="' . esc_url($pendingUrl) . '">Set Pending</a> ';
        echo '<a class="button button-secondary" href="' . esc_url($deleteUrl) . '">Delete</a>';
        echo '</td>';
        echo '</tr>';

        $hiddenDetailsHtml .= '<div id="pgp-detail-' . esc_attr((string) $id) . '" style="display:none;">';
        $hiddenDetailsHtml .= '<h2 style="margin-top:0;">Submission Details #' . esc_html((string) $id) . '</h2>';
        if ($photoUrl) {
            $hiddenDetailsHtml .= '<p style="margin-bottom:14px;">';
            $hiddenDetailsHtml .= '<img src="' . esc_url($photoUrl) . '" alt="" style="max-width:240px;height:auto;border-radius:10px;display:block;">';
            $hiddenDetailsHtml .= '</p>';
        }
        $hiddenDetailsHtml .= '<table class="widefat striped" style="margin-top:0;"><tbody>' . $detailRowsHtml . '</tbody></table>';
        $hiddenDetailsHtml .= '</div>';
    }

    echo '</tbody></table>';

    // --- Default WP pagination ---
    if ($total_pages > 1) {
        $pagination = paginate_links([
            'base'      => add_query_arg('paged','%#%'),
            'format'    => '',
            'current'   => max(1, $current_page),
            'total'     => $total_pages,
            'prev_text' => '‹',
            'next_text' => '›',
            'type'      => 'array', // IMPORTANT: change to array
        ]);
        echo '<div class="tablenav"><div class="tablenav-pages">';
        echo '<span class="pagination-links">';
        // First page
        if ($current_page > 1) {
            echo '<a class="first-page button" href="'.esc_url(add_query_arg('paged', 1)).'">«</a>';
        } else {
            echo '<span class="tablenav-pages-navspan button disabled">«</span>';
        }
        // Page numbers
        if (!empty($pagination)) {
            foreach ($pagination as $page) {
                $page_num = (int) wp_strip_all_tags($page);
                $btn = ($current_page == $page_num) ? 'button-primary' : 'button';
                echo str_replace('page-numbers', $btn, $page);
            }
        }
        // Last page
        if ($current_page < $total_pages) {
            echo '<a class="last-page button" href="'.esc_url(add_query_arg('paged', $total_pages)).'">»</a>';
        } else {
            echo '<span class="tablenav-pages-navspan button disabled">»</span>';
        }
        echo '</span>';
        echo '</div></div>';
    }

    echo $hiddenDetailsHtml;
    echo '</div>';

    // --- Modal JS ---
    echo '<div id="pgp-admin-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.48);z-index:99999;padding:24px;overflow:auto;">';
    echo '<div style="max-width:900px;margin:32px auto;background:#fff;border-radius:12px;padding:20px;position:relative;">';
    echo '<button type="button" id="pgp-admin-modal-close" class="button" style="position:absolute;right:14px;top:14px;">Close</button>';
    echo '<div id="pgp-admin-modal-content"></div>';
    echo '</div></div>';
    echo '<div id="pgp-edit-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:99999;">
            <div style="max-width:500px;margin:60px auto;background:#fff;padding:20px;border-radius:12px;position:relative;">
                <button type="button" id="pgp-edit-close" class="button" style="position:absolute;top:10px;right:10px;">Close</button>

                <h2>Edit Photo</h2>

                <div style="text-align:center;margin-bottom:15px;">
                    <img id="pgp-edit-preview" src="" style="max-width:200px;border-radius:8px;">
                </div>

                <input type="file" id="pgp-edit-file" accept="image/*">

                <div style="margin-top:15px;">
                    <a id="pgp-download-btn" class="button" href="#" download>Download</a>
                    <button id="pgp-save-btn" class="button button-primary">Save</button>
                </div>

                <input type="hidden" id="pgp-edit-id">
            </div>
        </div>';
    echo '<script>
    (function () {
        var modal = document.getElementById("pgp-admin-modal");
        var closeBtn = document.getElementById("pgp-admin-modal-close");
        var content = document.getElementById("pgp-admin-modal-content");
        if (!modal || !closeBtn || !content) return;

        function closeModal() {
            modal.style.display = "none";
            content.innerHTML = "";
        }

        document.addEventListener("click", function (event) {
            var trigger = event.target.closest(".pgp-view-details-btn");
            if (!trigger) return;
            var targetId = trigger.getAttribute("data-target");
            var target = targetId ? document.getElementById(targetId) : null;
            if (!target) return;
            content.innerHTML = target.innerHTML;
            modal.style.display = "block";
        });

        closeBtn.addEventListener("click", closeModal);
        modal.addEventListener("click", function (event) {
            if (event.target === modal) closeModal();
        });
        document.addEventListener("keydown", function (event) {
            if (event.key === "Escape") closeModal();
        });
    })();

    (function () {

    const editModal = document.getElementById("pgp-edit-modal");
    const preview = document.getElementById("pgp-edit-preview");
    const fileInput = document.getElementById("pgp-edit-file");
    const saveBtn = document.getElementById("pgp-save-btn");
    const closeBtn = document.getElementById("pgp-edit-close");
    const downloadBtn = document.getElementById("pgp-download-btn");
    const idInput = document.getElementById("pgp-edit-id");

    // OPEN MODAL
    document.addEventListener("click", function (e) {
        const btn = e.target.closest(".pgp-edit-btn");
        if (!btn) return;

        const id = btn.dataset.id;
        const photo = btn.dataset.photo;

        idInput.value = id;
        preview.src = photo;
        downloadBtn.href = photo;

        editModal.style.display = "block";
    });

    // PREVIEW NEW IMAGE
    fileInput.addEventListener("change", function () {
        const file = this.files[0];
        if (!file) return;

        preview.src = URL.createObjectURL(file);
    });

    // SAVE VIA AJAX
    saveBtn.addEventListener("click", function () {
        const file = fileInput.files[0];
        const id = idInput.value;

        if (!file) {
            alert("Please select an image.");
            return;
        }

        const formData = new FormData();
        formData.append("action", "pgp_update_photo");
        formData.append("submission_id", id);
        formData.append("photo", file);

        fetch(ajaxurl, {
            method: "POST",
            body: formData
        })
        .then(res => res.json())
        .then(res => {
            if (res.success) {
                alert("Updated successfully!");
                location.reload();
            } else {
                alert("Error updating.");
            }
        });
    });

    // CLOSE
    closeBtn.onclick = () => editModal.style.display = "none";
    editModal.onclick = (e) => {
        if (e.target === editModal) editModal.style.display = "none";
    };

    })();
    </script>';
}
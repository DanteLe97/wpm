<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Check if WordPress is loaded
if (!function_exists('add_action')) {
    die('This file cannot be accessed directly.');
}

class Mac_Authorize_Policy_And_Terms_Settings
{
    private static $instance = null;
    private $business_name;

    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        $this->init_hooks();
        $this->business_name = $this->get_business_name();
    }

    private function init_hooks()
    {
        // Only add admin hooks if we're in admin
        if (is_admin()) {
            add_action('admin_menu', array($this, 'add_admin_menu'));
        }

        // Add frontend hooks
        add_shortcode('mac_salon_name', array($this, 'mac_salon_name_shortcode'));
        add_shortcode('mac_salon_address', array($this, 'mac_salon_address_shortcode'));
        add_shortcode('mac_salon_phone', array($this, 'mac_salon_phone_shortcode'));
        add_shortcode('mac_salon_email', array($this, 'mac_salon_email_shortcode'));
        add_shortcode('mac_salon_city', array($this, 'mac_salon_city_shortcode'));
        add_shortcode('mac_salon_state', array($this, 'mac_salon_state_shortcode'));
        add_shortcode('mac_salon_url', array($this, 'mac_salon_url_shortcode'));

        add_shortcode('mac_authorize_policy', array($this, 'mac_authorize_policy_shortcode'));
    }

    public function add_admin_menu()
    {
        add_options_page(
            'Authorize Policy and Terms Settings',
            'Authorize Policy and Terms Settings',
            'manage_options',
            'mac-authorize-policy-and-terms-settings',
            array($this, 'render_mac_authorize_policy_and_terms_settings_page')
        );
    }

    public function render_mac_authorize_policy_and_terms_settings_page()
    {
        // Xử lý form submit
        if (isset($_POST['submit'])) {
            $city = sanitize_text_field($_POST['mac_city']);
            $state = sanitize_text_field($_POST['mac_state']);

            update_option('mac_city', $city);
            update_option('mac_state', $state);

            echo '<div class="notice notice-success"><p>Settings saved successfully!</p></div>';
        }

        // Lấy giá trị hiện tại
        $current_city = get_option('mac_city', '');
        $current_state = get_option('mac_state', '');

?>
        <div class="wrap">
            <h1>Authorize Policy and Terms Settings</h1>
            <form method="post" action="">
                <style>
                    .privacy-policy {
                        font-size: 18px;
                        text-decoration: none;
                        color: #222;
                    }

                    .form-table {
                        width: 576px;
                        margin-bottom: 50px;
                    }

                    .form-table th {
                        width: 150px;
                    }

                    .form-table input[type="text"] {
                        width: 300px;
                    }
                </style>

                <table class="form-table">
                    <tr>
                        <th>Name</th>
                        <th>Value</th>
                        <th>Shortcode</th>
                    </tr>
                    <tr>
                        <td>Business Name</td>
                        <td>
                            <?= $this->business_name ?> <a href="<?= esc_url(admin_url('options-general.php')); ?>">edit</a>
                        </td>
                        <td>
                            <strong>[mac_salon_name]</strong>
                        </td>
                    </tr>
                    <?php
                    $business_info = $this->get_business_info();
                    ?>
                    <tr>
                        <td>Business Phone</td>
                        <td>
                            <?= !empty($business_info['business_phone']) ? $business_info['business_phone'] : 'Chưa có thông tin' ?>
                        </td>
                        <td>
                            <strong>[mac_salon_phone]</strong>
                        </td>
                    </tr>
                    <tr>
                        <td>Address</td>
                        <td>
                            <?= !empty($business_info['address']) ? $business_info['address'] : 'Chưa có thông tin' ?>
                        </td>
                        <td>
                            <strong>[mac_salon_address]</strong>
                        </td>
                    </tr>
                    <tr>
                        <td>Email</td>
                        <td>
                            <?= !empty($business_info['gmail']) ? $business_info['gmail'] : 'Chưa có thông tin' ?>
                        </td>
                        <td>
                            <strong>[mac_salon_email]</strong>
                        </td>
                    </tr>
                    <tr>
                        <td>City</td>
                        <td>
                            <input type="text" name="mac_city" value="<?= esc_attr($current_city) ?>" placeholder="Enter city name">
                        </td>
                        <td>
                            <strong>[mac_salon_city]</strong>
                        </td>
                    </tr>
                    <tr>
                        <td>State (no abbreviation)</td>
                        <td>
                            <input type="text" name="mac_state" value="<?= esc_attr($current_state) ?>" placeholder="Enter state name">
                        </td>
                        <td>
                            <strong>[mac_salon_state]</strong>
                        </td>
                    </tr>
                    <tr>
                        <td>Website URL</td>
                        <td>
                            <?= get_site_url() ?>
                        </td>
                        <td>
                            <strong>[mac_salon_url]</strong>
                        </td>
                    </tr>
                </table>
                <input type="submit" name="submit" value="Save" class="button button-primary">
            </form>
            <h3>Use shortcode <strong>[mac_authorize_policy]</strong> anywhere you like for Authorize Policy.</h3>
        </div>
    <?php
    }
    private function get_business_name()
    {
        return !empty(get_bloginfo('name')) ? get_bloginfo('name') : 'MAC Salon Name';
    }
    private function get_business_info()
    {
        global $wpdb;

        // Lấy dữ liệu từ wp_options với option_name = 'web-info'
        $web_info_option = get_option('web-info');

        if ($web_info_option && is_array($web_info_option)) {
            // Lấy các giá trị từ array
            $business_phone = isset($web_info_option['business_phone']) ? $web_info_option['business_phone'] : '';
            $address = isset($web_info_option['address']) ? $web_info_option['address'] : '';
            $gmail = isset($web_info_option['gmail']) ? $web_info_option['gmail'] : '';

            return array(
                'business_phone' => $business_phone,
                'address' => $address,
                'gmail' => $gmail
            );
        }

        // Trả về giá trị mặc định nếu không tìm thấy
        return array(
            'business_phone' => '',
            'address' => 'MAC Salon Address',
            'gmail' => ''
        );
    }
    public function mac_salon_name_shortcode()
    {
        ob_start();
    ?>
        <span class="mac-salon-name" style="font-weight: bold;"> <?php echo $this->get_business_name(); ?></span>
    <?php
        return ob_get_clean();
    }
    public function mac_salon_address_shortcode()
    {
        ob_start();
        $business_info = $this->get_business_info();
    ?>
        <span class="mac-salon-address"> <?php echo $business_info['address']; ?></span>
    <?php
        return ob_get_clean();
    }
    public function mac_salon_phone_shortcode()
    {
        ob_start();
        $business_info = $this->get_business_info();
    ?>
        <span class="mac-salon-phone"> <?php echo $business_info['business_phone']; ?></span>
    <?php
        return ob_get_clean();
    }
    public function mac_salon_email_shortcode()
    {
        ob_start();
        $business_info = $this->get_business_info();
    ?>
        <span class="mac-salon-email"> <?php echo $business_info['gmail']; ?></span>
    <?php
        return ob_get_clean();
    }
    public function mac_salon_city_shortcode()
    {
        ob_start();
        $city = get_option('mac_city', '');
    ?>
        <span class="mac-salon-city"> <?php echo $city; ?></span>
    <?php
        return ob_get_clean();
    }
    public function mac_salon_state_shortcode()
    {
        ob_start();
        $state = get_option('mac_state', '');
    ?>
        <span class="mac-salon-state"> <?php echo $state; ?></span>
    <?php
        return ob_get_clean();
    }
    public function mac_salon_url_shortcode()
    {
        ob_start();
        $site_url = get_site_url();
    ?>
        <span class="mac-salon-url"> <?php echo $site_url; ?></span>
    <?php
        return ob_get_clean();
    }
    public function mac_authorize_policy_shortcode()
    {
        ob_start();
    ?>
    <div class="mac-authorize-policy">
    <div class="divider"> <span></span></div>
        <!-- <p>[mac_salon_name] Privacy Policy</p> -->
        <p>Last Updated: July 22, 2025</p>
        <h2>1. Introduction</h2>
        <p>Welcome to [mac_salon_name]! We are committed to protecting the privacy of our clients and website visitors. This Privacy Policy explains how we collect, use, disclose, and safeguard your information when you visit our salon, use our services, or access our website at [mac_salon_url].</p>
        <h2>2. Information We Collect</h2>
        <p>We may collect personal information from you in various ways, including when you book an appointment, visit our salon, sign up for newsletters, participate in promotions, or interact with us online. The types of information we may collect include:</p>
        <p> * Contact Information: Name, address, email address, phone number.</p>
        <p> * Appointment Details: Date and time of service, services requested, service history.</p>
        <p> * Payment Information: (Though we recommend using secure third-party payment processors that handle sensitive credit card data directly, you should mention if you store any partial payment information like transaction IDs).</p>
        <p> * Health Information (Optional/Sensitive): Any allergy information or medical conditions relevant to nail services (e.g., allergies to certain products, nail conditions). This should be collected with explicit consent and stored securely.</p>
        <p> * Preferences: Preferred nail technicians, service preferences.</p>
        <p> * Website Usage Data (if applicable): IP address, browser type, operating system, referring URLs, pages viewed, and access times.</p>
        <h2>3.  How We Use Your Information</h2>
        <p>We use the information we collect for various purposes, including:</p>
        <p> * Providing Services: To schedule and manage appointments, perform nail services, and personalize your salon experience.</p>
        <p> * Communication: To send appointment confirmations, reminders, updates, and respond to your inquiries.</p>
        <p> * Marketing and Promotions: To send you information about new services, special offers, and promotions that may be of interest to you (you can opt-out at any time).</p>
        <p> * Improving Our Services: To understand client preferences and improve our service offerings, website, and overall client experience.</p>
        <p> * Internal Operations: For billing, accounting, and administrative purposes.</p>
        <p> * Safety and Health: To ensure the safety of our services, especially when collecting health-related information.</p>
        <h2>4.  How We Share Your Information</h2>
        <p>We do not sell, trade, or otherwise transfer your personally identifiable information to outside parties without your consent, except in the following limited circumstances:</p>
        <p> * Service Providers: We may share information with trusted third-party service providers who assist us in operating our salon and website (e.g., online booking systems, payment processors, email marketing services). These parties are obligated to keep your information confidential and use it only for the purposes for which we disclose it to them.</p>
        <p> * Legal Requirements: We may disclose your information if required to do so by law or in response to valid requests by public authorities (e.g., a court order or government agency).</p>
        <p> * Business Transfers: In the event of a merger, acquisition, or sale of all or a portion of our assets, your information may be transferred as part of that transaction.</p>
        <h2>5.  Data Security</h2>
        <p>We implement a variety of security measures to maintain the safety of your personal information when you place an order or enter, submit, or access your personal information. These measures may include data encryption, secure servers, and restricted access to personal data. However, no method of transmission over the Internet or method of electronic storage is 100% secure.</p>
        <h2>6.  Your Choices and Rights</h2>
        <p>You have certain rights regarding your personal information:</p>
        <p> * Access and Correction: You have the right to request access to the personal information we hold about you and to request corrections of any inaccuracies.</p>
        <p> * Opt-Out: You can opt-out of receiving marketing communications from us at any time by following the unsubscribe instructions included in our emails or by contacting us directly.</p>
        <p>* Deletion: You may request the deletion of your personal information, subject to certain legal obligations to retain data.</p>
        <p> * Withdraw Consent: Where we rely on your consent to process your personal information, you have the right to withdraw that consent at any time.</p>
        <p>To exercise any of these rights, please contact us using the information provided below.</p>
        <h2>7.  Cookies and Tracking Technologies</h2>
        <p>Our website may use "cookies" and similar tracking technologies to enhance your experience. Cookies are small data files placed on your device that help us remember your preferences, understand how you interact with our site, and improve our services. You can set your browser to refuse all or some browser cookies, or to alert you when websites set or access cookies. If you disable or refuse cookies, please note that some parts of our website may become inaccessible or not function properly.</p>
        <h2>8.  Third-Party Links</h2>
        <p>Our website may contain links to third-party websites that are not operated by us. We have no control over and assume no responsibility for the content, privacy policies, or practices of any third-party sites or services. We strongly advise you to review the Privacy Policy of every site you visit.</p>
        <h2>9.  Children's Privacy</h2>
        <p>Our services are not directed to individuals under the age of 13. We do not knowingly collect personally identifiable information from children under 13. If you are a parent or guardian and you are aware that your child has provided us with personal information, please contact us. If we become aware that we have collected personal information from a child under the age of 13 without verification of parental consent, we take steps to remove that information from our servers.</p>
        <h2>10.  Changes to This Privacy Policy</h2>
        <p>We may update our Privacy Policy from time to time. We will notify you of any changes by posting the new Privacy Policy on this page and updating the "Last Updated" date at the top of this policy. You are advised to review this Privacy Policy periodically for any changes.</p>
        <h2>11.  Contact Us</h2>
        <p>If you have any questions or concerns about this Privacy Policy or our data practices, please contact us at:</p>
        <p>[mac_salon_name]</p>
        <p>[mac_salon_address]</p>
        <p>[mac_salon_phone]</p>
        <p>[mac_salon_email]</p>
    </div>
    <style>
        .mac-authorize-policy .divider {
            display: block;
            height: 2px;
            width: 100%;
            background-color: #cbcbcb;
            margin-bottom: 70px;
        }
        @media (max-width: 767px) {
            .mac-authorize-policy .divider {
                margin-bottom: 40px;
            }
        }
    </style>
    

<?php
        return ob_get_clean();
    }
public function mac_authorize_terms_and_conditions_shortcode()
    {
        ob_start();

    ?>
    <div class="mac-terms-and-conditions">
        <h2>[mac_salon_name] Terms of Service</h2>
        <div>  Last Updated: July 22, 2025</div>
        <h2>  1. Introduction</h2>
        <div>  Welcome to [mac_salon_name]! These Terms of Service ("Terms") govern your use of the services provided by [mac_salon_name] ("we," "us," or "our") at our salon located at [mac_salon_address], and any related online platforms, including our website at [mac_salon_url]. By booking an appointment, receiving services, or otherwise interacting with our salon, you agree to comply with and be bound by these Terms. If you do not agree to these Terms, please do not use our services.</div>
        <h2>  2. Acceptance of Terms</h2>
        <div>  By accessing or using our services, you acknowledge that you have read, understood, and agree to be bound by these Terms, as well as our Privacy Policy.</div>
        <h2>  3. Services Offered</h2>
        <div>  [mac_salon_name] provides a range of professional nail care services, including but not limited to manicures, pedicures, nail enhancements (e.g., gel, acrylic), nail art, and nail repair.</div>
        <h2>  4. Appointments</h2>
        <div>  * Booking: Appointments can be booked in person, by phone, or through our online booking system (if applicable). We recommend booking in advance to secure your preferred time.</div>
        <div>  * Cancellations: We kindly request that you provide at least [e.g., 24 hours'] notice if you need to cancel or reschedule your appointment. This allows us to offer the slot to other clients.</div>
        <div>  * No-Shows: Failure to provide sufficient notice for cancellations or a no-show may result in a cancellation fee or requiring a deposit for future bookings.</div>
        <div>  * Late Arrivals: Please arrive on time for your appointment. If you are significantly late, we may need to shorten your service or reschedule your appointment to avoid inconveniencing other clients.</div>
        <h2>  5. Payment</h2>
        <div>  * Pricing: All prices for services are listed in our salon and/or on our website. Prices are subject to change without prior notice.</div>
        <div>  * Payment Methods: We accept [List accepted payment methods, e.g., cash, major credit cards (Visa, MasterCard, American Express, Discover), debit cards, salon gift cards].</div>
        <div>  * Gratuities: Gratuities for our technicians are not included in the service price and are at your discretion.</div>
        
        <h2>  6. Client Responsibilities</h2>
        <div>  * Health Disclosure: For your safety and the best service outcome, please inform your technician of any allergies, sensitivities, medical conditions (e.g., diabetes, nail fungus), or medications that may affect your service, prior to the start of your service.</div>
        <div>  * Conduct: We ask all clients to maintain respectful and appropriate behavior while in our salon. We reserve the right to refuse service to anyone who behaves in an inappropriate, abusive, or disruptive manner.</div>
        <div>  * Personal Belongings: While we take reasonable care, we are not responsible for any lost or damaged personal belongings brought into the salon.</div>
        <div>  * Children: For the safety and relaxation of all our clients, we request that children are supervised by an adult at all times and remain seated unless they are receiving a service.</div>
        
        <h2>  7. Salon's Rights</h2>
        <div>  * Refusal of Service: We reserve the right to refuse service to anyone at our discretion.</div>
        <div>  * Changes to Services/Pricing: We reserve the right to modify or discontinue services or adjust pricing at any time without prior notice.</div>
        <div>  * Corrections/Fixes: If you are not satisfied with your service, please notify us within [e.g., 24-48 hours] of your appointment. We will assess the situation and, if deemed appropriate, offer a complimentary correction or a partial refund. Beyond this period, charges may apply for any corrections.</div>
        
        <h2>  8. Gift Cards/Vouchers</h2>
        <div>  * Gift cards and vouchers are redeemable for services and products at [mac_salon_name] only.</div>
        <div>  * They are not redeemable for cash.</div>
        <div>  * Lost or stolen gift cards will not be replaced.</div>
        <div>  * Gift Cards expire 5 years from the date of purchase.</div>
        
        <h2>  9. Product Sales</h2>
        <div>  * Returns/Refunds: All retail product sales are final. We do not offer refunds or exchanges on any purchased items.</div>
        
        <div>  10. Limitation of Liability</div>
        <div>  [mac_salon_name] and its employees shall not be liable for any direct, indirect, incidental, special, consequential, or punitive damages, including but not limited to personal injury, lost profits, or lost data, arising out of or in connection with your use of our services, to the fullest extent permitted by law. While we strive for excellence, all services are provided "as is" and "as available."</div>
        
        <h2>  11. Intellectual Property (If applicable to your website or branding)</h2>
        <div>  All content on our website and in our salon, including text, graphics, logos, images, and service marks, is the property of [mac_salon_name] or its licensors and is protected by intellectual property laws. You may not use, reproduce, or distribute any content without our express written permission.</div>
        
        <h2>  12. Governing Law</h2>
        <div>  These Terms shall be governed by and construed in accordance with the laws of the State of [mac_salon_state], without regard to its conflict of law principles. Any legal action or proceeding arising out of or related to these Terms shall be brought exclusively in the courts located in [mac_salon_city], [mac_salon_state].</div>
        
        <h2>  13. Changes to These Terms</h2>
        <div>  We reserve the right to modify or replace these Terms at any time. If a revision is material, we will try to provide at least as soon as reasonably possible notice prior to any new terms taking effect. What constitutes a material change will be determined at our sole discretion. By continuing to access or use our services after those revisions become effective, you agree to be bound by the revised terms.</div>
        
        <h2>  14. Contact Us</h2>
        <div>  If you have any questions about these Terms of Service, please contact us at:</div>
        <div>  [mac_salon_name]</div>
    </div>

<?php
        return ob_get_clean();
    }
}

// Initialize the plugin only if we're in WordPress
if (defined('ABSPATH')) {
    add_action('plugins_loaded', array('Mac_Authorize_Policy_And_Terms_Settings', 'get_instance'));
}

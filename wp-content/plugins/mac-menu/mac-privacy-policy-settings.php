<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Check if WordPress is loaded
if (!function_exists('add_action')) {
    die('This file cannot be accessed directly.');
}

class Mac_Privacy_Policy_Settings {
    private static $instance = null;
    private $business_name;
    private $contact_page_id;
    private $privacy_page_id;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Check if we're in WordPress admin
        // if (!is_admin()) {
        //     return;
        // }

        $this->init_hooks();
        $this->business_name = $this->get_business_name();
        $this->contact_page_id = get_option('contact_page_id');
        $this->privacy_page_id = get_option('wp_page_for_privacy_policy');
    }

    private function init_hooks() {
        // Only add admin hooks if we're in admin
        if (is_admin()) {
            add_action('admin_menu', array($this, 'add_admin_menu'));
            add_action('admin_init', array($this, 'register_settings'));
        }

        // Add frontend hooks
        add_shortcode('privacy_policy', array($this, 'privacy_policy_shortcode'));
        
        // Add Contact Form 7 hooks if CF7 is active
        if (class_exists('WPCF7')) {
            add_filter('wpcf7_form_elements', array($this, 'add_hidden_field'));
            add_action('wpcf7_posted_data', array($this, 'validate_form_submission'));
        }
    }

    public function add_admin_menu() {
        add_options_page(
            'Privacy Policy Settings',
            'Privacy Policy Settings',
            'manage_options',
            'mac-privacy-policy-settings',
            array($this, 'render_settings_page')
        );
    }

    public function register_settings() {
        register_setting('privacy_policy_settings', 'contact_page_id');
    }

    public function render_settings_page() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_REQUEST['contact_page_id']) && $_REQUEST['contact_page_id'] != '') {
            $this->handle_form_submission();
        }
        ?>
        <div class="wrap">
            <h1>Privacy Policy Settings</h1>
            <form method="post" action="">
                <style>
                    .privacy-policy {
                        font-size: 18px;
                        text-decoration: none;
                        color: #222;
                    }
                </style>
                <?php
                settings_fields('privacy_policy_settings');
                do_settings_sections('privacy_policy_settings');
                ?>

                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">Business Name</th>
                        <td>
                            <?= $this->business_name ?> <a href="<?= esc_url(admin_url('options-general.php')); ?>">edit</a>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Contact Page</th>
                        <td>
                            <?php echo $this->get_contact_page_select(); ?>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Privacy Policy Page</th>
                        <td>
                            <?php echo $this->get_privacy_policy_link(); ?>
                            <a href="<?= esc_url(admin_url('options-privacy.php')); ?>">edit</a>
                        </td>
                    </tr>
                </table>
                <input type="submit" name="submit-add-shortcode" value="Update Privacy Policy" class="button button-primary">
            </form>

            <h3>Use shortcode <strong>[privacy_policy]</strong> anywhere you like for Privacy Policy.</h3>
            <h2>Copyable Contact form with consent</h2>

            <textarea id="form-template" style="width: 100%; height: 300px;" readonly>
                <?= $this->get_form_content(); ?>
            </textarea>

            <button id="copy-template-btn" class="button button-primary" style="margin-top: 10px;">Copy to Clipboard</button>
            <p class="description">Click the button above to copy the form template. Paste it into Contact Form 7 to create your custom form.</p>

            <script>
                document.addEventListener('DOMContentLoaded', function () {
                    const copyButton = document.getElementById('copy-template-btn');
                    const templateTextarea = document.getElementById('form-template');

                    copyButton.addEventListener('click', function () {
                        templateTextarea.select();
                        templateTextarea.setSelectionRange(0, 99999);
                        document.execCommand('copy');
                        alert('Form template copied to clipboard!');
                    });
                });
            </script>

            <p class="description">Copy the above template and paste it into Contact Form 7 to create your contact form. Might need to update the privacy link</p>
        </div>
        <?php
    }

    private function handle_form_submission() {
        update_option('contact_page_id', $_REQUEST['contact_page_id']);
        $this->install_and_activate_cf7();
        $this->create_custom_contact_form();
        $this->update_privacy_policy();
    }

    private function get_contact_page_select() {
        $pages = get_posts([
            'post_type' => 'page',
            'post_status' => 'publish',
            'numberposts' => -1,
        ]);

        if (empty($pages)) {
            return '<select id="contact_page_id"><option value="">No pages available</option></select>';
        }

        $select_html = '<select name="contact_page_id" id="pages">';
        $selected_found = false;

        foreach ($pages as $page) {
            $selected = '';
            if ($this->contact_page_id == $page->ID) {
                $selected = 'selected';
                $selected_found = true;
            } else if ((stripos($page->post_title, 'contact') !== false) && !$selected_found) {
                $selected = 'selected';
                $selected_found = true;
            }

            $select_html .= sprintf(
                '<option value="%d" %s>%s</option>',
                $page->ID,
                $selected,
                esc_html($page->post_title)
            );
        }

        $select_html .= '</select>';
        return $select_html;
    }

    private function get_privacy_policy_link() {
        if (!$this->privacy_page_id) {
            return '<span class="privacy-policy">No Privacy Policy Page</span>';
        }

        $url = get_permalink($this->privacy_page_id);
        $title = get_the_title($this->privacy_page_id);
        return '<a class="privacy-policy" href="' . $url . '">' . $title . '</a>';
    }

    private function get_business_name() {
        return !empty(get_bloginfo('name')) ? get_bloginfo('name') : 'ABC SALON';
    }

    private function get_form_content() {
        $privacy_policy_url = $this->privacy_page_id ? get_permalink($this->privacy_page_id) : "";
        $small_numbers = [1 => "one", 2 => "two", 3 => "three", 4 => "four", 5 => "five"];
        $large_numbers = [6 => "six", 7 => "seven", 8 => "eight", 9 => "nine", 10 => "ten"];
        $questions = [];

        for ($i = 0; $i < 10; $i++) {
            $num1 = array_rand($large_numbers);
            $num2 = array_rand($small_numbers);

            if (rand(0, 1) === 0) {
                $result = $num1 + $num2;
                $question = "$num1 + " . $small_numbers[$num2] . " equals what?|$result";
            } else {
                $result = $num1 - $num2;
                $question = "$num1 - " . $small_numbers[$num2] . " equals what?|$result";
            }

            $questions[] = $question;
        }

        $quiz_tag = '[quiz custom_quiz "' . implode('" "', $questions) . '"]';

        return '<div class="cf-container">
            <div class="cf-col-12">[text* your-name placeholder "Name"]</div>
            <div class="cf-col-12">[tel phone placeholder "Cell Phone"]</div>
            <div class="cf-col-12">[select Purpose "General Information" "Say Hi" "Get promotion" "Other"]</div>
            <div class="cf-col-12">[textarea note placeholder] Note [/textarea]</div>
            <div class="cf-col-12">[acceptance checkbox-optin optional] (optional) I consent to receive marketing texts (for example, promotions and reminders) from ' . $this->business_name . ' at the provided number, including messages sent via an autodialer. Consent is not required for purchase. Message and data rates may apply, and the frequency of texts may vary. Reply STOP to unsubscribe. [/acceptance]</div>
            <div class="cf-col-12">[acceptance checkbox-trans optional] (optional) I consent to receive transactional SMS messages from ' . $this->business_name . ' for appointment notifications and POS-related functions. Consent is not required for purchase. Message and data rates may apply, and the frequency of texts may vary. Reply STOP to unsubscribe. [/acceptance]</div>
            <div class="cf-col-12">[acceptance privacy-policy optional] (optional) I have read and agree to the Privacy Policy located at <a href="' . $privacy_policy_url . '" target="_blank">' . $privacy_policy_url . '</a> [/acceptance]</div>
            <div class="cf-col-12"><label for="custom_quiz" style="margin-right:10px;">Please answer with a number:</label>' . $quiz_tag . '</div>
            <div class="cf-col-12">[submit "Submit"]</div>
        </div>';
    }

    public function privacy_policy_shortcode() {
        $contact_page_url = get_permalink($this->contact_page_id);
        ob_start();
        ?>
        <div id="privacy-policy">
            <p><strong>Effective Date:</strong> 05/19/23</p>
            <p>
                At <?php echo $this->business_name; ?>, we are committed to protecting your privacy and ensuring the security of your
                personal information.
                This Privacy Policy outlines how we collect, use, and safeguard the information you provide when using our
                mobile application,
                <?php echo $this->business_name; ?>.
            </p>

            <h2>1. Information We Collect</h2>
            <h3>1.1 Personal Information</h3>
            <p>
                When you use ABC Salon System at <?php echo $this->business_name; ?>, we may collect personal information that you
                voluntarily provide,
                such as your name, email address, phone number, and any other information you choose to share with us.
            </p>
            <h3>1.2 Usage Information</h3>
            <p>
                We may also collect non-personal information about your use of the app, including device information, app usage
                data, and analytical information.
            </p>

            <h2>2. Use of Collected Information</h2>
            <p>
                We may use the personal information you provide to us for the following purposes:
            </p>
            <ul>
                <li>To provide you with access to the features and functionalities of ABC Salon System at
                    <?php echo $this->business_name; ?>.
                </li>
                <li>To communicate with you and respond to your inquiries, feedback, or support requests.</li>
                <li>To personalize your experience and improve the app's usability.</li>
                <li>To send you important updates, notifications, and marketing communications related to
                    <?php echo $this->business_name; ?>, only if you have opted in to receive such communications.
                </li>
            </ul>
            <h2>3. Data Security</h2>
            <p>
                We take appropriate measures to protect your personal information from unauthorized access, disclosure,
                alteration, or destruction.
                We use industry-standard security technologies and procedures to safeguard your data.
            </p>

            <h2>4. Data Retention</h2>
            <p>We will retain your personal information for as long as necessary to fulfill the purposes outlined in this
                Privacy Policy, unless a longer retention period is required or permitted by law.</p>

            <h2>5. Third-Party Services</h2>
            <p><?php echo $this->business_name; ?> may integrate with third-party services or include links to external websites or
                applications. This Privacy Policy does not cover the privacy practices of these third parties. We encourage you
                to review the privacy policies of any third-party services or websites you interact with.</p>

            <h2>6. Children's Privacy</h2>
            <p><?php echo $this->business_name; ?> is not intended for use by individuals under the age of 13. We do not knowingly
                collect personal information from children. If you believe we have inadvertently collected personal information
                from a child, please contact us immediately.</p>

            <h2>7. Changes to this Privacy Policy</h2>
            <p>We may update this Privacy Policy from time to time. Any changes will be effective when we post the revised
                Privacy Policy within the ABC SALON SYSTEM app. We encourage you to review this Privacy Policy periodically.</p>

            <h2>8. Your Choices</h2>
            <ul>
                <li><strong>Access and Correction:</strong> You may request access to and correction of your personal
                    information.</li>
                <li><strong>Opt-Out:</strong> You may opt out of receiving marketing communications from us by following the
                    unsubscribe instructions provided in those communications.</li>
                <li><strong>Cookies:</strong> You can manage your cookie preferences through your browser settings. However,
                    disabling cookies may affect your experience on our app.</li>
            </ul>

            <h2>9. Sharing Policy</h2>
            <p>All the above categories exclude text messaging opt-in data and consent; this information will not be shared with
                third parties.</p>
            <p><strong>SMS Opt-In Sharing Policy:</strong> We will not share your SMS opt-in data with third parties for
                purposes unrelated to providing services. Your SMS opt-in status may be shared only with third parties that help
                us deliver messages, such as platform providers, phone companies, and messaging vendors.</p>

            <h2>10. Contact Us</h2>
            <p>
                If you have any questions, concerns, or requests regarding this Privacy Policy or our data practices,
                please contact us at: <a href="<?php echo $contact_page_url; ?>">Contact Us</a>
            </p>
        </div>
        <?php
        return ob_get_clean();
    }

    private function update_privacy_policy() {
        if (!$this->privacy_page_id) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p>Không tìm thấy trang privacy policy được cấu hình.</p></div>';
            });
            return;
        }

        $page = get_post($this->privacy_page_id);
        if (!$page) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p>Không tìm thấy trang privacy policy được cấu hình.</p></div>';
            });
            return;
        }

        $page_content = '[privacy_policy]';
        $page_title = 'Privacy Policy for ' . $this->business_name;

        wp_update_post([
            'ID' => $this->privacy_page_id,
            'post_status' => 'publish',
            'post_title' => $page_title,
        ]);

        if (empty(trim($page->post_content))) {
            $update_result = wp_update_post([
                'ID' => $this->privacy_page_id,
                'post_content' => $page_content
            ]);

            if (is_wp_error($update_result)) {
                error_log($update_result->get_error_message());
            } else {
                add_action('admin_notices', function() {
                    echo '<div class="notice notice-success"><p>Page đã được cập nhật nội dung.</p></div>';
                });
            }
        } else if (!str_contains($page->post_content, $page_content) && \Elementor\Plugin::$instance->db->is_built_with_elementor($this->privacy_page_id) == false) {
            add_action('admin_footer', function() {
                ?>
                <script type="text/javascript">
                    document.addEventListener('DOMContentLoaded', function () {
                        if (confirm("Page đã có nội dung. Bạn có muốn thêm shortcode hay không?")) {
                            var data = {
                                'action': 'override_privacy_policy_content',
                                'privacy_policy_id': '<?php echo esc_js($this->privacy_page_id); ?>'
                            };

                            jQuery.post(ajaxurl, data, function (response) {
                                alert("Shortcode đã được thêm thành công.");
                                location.reload();
                            });
                        }
                    });
                </script>
                <?php
            });
        }

        // Use JavaScript redirection instead of wp_safe_redirect
        add_action('admin_footer', function() {
            ?>
            <script type="text/javascript">
                window.location.href = '<?php echo esc_url(add_query_arg('settings-updated', 'true', wp_get_referer())); ?>';
            </script>
            <?php
        });
    }

    private function create_custom_contact_form() {
        if (!class_exists('WPCF7')) {
            return;
        }

        $form_content = $this->get_form_content();
        $form_title = "Mac Privacy Policy Contact Form";
        $existing_form = get_posts(array(
            'post_type' => 'wpcf7_contact_form',
            'title' => $form_title,
            'posts_per_page' => 1
        ));

        if (!$existing_form) {
            $post_data = array(
                'post_title' => $form_title,
                'post_content' => $form_content,
                'post_status' => 'publish',
                'post_type' => 'wpcf7_contact_form',
            );
            $form_id = wp_insert_post($post_data);
        } else {
            $form_id = $existing_form[0]->ID;
        }

        $contact_form = WPCF7_ContactForm::get_instance($form_id);
        $properties = $contact_form->get_properties();
        $properties['form'] = $form_content;

        $mail_properties = isset($properties['mail']) ? $properties['mail'] : array();
        $domain = parse_url(home_url(), PHP_URL_HOST);
        $mail_properties['sender'] = '[_site_title] <wordpress@' . $domain . '>';
        $mail_properties['subject'] = 'New submission from Contact Form ([your-name])';
        $mail_properties['body'] = 'Name: [your-name]
Phone: [phone]
Purpose: [Purpose]
Note: [note]
[checkbox-optin]
[checkbox-trans]
--
This e-mail was sent from a contact form on [_site_title] ([_site_url])';
        $mail_properties['exclude_blank'] = true;
        $mail_properties['use_html'] = true;
        $properties['mail'] = $mail_properties;

        $contact_form->set_properties($properties);
        $contact_form->save();
    }

    private function install_and_activate_cf7() {
        if (!is_plugin_active('contact-form-7/wp-contact-form-7.php')) {
            if (!file_exists(WP_PLUGIN_DIR . '/contact-form-7')) {
                require_once(ABSPATH . 'wp-admin/includes/plugin.php');
                require_once(ABSPATH . 'wp-admin/includes/file.php');
                require_once(ABSPATH . 'wp-admin/includes/misc.php');
                require_once(ABSPATH . 'wp-admin/includes/class-wp-upgrader.php');

                $plugin_installer = new Plugin_Upgrader();
                $url = 'https://downloads.wordpress.org/plugin/contact-form-7.latest-stable.zip';
                $plugin_installer->install($url);
            }

            activate_plugin('contact-form-7/wp-contact-form-7.php');
        }
    }

    public function add_hidden_field($html) {
        return '<div style="display: none"><p><span class="wpcf7-form-control-wrap" data-name="mac_check"><input size="40" class="wpcf7-form-control wpcf7-text" aria-invalid="false" value="" type="text" name="mac_check"></span></p></div>' . $html;
    }

    public function validate_form_submission($posted_data) {
        $submission = WPCF7_Submission::get_instance();
        if (!$submission) {
            return $posted_data;
        }

        if (!empty($posted_data['mac_check'])) {
            $submission->set_status('spam');
            $submission->set_response('There was an error trying to send your message. Please try again later.');
            return $posted_data;
        }

        $urlPattern = "/\b((https?:\/\/|www\.)?[a-zA-Z0-9][a-zA-Z0-9-]{1,61}[a-zA-Z0-9]\.[a-zA-Z]{2,}(\S*)?)\b/i";
        $fields_to_check = ['your-name', 'note'];

        foreach ($fields_to_check as $field) {
            if (!empty($posted_data[$field]) && preg_match($urlPattern, $posted_data[$field])) {
                $submission->set_status('spam');
                $submission->set_response('There was an error trying to send your message. Please try again later.');
                return $posted_data;
            }
        }

        return $posted_data;
    }
}

// Initialize the plugin only if we're in WordPress
if (defined('ABSPATH')) {
    add_action('plugins_loaded', array('Mac_Privacy_Policy_Settings', 'get_instance'));
}

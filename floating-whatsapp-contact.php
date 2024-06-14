<?php

if (!class_exists('FloatingWhatsAppContact')) {
    class FloatingWhatsAppContact {

        public function __construct() {
            add_action('admin_menu', array($this, 'fwc_create_menu'));
            add_action('admin_init', array($this, 'fwc_settings_init'));
            add_action('wp_ajax_fwc_update_click_count', array($this, 'fwc_update_click_count'));
            add_action('wp_ajax_nopriv_fwc_update_click_count', array($this, 'fwc_update_click_count'));
            add_action('wp_enqueue_scripts', array($this, 'fwc_enqueue_scripts'));
            add_action('init', array($this, 'fwc_register_shortcode')); // Register shortcode
        }

        public function fwc_create_menu() {
            add_menu_page(
                __('Floating WhatsApp Contact', 'wordpress'),
                __('WhatsApp Contact', 'wordpress'),
                'manage_options',
                'fwc-settings',
                array($this, 'fwc_settings_page')
            );
        }

        public function fwc_settings_init() {
            register_setting('fwc_settings_group', 'fwc_settings');

            add_settings_section(
                'fwc_settings_section',
                __('WhatsApp Contact Settings', 'wordpress'),
                array($this, 'fwc_settings_section_callback'),
                'fwc_settings_group'
            );

            add_settings_field(
                'fwc_selected_numbers',
                __('Select WhatsApp Numbers', 'wordpress'),
                array($this, 'fwc_selected_numbers_render'),
                'fwc_settings_group',
                'fwc_settings_section'
            );

            add_settings_field(
                'fwc_display_option',
                __('Display Icon On', 'wordpress'),
                array($this, 'fwc_display_option_render'),
                'fwc_settings_group',
                'fwc_settings_section'
            );

            if (current_user_can('manage_options')) {
                add_settings_field(
                    'fwc_manage_agents',
                    __('Manage Agents', 'wordpress'),
                    array($this, 'fwc_manage_agents_render'),
                    'fwc_settings_group',
                    'fwc_settings_section'
                );
            }
        }

        public function fwc_selected_numbers_render() {
            $options = get_option('fwc_settings');
            $agents = get_option('fwc_agents', array());
            ?>
            <select multiple="multiple" name="fwc_settings[selected_numbers][]">
                <?php foreach ($agents as $key => $agent) : ?>
                <option value="<?php echo esc_attr($agent['number']); ?>" <?php echo in_array($agent['number'], $options['selected_numbers']) ? 'selected' : ''; ?>>
                    <?php echo esc_html($agent['name']); ?> - <?php echo esc_html($agent['number']); ?>
                </option>
                <?php endforeach; ?>
            </select>
            <?php
        }

        public function fwc_display_option_render() {
            $options = get_option('fwc_settings');
            ?>
            <select name="fwc_settings[display_option]">
                <option value="all" <?php selected($options['display_option'], 'all'); ?>><?php esc_html_e('All Pages', 'wordpress'); ?></option>
                <option value="single_product" <?php selected($options['display_option'], 'single_product'); ?>><?php esc_html_e('Single Product Page Only', 'wordpress'); ?></option>
            </select>
            <?php
        }

        public function fwc_settings_section_callback() {
            echo __('Configure the WhatsApp contact settings below:', 'wordpress');
        }

        public function fwc_manage_agents_render() {
            ?>
            <h2><?php _e('Add New Agent', 'wordpress'); ?></h2>
            <form method="post" action="">
                <input type="text" name="fwc_agent_name" placeholder="<?php esc_attr_e('Agent Name', 'wordpress'); ?>" required>
                <input type="text" name="fwc_agent_number" placeholder="<?php esc_attr_e('WhatsApp Number', 'wordpress'); ?>" required>
                <?php wp_nonce_field('fwc_add_agent_nonce', 'fwc_add_agent_nonce_field'); ?>
                <input type="submit" name="fwc_add_agent" value="<?php esc_attr_e('Add Agent', 'wordpress'); ?>">
            </form>
            <h2><?php _e('Existing Agents', 'wordpress'); ?></h2>
            <table>
                <thead>
                    <tr>
                        <th><?php _e('Agent Name', 'wordpress'); ?></th>
                        <th><?php _e('WhatsApp Number', 'wordpress'); ?></th>
                        <th><?php _e('Actions', 'wordpress'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $agents = get_option('fwc_agents', array());
                    foreach ($agents as $key => $agent) {
                        ?>
                        <tr>
                            <td><?php echo esc_html($agent['name']); ?></td>
                            <td><?php echo esc_html($agent['number']); ?></td>
                            <td>
                                <form method="post" action="" style="display:inline;">
                                    <?php wp_nonce_field('fwc_delete_agent_nonce', 'fwc_delete_agent_nonce_field'); ?>
                                    <input type="hidden" name="fwc_agent_key" value="<?php echo esc_attr($key); ?>">
                                    <input type="submit" name="fwc_delete_agent" value="<?php esc_attr_e('Delete', 'wordpress'); ?>">
                                </form>
                            </td>
                        </tr>
                        <?php
                    }
                    ?>
                </tbody>
            </table>
            <?php
        }

        public function fwc_settings_page() {
            if (current_user_can('manage_options')) {
                if (isset($_POST['fwc_add_agent']) && check_admin_referer('fwc_add_agent_nonce', 'fwc_add_agent_nonce_field')) {
                    $this->fwc_add_agent();
                }

                if (isset($_POST['fwc_delete_agent']) && check_admin_referer('fwc_delete_agent_nonce', 'fwc_delete_agent_nonce_field')) {
                    $this->fwc_delete_agent();
                }
            }
            ?>
            <form action="options.php" method="post">
                <?php
                settings_fields('fwc_settings_group');
                do_settings_sections('fwc_settings_group');
                submit_button();
                ?>
            </form>
            <?php
        }

        public function fwc_add_agent() {
            if (!current_user_can('manage_options')) {
                return;
            }
            $name = sanitize_text_field($_POST['fwc_agent_name']);
            $number = sanitize_text_field($_POST['fwc_agent_number']);
            $agents = get_option('fwc_agents', array());
            $agents[] = array('name' => $name, 'number' => $number);
            update_option('fwc_agents', $agents);
        }

        public function fwc_delete_agent() {
            if (!current_user_can('manage_options')) {
                return;
            }
            $key = intval($_POST['fwc_agent_key']);
            $agents = get_option('fwc_agents', array());
            if (isset($agents[$key])) {
                unset($agents[$key]);
                update_option('fwc_agents', $agents);
            }
        }

        public function fwc_get_current_whatsapp_number() {
            $options = get_option('fwc_settings');
            $selected_numbers = isset($options['selected_numbers']) ? $options['selected_numbers'] : array();
            if (empty($selected_numbers)) {
                return '';
            }

            $session_key = 'fwc_selected_number';
            if (isset($_SESSION[$session_key]) && in_array($_SESSION[$session_key], $selected_numbers)) {
                return $_SESSION[$session_key];
            }

            $number = $selected_numbers[array_rand($selected_numbers)];
            $_SESSION[$session_key] = $number;
            return $number;
        }

        public function fwc_register_shortcode() {
            add_shortcode('whatsapp_icon', array($this, 'fwc_whatsapp_shortcode'));
        }

        public function fwc_whatsapp_shortcode($atts) {
            $number = $this->fwc_get_current_whatsapp_number();
            if (!$number) {
                return '';
            }

            // Get current post or product info if available
            global $post, $product;
            $pre_message = "Hi, I'm interested in the following:\n";

            if (is_object($product)) {
                $pre_message .= "Product Name: " . $product->get_name() . "\n";
                $pre_message .= "Product SKU: " . $product->get_sku() . "\n";
            } elseif (is_object($post)) {
                $pre_message .= "Title: " . $post->post_title . "\n";
            }

            $pre_message .= "Please provide more information.";

            // Encode pre-message for URL
            $encoded_message = rawurlencode($pre_message);

            ob_start();
            ?>
            <div id="floating-whatsapp">
                <a href="https://wa.me/<?php echo esc_attr($number); ?>/?text=<?php echo $encoded_message; ?>" target="_blank">
                    <img src="<?php echo plugin_dir_url(__FILE__) . 'whatsapp.gif'; ?>" alt="WhatsApp">
                </a>
            </div>
            <style>
            #floating-whatsapp {
                position: fixed;
                bottom: 20px;
                right: 20px;
                z-index: 1000;
            }
            #floating-whatsapp img {
                width: 50px;
                height: 50px;
            }
            </style>
            <?php
            return ob_get_clean();
        }

        public function fwc_enqueue_scripts() {
            wp_enqueue_script('jquery');
            wp_add_inline_script('jquery', $this->fwc_inline_js());
        }

        private function fwc_inline_js() {
            ob_start();
            ?>
            <script type="text/javascript">
            jQuery(document).ready(function($) {
                $('#floating-whatsapp a').on('click', function() {
                    var data = {
                        action: 'fwc_update_click_count',
                        security: '<?php echo wp_create_nonce("fwc_update_click_count"); ?>'
                    };
                    $.post('<?php echo admin_url("admin-ajax.php"); ?>', data);
                });
            });
            </script>
            <?php
            return ob_get_clean();
        }

        public function fwc_update_click_count() {
            check_ajax_referer('fwc_update_click_count', 'security');
            $count = get_option('fwc_click_count', 0);
            $count++;
            update_option('fwc_click_count', $count);
            wp_send_json_success();
        }
    }
}

if (class_exists('FloatingWhatsAppContact')) {
    new FloatingWhatsAppContact();
}



<?php
/**
 * Plugin Name: Greek Regions for WooCommerce
 * Description: Adds all Greek regions (νομοί) to WooCommerce checkout options.
 * Version: 1.1.0
 * Author: Thanos Zacharias
 * Author URI: https://thanoszacharias.com/
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: greek-regions-for-woocommerce
 * Requires at least: 5.0
 * Requires PHP: 7.2
 * Requires Plugins: woocommerce
 * WC requires at least: 3.0.0
 * Tested up to: 6.8
 * WC tested up to: 8.5
 *
 * @package Greek_Regions_For_WooCommerce
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('GRWC_VERSION', '1.0.0');
define('GRWC_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('GRWC_PLUGIN_URL', plugin_dir_url(__FILE__));
define('GRWC_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Check if WooCommerce is active
function grwc_is_woocommerce_active() {
    $active_plugins = (array) get_option('active_plugins', array());
    
    if (is_multisite()) {
        $active_plugins = array_merge($active_plugins, get_site_option('active_sitewide_plugins', array()));
    }
    
    return in_array('woocommerce/woocommerce.php', $active_plugins) || array_key_exists('woocommerce/woocommerce.php', $active_plugins);
}

// Don't proceed if WooCommerce is not active
if (!grwc_is_woocommerce_active()) {
    add_action('admin_notices', 'grwc_woocommerce_missing_notice');
    return;
}

/**
 * Display admin notice if WooCommerce is not active
 */
function grwc_woocommerce_missing_notice() {
    ?>
    <div class="error">
        esc_html__('Greek Regions for WooCommerce requires WooCommerce to be installed and active. You can download %s here.', 'greek-regions-for-woocommerce'),
    '<a href="https://woocommerce.com/" target="_blank">WooCommerce</a>'
    </div>
    <?php
}

/**
 * Greek Regions for WooCommerce Class
 *
 * @since 1.0.0
 */
class GRWC_Main {

    /**
     * Singleton instance
     *
     * @var Greek_Regions_For_WooCommerce
     */
    private static $instance = null;

    /**
     * Get singleton instance
     *
     * @return Greek_Regions_For_WooCommerce
     */
    public static function get_instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        // Hook to filter states
        add_filter('woocommerce_states', array($this, 'add_greek_regions'));
        
        // Change the label for the state field when Greece is selected
        add_filter('woocommerce_default_address_fields', array($this, 'change_state_label_for_greece'));
        
        // Load plugin text domain for translations
        // add_action('plugins_loaded', array($this, 'load_plugin_textdomain'));
        
        // Add plugin action links
        add_filter('plugin_action_links_' . GRWC_PLUGIN_BASENAME, array($this, 'add_plugin_action_links'));
        
        // Register activation hook
        register_activation_hook(__FILE__, array($this, 'activation'));
        
        // Register deactivation hook
        register_deactivation_hook(__FILE__, array($this, 'deactivation'));
        
        // Declare HPOS compatibility
        add_action('before_woocommerce_init', array($this, 'declare_hpos_compatibility'));
    }

    /**
     * Plugin activation
     */
    public function activation() {
        // Flush rewrite rules
        flush_rewrite_rules();
        
        // Maybe set a transient for activation notice
        set_transient('grwc_activation_notice', true, 5);
    }

    /**
     * Plugin deactivation
     */
    public function deactivation() {
        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Declare HPOS compatibility
     */
    public function declare_hpos_compatibility() {
        if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
        }
    }

    /**
     * Add plugin action links
     *
     * @param array $links Plugin action links
     * @return array Modified plugin action links
     */
    public function add_plugin_action_links($links) {
        $plugin_links = array(
            '<a href="' . admin_url('admin.php?page=wc-settings&tab=shipping') . '">' . __('Settings', 'greek-regions-for-woocommerce') . '</a>',
        );
        return array_merge($plugin_links, $links);
    }

    /**
     * Load plugin textdomain
     */
    // public function load_plugin_textdomain() {
    //     load_plugin_textdomain('greek-regions-for-woocommerce', false, dirname(GRWC_PLUGIN_BASENAME) . '/languages');
    // }

    /**
     * Change the label for the state field to "Νομός" when Greece is selected
     *
     * @param array $fields Address fields
     * @return array Modified address fields
     */
    public function change_state_label_for_greece($fields) {
        if (isset($fields['state'])) {
            $fields['state']['label_callback'] = array($this, 'maybe_change_state_label');
            $fields['state']['placeholder_callback'] = array($this, 'maybe_change_state_placeholder');
        }
        
        return $fields;
    }
    
    /**
     * Maybe change the state label based on the country
     *
     * @param string $label Current label
     * @param string $country Country code
     * @return string Modified label
     */
    public function maybe_change_state_label($label, $country) {
        if ('GR' === $country) {
            return __('Νομός', 'greek-regions-for-woocommerce');
        }
        
        return $label;
    }
    
    /**
     * Maybe change the state placeholder based on the country
     *
     * @param string $placeholder Current placeholder
     * @param string $country Country code
     * @return string Modified placeholder
     */
    public function maybe_change_state_placeholder($placeholder, $country) {
        if ('GR' === $country) {
            return __('Επιλέξτε νομό', 'greek-regions-for-woocommerce');
        }
        
        return $placeholder;
    }

    /**
     * Add Greek regions to WooCommerce states
     *
     * @param array $states Array of states
     * @return array Modified array of states
     */
    public function add_greek_regions($states) {
        
        // Define Greek regions (νομοί)
        $states['GR'] = array(
            'ΑΤΤΙΚΗΣ' => __('Αττικής', 'greek-regions-for-woocommerce'),
            'ΑΙΤΩΛΟΑΚΑΡΝΑΝΙΑΣ' => __('Αιτωλοακαρνανίας', 'greek-regions-for-woocommerce'),
            'ΑΡΓΟΛΙΔΑΣ' => __('Αργολίδας', 'greek-regions-for-woocommerce'),
            'ΑΡΚΑΔΙΑΣ' => __('Αρκαδίας', 'greek-regions-for-woocommerce'),
            'ΑΡΤΑΣ' => __('Άρτας', 'greek-regions-for-woocommerce'),
            'ΑΧΑΪΑΣ' => __('Αχαΐας', 'greek-regions-for-woocommerce'),
            'ΒΟΙΩΤΙΑΣ' => __('Βοιωτίας', 'greek-regions-for-woocommerce'),
            'ΓΡΕΒΕΝΩΝ' => __('Γρεβενών', 'greek-regions-for-woocommerce'),
            'ΔΡΑΜΑΣ' => __('Δράμας', 'greek-regions-for-woocommerce'),
            'ΔΩΔΕΚΑΝΗΣΟΥ' => __('Δωδεκανήσου', 'greek-regions-for-woocommerce'),
            'ΕΒΡΟΥ' => __('Έβρου', 'greek-regions-for-woocommerce'),
            'ΕΥΒΟΙΑΣ' => __('Εύβοιας', 'greek-regions-for-woocommerce'),
            'ΕΥΡΥΤΑΝΙΑΣ' => __('Ευρυτανίας', 'greek-regions-for-woocommerce'),
            'ΖΑΚΥΝΘΟΥ' => __('Ζακύνθου', 'greek-regions-for-woocommerce'),
            'ΗΛΕΙΑΣ' => __('Ηλείας', 'greek-regions-for-woocommerce'),
            'ΗΜΑΘΙΑΣ' => __('Ημαθίας', 'greek-regions-for-woocommerce'),
            'ΗΡΑΚΛΕΙΟΥ' => __('Ηρακλείου', 'greek-regions-for-woocommerce'),
            'ΘΕΣΠΡΩΤΙΑΣ' => __('Θεσπρωτίας', 'greek-regions-for-woocommerce'),
            'ΘΕΣΣΑΛΟΝΙΚΗΣ' => __('Θεσσαλονίκης', 'greek-regions-for-woocommerce'),
            'ΙΩΑΝΝΙΝΩΝ' => __('Ιωαννίνων', 'greek-regions-for-woocommerce'),
            'ΚΑΒΑΛΑΣ' => __('Καβάλας', 'greek-regions-for-woocommerce'),
            'ΚΑΡΔΙΤΣΑΣ' => __('Καρδίτσας', 'greek-regions-for-woocommerce'),
            'ΚΑΣΤΟΡΙΑΣ' => __('Καστοριάς', 'greek-regions-for-woocommerce'),
            'ΚΕΡΚΥΡΑΣ' => __('Κέρκυρας', 'greek-regions-for-woocommerce'),
            'ΚΕΦΑΛΛΗΝΙΑΣ' => __('Κεφαλληνίας', 'greek-regions-for-woocommerce'),
            'ΚΙΛΚΙΣ' => __('Κιλκίς', 'greek-regions-for-woocommerce'),
            'ΚΟΖΑΝΗΣ' => __('Κοζάνης', 'greek-regions-for-woocommerce'),
            'ΚΟΡΙΝΘΙΑΣ' => __('Κορινθίας', 'greek-regions-for-woocommerce'),
            'ΚΥΚΛΑΔΩΝ' => __('Κυκλάδων', 'greek-regions-for-woocommerce'),
            'ΛΑΚΩΝΙΑΣ' => __('Λακωνίας', 'greek-regions-for-woocommerce'),
            'ΛΑΡΙΣΑΣ' => __('Λάρισας', 'greek-regions-for-woocommerce'),
            'ΛΑΣΙΘΙΟΥ' => __('Λασιθίου', 'greek-regions-for-woocommerce'),
            'ΛΕΣΒΟΥ' => __('Λέσβου', 'greek-regions-for-woocommerce'),
            'ΛΕΥΚΑΔΑΣ' => __('Λευκάδας', 'greek-regions-for-woocommerce'),
            'ΜΑΓΝΗΣΙΑΣ' => __('Μαγνησίας', 'greek-regions-for-woocommerce'),
            'ΜΕΣΣΗΝΙΑΣ' => __('Μεσσηνίας', 'greek-regions-for-woocommerce'),
            'ΞΑΝΘΗΣ' => __('Ξάνθης', 'greek-regions-for-woocommerce'),
            'ΠΕΛΛΑΣ' => __('Πέλλας', 'greek-regions-for-woocommerce'),
            'ΠΙΕΡΙΑΣ' => __('Πιερίας', 'greek-regions-for-woocommerce'),
            'ΠΡΕΒΕΖΑΣ' => __('Πρέβεζας', 'greek-regions-for-woocommerce'),
            'ΡΕΘΥΜΝΟΥ' => __('Ρεθύμνου', 'greek-regions-for-woocommerce'),
            'ΡΟΔΟΠΗΣ' => __('Ροδόπης', 'greek-regions-for-woocommerce'),
            'ΣΑΜΟΥ' => __('Σάμου', 'greek-regions-for-woocommerce'),
            'ΣΕΡΡΩΝ' => __('Σερρών', 'greek-regions-for-woocommerce'),
            'ΤΡΙΚΑΛΩΝ' => __('Τρικάλων', 'greek-regions-for-woocommerce'),
            'ΦΘΙΩΤΙΔΑΣ' => __('Φθιώτιδας', 'greek-regions-for-woocommerce'),
            'ΦΛΩΡΙΝΑΣ' => __('Φλώρινας', 'greek-regions-for-woocommerce'),
            'ΦΩΚΙΔΑΣ' => __('Φωκίδας', 'greek-regions-for-woocommerce'),
            'ΧΑΛΚΙΔΙΚΗΣ' => __('Χαλκιδικής', 'greek-regions-for-woocommerce'),
            'ΧΑΝΙΩΝ' => __('Χανίων', 'greek-regions-for-woocommerce'),
            'ΧΙΟΥ' => __('Χίου', 'greek-regions-for-woocommerce')
        );
        
        return $states;
    }
}

// Initialize the plugin
add_action('plugins_loaded', array('GRWC_Main', 'get_instance'), 10);

/**
 * Admin notice after plugin activation
 */
function grwc_admin_notice() {
    // Check transient
    if (get_transient('grwc_activation_notice')) {
        ?>
        <div class="notice notice-success is-dismissible">
            <p><?php esc_html_e('Thank you for installing Greek Regions for WooCommerce! All Greek regions have been added to your WooCommerce checkout.', 'greek-regions-for-woocommerce'); ?></p>
        </div>
        <?php
        // Delete transient
        delete_transient('grwc_activation_notice');
    }
}
add_action('admin_notices', 'grwc_admin_notice');

/**
 * Use the callback functions for WooCommerce 3.6+
 */
add_filter('woocommerce_checkout_fields', 'grwc_customize_checkout_fields');

/**
 * Customize checkout fields
 */
function grwc_customize_checkout_fields($fields) {
    if (isset($fields['billing']['billing_state'])) {
        // Add jQuery script to change the label and placeholder when Greece is selected
        wc_enqueue_js("
            jQuery(document).ready(function($) {
                // On page load
                if ($('#billing_country').val() == 'GR') {
                    $('#billing_state_field label').text('" . __('Νομός', 'greek-regions-for-woocommerce') . "');
                    $('#billing_state').attr('placeholder', '" . __('Επιλέξτε νομό', 'greek-regions-for-woocommerce') . "');
                }
                
                // On country change
                $(document).on('change', '#billing_country', function() {
                    if ($(this).val() == 'GR') {
                        $('#billing_state_field label').text('" . __('Νομός', 'greek-regions-for-woocommerce') . "');
                        $('#billing_state').attr('placeholder', '" . __('Επιλέξτε νομό', 'greek-regions-for-woocommerce') . "');
                    } else {
                        $('#billing_state_field label').text('" . __('State / County', 'greek-regions-for-woocommerce') . "');
                        $('#billing_state').attr('placeholder', '" . __('State / County', 'greek-regions-for-woocommerce') . "');
                    }
                });
                
                // Same for shipping fields
                if ($('#shipping_country').val() == 'GR') {
                    $('#shipping_state_field label').text('" . __('Νομός', 'greek-regions-for-woocommerce') . "');
                    $('#shipping_state').attr('placeholder', '" . __('Επιλέξτε νομό', 'greek-regions-for-woocommerce') . "');
                }
                
                $(document).on('change', '#shipping_country', function() {
                    if ($(this).val() == 'GR') {
                        $('#shipping_state_field label').text('" . __('Νομός', 'greek-regions-for-woocommerce') . "');
                        $('#shipping_state').attr('placeholder', '" . __('Επιλέξτε νομό', 'greek-regions-for-woocommerce') . "');
                    } else {
                        $('#shipping_state_field label').text('" . __('State / County', 'greek-regions-for-woocommerce') . "');
                        $('#shipping_state').attr('placeholder', '" . __('State / County', 'greek-regions-for-woocommerce') . "');
                    }
                });
            });
        ");
    }
    
    return $fields;
}
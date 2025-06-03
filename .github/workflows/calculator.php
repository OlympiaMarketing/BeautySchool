<?php
/**
 * Plugin Name: Beauty School Tuition Calculator
 * Plugin URI: https://github.com/yourusername/beauty-school-calculator
 * Description: A comprehensive calculator for beauty schools to help students estimate tuition costs and FAFSA eligibility. Complies with WordPress.org guidelines.
 * Version: 1.0.0
 * Requires at least: 5.0
 * Requires PHP: 7.4
 * Author: Your Name
 * Author URI: https://yourwebsite.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: beauty-school-calculator
 * Domain Path: /languages
 * Network: false
 * 
 * @package BeautySchoolCalculator
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
if (!defined('BSC_VERSION')) {
    define('BSC_VERSION', '1.0.0');
}
if (!defined('BSC_PLUGIN_URL')) {
    define('BSC_PLUGIN_URL', plugin_dir_url(__FILE__));
}
if (!defined('BSC_PLUGIN_PATH')) {
    define('BSC_PLUGIN_PATH', plugin_dir_path(__FILE__));
}
if (!defined('BSC_PLUGIN_BASENAME')) {
    define('BSC_PLUGIN_BASENAME', plugin_basename(__FILE__));
}

/**
 * Main plugin class
 * 
 * @since 1.0.0
 */
final class BeautySchoolCalculator {
    
    /**
     * Plugin instance
     * 
     * @since 1.0.0
     * @var BeautySchoolCalculator|null
     */
    private static $instance = null;
    
    /**
     * Course configurations cache
     * 
     * @since 1.0.0
     * @var array|null
     */
    private $courses_cache = null;
    
    /**
     * FAFSA enabled cache
     * 
     * @since 1.0.0
     * @var bool|null
     */
    private $fafsa_enabled_cache = null;
    
    /**
     * Get plugin instance
     * 
     * @since 1.0.0
     * @return BeautySchoolCalculator
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     * 
     * @since 1.0.0
     */
    private function __construct() {
        $this->init_hooks();
    }
    
    /**
     * Prevent cloning
     * 
     * @since 1.0.0
     */
    public function __clone() {
        _doing_it_wrong(__FUNCTION__, esc_html__('Cloning is forbidden.', 'beauty-school-calculator'), BSC_VERSION);
    }
    
    /**
     * Prevent unserializing
     * 
     * @since 1.0.0
     */
    public function __wakeup() {
        _doing_it_wrong(__FUNCTION__, esc_html__('Unserializing instances of this class is forbidden.', 'beauty-school-calculator'), BSC_VERSION);
    }
    
    /**
     * Initialize hooks
     * 
     * @since 1.0.0
     */
    private function init_hooks() {
        // Activation/deactivation hooks
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        // Core hooks
        add_action('init', array($this, 'init'));
        add_action('admin_menu', array($this, 'admin_menu'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        
        // AJAX hooks
        add_action('wp_ajax_bsc_calculate_fafsa', array($this, 'ajax_calculate_fafsa'));
        add_action('wp_ajax_nopriv_bsc_calculate_fafsa', array($this, 'ajax_calculate_fafsa'));
        add_action('wp_ajax_bsc_calculate_costs', array($this, 'ajax_calculate_costs'));
        add_action('wp_ajax_nopriv_bsc_calculate_costs', array($this, 'ajax_calculate_costs'));
        
        // Shortcode
        add_shortcode('beauty_calculator', array($this, 'calculator_shortcode'));
        
        // Text domain for translations
        add_action('plugins_loaded', array($this, 'load_textdomain'));
    }
    
    /**
     * Plugin initialization
     * 
     * @since 1.0.0
     */
    public function init() {
        // Cache options on init for better performance
        $this->get_courses();
        $this->is_fafsa_enabled();
    }
    
    /**
     * Load text domain for translations
     * 
     * @since 1.0.0
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'beauty-school-calculator',
            false,
            dirname(BSC_PLUGIN_BASENAME) . '/languages'
        );
    }
    
    /**
     * Plugin activation
     * 
     * @since 1.0.0
     */
    public function activate() {
        // Check minimum requirements
        if (version_compare(PHP_VERSION, '7.4', '<')) {
            deactivate_plugins(BSC_PLUGIN_BASENAME);
            wp_die(esc_html__('Beauty School Calculator requires PHP 7.4 or higher.', 'beauty-school-calculator'));
        }
        
        if (version_compare(get_bloginfo('version'), '5.0', '<')) {
            deactivate_plugins(BSC_PLUGIN_BASENAME);
            wp_die(esc_html__('Beauty School Calculator requires WordPress 5.0 or higher.', 'beauty-school-calculator'));
        }
        
        // Set default options
        $this->set_default_options();
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Plugin deactivation
     * 
     * @since 1.0.0
     */
    public function deactivate() {
        // Clean up temporary data, flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Set default plugin options
     * 
     * @since 1.0.0
     */
    private function set_default_options() {
        $default_courses = array(
            'cosmetology' => array(
                'name' => __('Cosmetology', 'beauty-school-calculator'),
                'price' => 15000,
                'hours' => 1500,
                'books_price' => 500,
                'supplies_price' => 750,
                'other_price' => 0,
                'other_label' => __('Other Fees', 'beauty-school-calculator')
            ),
            'barbering' => array(
                'name' => __('Barbering', 'beauty-school-calculator'),
                'price' => 12000,
                'hours' => 1200,
                'books_price' => 400,
                'supplies_price' => 600,
                'other_price' => 0,
                'other_label' => __('Other Fees', 'beauty-school-calculator')
            ),
            'esthetics' => array(
                'name' => __('Esthetics (Skincare)', 'beauty-school-calculator'),
                'price' => 8000,
                'hours' => 600,
                'books_price' => 300,
                'supplies_price' => 400,
                'other_price' => 0,
                'other_label' => __('Other Fees', 'beauty-school-calculator')
            ),
            'massage' => array(
                'name' => __('Massage Therapy', 'beauty-school-calculator'),
                'price' => 10000,
                'hours' => 750,
                'books_price' => 350,
                'supplies_price' => 300,
                'other_price' => 0,
                'other_label' => __('Other Fees', 'beauty-school-calculator')
            )
        );
        
        // Use add_option to prevent overwriting existing settings
        add_option('bsc_courses', $default_courses);
        add_option('bsc_fafsa_enabled', true);
        add_option('bsc_version', BSC_VERSION);
    }
    
    /**
     * Get courses with caching
     * 
     * @since 1.0.0
     * @return array
     */
    public function get_courses() {
        if (null === $this->courses_cache) {
            $this->courses_cache = get_option('bsc_courses', array());
        }
        return $this->courses_cache;
    }
    
    /**
     * Check if FAFSA is enabled with caching
     * 
     * @since 1.0.0
     * @return bool
     */
    public function is_fafsa_enabled() {
        if (null === $this->fafsa_enabled_cache) {
            $this->fafsa_enabled_cache = (bool) get_option('bsc_fafsa_enabled', true);
        }
        return $this->fafsa_enabled_cache;
    }
    
    /**
     * Clear caches
     * 
     * @since 1.0.0
     */
    private function clear_cache() {
        $this->courses_cache = null;
        $this->fafsa_enabled_cache = null;
    }
    
    /**
     * Enqueue frontend assets
     * 
     * @since 1.0.0
     */
    public function enqueue_frontend_assets() {
        // Only enqueue on pages with shortcode
        global $post;
        if (!is_a($post, 'WP_Post') || !has_shortcode($post->post_content, 'beauty_calculator')) {
            return;
        }
        
        // Enqueue optimized JavaScript (minified in production)
        wp_enqueue_script(
            'bsc-calculator',
            BSC_PLUGIN_URL . 'assets/js/calculator.js',
            array('jquery'),
            BSC_VERSION,
            true
        );
        
        // Localize script with minimal data
        wp_localize_script('bsc-calculator', 'bscAjax', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('bsc_calculator_nonce'),
            'fafaEnabled' => $this->is_fafsa_enabled() ? '1' : '0',
            'strings' => array(
                'selectCourse' => __('Please select a course first.', 'beauty-school-calculator'),
                'fillFields' => __('Please fill in all required fields.', 'beauty-school-calculator'),
                'calcError' => __('Error calculating costs. Please try again.', 'beauty-school-calculator')
            )
        ));
        
        // Enqueue optimized CSS
        wp_enqueue_style(
            'bsc-calculator',
            BSC_PLUGIN_URL . 'assets/css/calculator.css',
            array(),
            BSC_VERSION
        );
    }
    
    /**
     * Enqueue admin assets
     * 
     * @since 1.0.0
     * @param string $hook
     */
    public function enqueue_admin_assets($hook) {
        // Only enqueue on plugin admin pages
        if (strpos($hook, 'beauty-calculator') === false) {
            return;
        }
        
        wp_enqueue_style(
            'bsc-admin',
            BSC_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            BSC_VERSION
        );
    }
    
    /**
     * Add admin menu
     * 
     * @since 1.0.0
     */
    public function admin_menu() {
        add_options_page(
            __('Beauty School Calculator Settings', 'beauty-school-calculator'),
            __('Beauty Calculator', 'beauty-school-calculator'),
            'manage_options',
            'beauty-calculator-settings',
            array($this, 'admin_page')
        );
    }
    
    /**
     * Admin page content
     * 
     * @since 1.0.0
     */
    public function admin_page() {
        // Handle form submission
        if (isset($_POST['submit']) && wp_verify_nonce($_POST['bsc_settings_nonce'], 'bsc_settings')) {
            $this->save_settings();
        }
        
        $courses = $this->get_courses();
        $fafsa_enabled = $this->is_fafsa_enabled();
        
        include BSC_PLUGIN_PATH . 'templates/admin-page.php';
    }
    
    /**
     * Save admin settings
     * 
     * @since 1.0.0
     */
    private function save_settings() {
        // Validate and sanitize input
        $courses = array();
        if (isset($_POST['courses']) && is_array($_POST['courses'])) {
            foreach ($_POST['courses'] as $key => $course) {
                if (!is_array($course)) {
                    continue;
                }
                
                $courses[sanitize_key($key)] = array(
                    'name' => sanitize_text_field($course['name'] ?? ''),
                    'price' => absint($course['price'] ?? 0),
                    'hours' => absint($course['hours'] ?? 0),
                    'books_price' => absint($course['books_price'] ?? 0),
                    'supplies_price' => absint($course['supplies_price'] ?? 0),
                    'other_price' => absint($course['other_price'] ?? 0),
                    'other_label' => sanitize_text_field($course['other_label'] ?? __('Other Fees', 'beauty-school-calculator'))
                );
            }
        }
        
        $fafsa_enabled = isset($_POST['fafsa_enabled']) && $_POST['fafsa_enabled'] === '1';
        
        // Update options
        update_option('bsc_courses', $courses);
        update_option('bsc_fafsa_enabled', $fafsa_enabled);
        
        // Clear cache
        $this->clear_cache();
        
        add_settings_error(
            'bsc_settings',
            'settings_updated',
            __('Settings saved successfully!', 'beauty-school-calculator'),
            'updated'
        );
    }
    
    /**
     * Calculator shortcode
     * 
     * @since 1.0.0
     * @param array $atts Shortcode attributes
     * @return string
     */
    public function calculator_shortcode($atts) {
        // Parse attributes
        $atts = shortcode_atts(array(
            'class' => '',
            'title' => __('Beauty School Tuition Calculator', 'beauty-school-calculator')
        ), $atts, 'beauty_calculator');
        
        // Start output buffering
        ob_start();
        
        // Get cached data
        $courses = $this->get_courses();
        $fafsa_enabled = $this->is_fafsa_enabled();
        
        // Include template
        include BSC_PLUGIN_PATH . 'templates/calculator-form.php';
        
        return ob_get_clean();
    }
    
    /**
     * AJAX handler for FAFSA calculations
     * 
     * @since 1.0.0
     */
    public function ajax_calculate_fafsa() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'bsc_calculator_nonce')) {
            wp_send_json_error(__('Security check failed.', 'beauty-school-calculator'));
        }
        
        // Validate and sanitize input
        $input = $this->validate_calculation_input($_POST, true);
        if (is_wp_error($input)) {
            wp_send_json_error($input->get_error_message());
        }
        
        // Perform calculations
        $results = $this->calculate_financial_aid($input);
        
        wp_send_json_success($results);
    }
    
    /**
     * AJAX handler for cost calculations (no FAFSA)
     * 
     * @since 1.0.0
     */
    public function ajax_calculate_costs() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'bsc_calculator_nonce')) {
            wp_send_json_error(__('Security check failed.', 'beauty-school-calculator'));
        }
        
        // Validate and sanitize input
        $input = $this->validate_calculation_input($_POST, false);
        if (is_wp_error($input)) {
            wp_send_json_error($input->get_error_message());
        }
        
        // Calculate costs
        $results = array(
            'course_name' => $input['course_name'],
            'course_price' => $input['course_price'],
            'books_price' => $input['books_price'],
            'supplies_price' => $input['supplies_price'],
            'other_price' => $input['other_price'],
            'other_label' => $input['other_label'],
            'total_program_cost' => $input['total_program_cost'],
            'fafsa_enabled' => false
        );
        
        wp_send_json_success($results);
    }
    
    /**
     * Validate calculation input
     * 
     * @since 1.0.0
     * @param array $data Input data
     * @param bool $include_fafsa Whether to validate FAFSA fields
     * @return array|WP_Error Validated data or error
     */
    private function validate_calculation_input($data, $include_fafsa = false) {
        $courses = $this->get_courses();
        
        // Validate course
        $course_key = sanitize_key($data['course'] ?? '');
        if (empty($course_key) || !isset($courses[$course_key])) {
            return new WP_Error('invalid_course', __('Invalid course selected.', 'beauty-school-calculator'));
        }
        
        $course = $courses[$course_key];
        $total_program_cost = $course['price'] + $course['books_price'] + $course['supplies_price'] + $course['other_price'];
        
        $validated = array(
            'course_name' => $course['name'],
            'course_price' => $course['price'],
            'books_price' => $course['books_price'],
            'supplies_price' => $course['supplies_price'],
            'other_price' => $course['other_price'],
            'other_label' => $course['other_label'],
            'total_program_cost' => $total_program_cost
        );
        
        // Validate FAFSA fields if needed
        if ($include_fafsa) {
            $age = absint($data['age'] ?? 0);
            $income = absint($data['income'] ?? 0);
            $household_size = absint($data['household_size'] ?? 0);
            $college_students = absint($data['college_students'] ?? 0);
            $dependency = sanitize_text_field($data['dependency'] ?? '');
            
            if ($age < 16 || $age > 100) {
                return new WP_Error('invalid_age', __('Please enter a valid age between 16 and 100.', 'beauty-school-calculator'));
            }
            
            if ($household_size < 1 || $household_size > 20) {
                return new WP_Error('invalid_household', __('Please enter a valid household size.', 'beauty-school-calculator'));
            }
            
            if ($college_students < 1 || $college_students > $household_size) {
                return new WP_Error('invalid_students', __('Number of college students cannot exceed household size.', 'beauty-school-calculator'));
            }
            
            if (!in_array($dependency, array('dependent', 'independent'), true)) {
                return new WP_Error('invalid_dependency', __('Invalid dependency status.', 'beauty-school-calculator'));
            }
            
            $validated = array_merge($validated, array(
                'age' => $age,
                'income' => $income,
                'household_size' => $household_size,
                'college_students' => $college_students,
                'dependency' => $dependency
            ));
        }
        
        return $validated;
    }
    
    /**
     * Calculate financial aid
     * 
     * @since 1.0.0
     * @param array $input Validated input data
     * @return array Results
     */
    private function calculate_financial_aid($input) {
        // Calculate EFC (Expected Family Contribution)
        $efc = $this->calculate_efc(
            $input['income'],
            $input['household_size'],
            $input['college_students'],
            $input['dependency']
        );
        
        // Calculate Pell Grant eligibility
        $pell_grant = $this->calculate_pell_grant($efc);
        
        // Calculate loan eligibility
        $loan_eligibility = $this->calculate_loan_eligibility($input['dependency'], $input['age']);
        
        $total_aid = $pell_grant + $loan_eligibility;
        $remaining_cost = max(0, $input['total_program_cost'] - $total_aid);
        
        return array(
            'course_name' => $input['course_name'],
            'course_price' => $input['course_price'],
            'books_price' => $input['books_price'],
            'supplies_price' => $input['supplies_price'],
            'other_price' => $input['other_price'],
            'other_label' => $input['other_label'],
            'total_program_cost' => $input['total_program_cost'],
            'efc' => $efc,
            'pell_grant' => $pell_grant,
            'loan_eligibility' => $loan_eligibility,
            'total_aid' => $total_aid,
            'remaining_cost' => $remaining_cost,
            'fafsa_enabled' => true
        );
    }
    
    /**
     * Calculate Expected Family Contribution (EFC)
     * 
     * @since 1.0.0
     * @param int $income Annual income
     * @param int $household_size Number of people in household
     * @param int $college_students Number of college students in household
     * @param string $dependency Dependency status
     * @return int EFC amount
     */
    private function calculate_efc($income, $household_size, $college_students, $dependency) {
        // Income protection allowances (simplified version)
        $income_protection = array(
            1 => 17040, 2 => 21330, 3 => 26520, 4 => 32710, 5 => 38490, 6 => 44780
        );
        
        $protection = $income_protection[$household_size] ?? 44780;
        $available_income = max(0, $income - $protection);
        
        // Apply assessment rate based on dependency status
        if ($dependency === 'dependent') {
            $efc = $available_income * 0.47; // Simplified dependent rate
        } else {
            $efc = $available_income * 0.50; // Simplified independent rate
        }
        
        // Adjust for multiple college students
        if ($college_students > 1) {
            $efc = $efc / $college_students;
        }
        
        return round($efc);
    }
    
    /**
     * Calculate Pell Grant eligibility
     * 
     * @since 1.0.0
     * @param int $efc Expected Family Contribution
     * @return int Pell Grant amount
     */
    private function calculate_pell_grant($efc) {
        // 2024-2025 Pell Grant maximum
        $max_pell = 7395;
        $efc_cutoff = 6656;
        
        if ($efc >= $efc_cutoff) {
            return 0;
        }
        
        // Simplified calculation
        $pell_amount = $max_pell - ($efc * 0.3);
        
        return max(0, round($pell_amount));
    }
    
    /**
     * Calculate federal loan eligibility
     * 
     * @since 1.0.0
     * @param string $dependency Dependency status
     * @param int $age Student age
     * @return int Loan amount
     */
    private function calculate_loan_eligibility($dependency, $age) {
        // Federal Direct Loan limits for vocational programs
        if ($dependency === 'independent' || $age >= 24) {
            return 12500; // Independent students
        } else {
            return 5500; // Dependent students
        }
    }
}

// Templates would be in separate files for better organization:

/**
 * Admin page template (templates/admin-page.php)
 */
function bsc_render_admin_page_template($courses, $fafsa_enabled) {
    ?>
    <div class="wrap">
        <h1><?php esc_html_e('Beauty School Calculator Settings', 'beauty-school-calculator'); ?></h1>
        
        <?php settings_errors('bsc_settings'); ?>
        
        <form method="post" action="">
            <?php wp_nonce_field('bsc_settings', 'bsc_settings_nonce'); ?>
            
            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e('Enable FAFSA Calculator', 'beauty-school-calculator'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="fafsa_enabled" value="1" <?php checked($fafsa_enabled, true); ?> />
                            <?php esc_html_e('Check this box only if your school is accredited and eligible for federal financial aid', 'beauty-school-calculator'); ?>
                        </label>
                        <p class="description">
                            <?php esc_html_e('Only accredited beauty schools can qualify for FAFSA. Uncheck this if your school is not accredited.', 'beauty-school-calculator'); ?>
                        </p>
                    </td>
                </tr>
            </table>
            
            <h2><?php esc_html_e('Course Configuration', 'beauty-school-calculator'); ?></h2>
            
            <?php foreach ($courses as $key => $course): ?>
            <h3><?php echo esc_html($course['name']); ?></h3>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e('Course Name', 'beauty-school-calculator'); ?></th>
                    <td><input type="text" name="courses[<?php echo esc_attr($key); ?>][name]" value="<?php echo esc_attr($course['name']); ?>" class="regular-text" /></td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Tuition Price ($)', 'beauty-school-calculator'); ?></th>
                    <td><input type="number" name="courses[<?php echo esc_attr($key); ?>][price]" value="<?php echo esc_attr($course['price']); ?>" min="0" /></td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Required Hours', 'beauty-school-calculator'); ?></th>
                    <td><input type="number" name="courses[<?php echo esc_attr($key); ?>][hours]" value="<?php echo esc_attr($course['hours']); ?>" min="0" /></td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Books Price ($)', 'beauty-school-calculator'); ?></th>
                    <td><input type="number" name="courses[<?php echo esc_attr($key); ?>][books_price]" value="<?php echo esc_attr($course['books_price'] ?? 0); ?>" min="0" /></td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Supplies Price ($)', 'beauty-school-calculator'); ?></th>
                    <td><input type="number" name="courses[<?php echo esc_attr($key); ?>][supplies_price]" value="<?php echo esc_attr($course['supplies_price'] ?? 0); ?>" min="0" /></td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Other Label', 'beauty-school-calculator'); ?></th>
                    <td><input type="text" name="courses[<?php echo esc_attr($key); ?>][other_label]" value="<?php echo esc_attr($course['other_label'] ?? __('Other Fees', 'beauty-school-calculator')); ?>" class="regular-text" /></td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Other Price ($)', 'beauty-school-calculator'); ?></th>
                    <td><input type="number" name="courses[<?php echo esc_attr($key); ?>][other_price]" value="<?php echo esc_attr($course['other_price'] ?? 0); ?>" min="0" /></td>
                </tr>
            </table>
            <?php endforeach; ?>
            
            <?php submit_button(); ?>
        </form>
        
        <h2><?php esc_html_e('Usage', 'beauty-school-calculator'); ?></h2>
        <p><?php esc_html_e('Use the shortcode', 'beauty-school-calculator'); ?> <code>[beauty_calculator]</code> <?php esc_html_e('to display the calculator on any page or post.', 'beauty-school-calculator'); ?></p>
    </div>
    <?php
}

/**
 * Calculator form template (templates/calculator-form.php)
 */
function bsc_render_calculator_template($courses, $fafsa_enabled, $atts) {
    $wrapper_class = 'bsc-calculator-container';
    if (!empty($atts['class'])) {
        $wrapper_class .= ' ' . esc_attr($atts['class']);
    }
    ?>
    <div class="<?php echo esc_attr($wrapper_class); ?>" id="bsc-calculator">
        <h3><?php echo esc_html($atts['title']); ?></h3>

<?php
/**
 * Plugin Name: Office Utilities
 * Plugin URI:  https://example.com
 * Description: Office management plugin for requests and expenses.
 * Version:     1.0.3
 * Author:      Office Team
 * Author URI:  https://example.com
 * Text Domain: office-utilities
 * Domain Path: /languages
 */

defined( 'ABSPATH' ) || exit;

// Autoload dependencies
if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
    require_once __DIR__ . '/vendor/autoload.php';
}

// Manually load Action Scheduler if not present (Fix for 'reading init' error)
if ( ! class_exists( 'ActionScheduler' ) && file_exists( __DIR__ . '/vendor/woocommerce/action-scheduler/action-scheduler.php' ) ) {
    require_once __DIR__ . '/vendor/woocommerce/action-scheduler/action-scheduler.php';
}

final class OfficeUtilities {
    /**
     * Plugin Version
     */
    const VERSION = '1.2.1';

    /**
     * Plugin Instance
     */
    private static $instance = null;

    /**
     * Main Instance
     */
    public static function instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    public function __construct() {
        $this->define_constants();
        $this->includes();
        $this->init_hooks();
    }

    /**
     * Define Constants
     */
    private function define_constants() {
        define( 'OU_VERSION', self::VERSION );
        define( 'OU_FILE', __FILE__ );
        define( 'OU_PATH', plugin_dir_path( __FILE__ ) );
        define( 'OU_URL', plugin_dir_url( __FILE__ ) );
        define( 'OU_ASSETS', OU_URL . 'assets/' );
    }

    /**
     * Include required files
     */
    private function includes() {
        // Will include core classes here
        require_once OU_PATH . 'app/Common/Database/Migrations.php';
        require_once OU_PATH . 'app/Http/Routes/Api.php';
    }

    /**
     * Initialize Hooks
     */
    private function init_hooks() {
        // Version Check & Migration
        $installed_version = get_option( 'office_utilities_version' );
        if ( $installed_version !== '1.2.0' ) {
            \App\Common\Database\Migrations::run();
            update_option( 'office_utilities_version', '1.2.0' );
        }

        register_activation_hook( __FILE__, [ $this, 'activate' ] );
        register_deactivation_hook( __FILE__, [ $this, 'deactivate' ] );
        add_action( 'admin_menu', [ $this, 'register_admin_menu' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
        
        // Register Action Scheduler Hook
        add_action( 'ou_daily_reminder_check', [ $this, 'check_reminders' ] );
        
        // Ensure Recurring Action is Scheduled (Run once on admin init to fix missing schedule)
        add_action( 'admin_init', function() {
            if ( function_exists( 'as_next_scheduled_action' ) && false === as_next_scheduled_action( 'ou_daily_reminder_check' ) ) {
                as_schedule_recurring_action( strtotime( 'tomorrow 09:00:00' ), DAY_IN_SECONDS, 'ou_daily_reminder_check' );
            }
        });
    }
    
    /**
     * Check Reminders (Recurring Action Scheduler)
     */
    public function check_reminders() {
        global $wpdb;
        $now = current_time( 'mysql' );

        // 1. Check Requests
        $req_table = $wpdb->prefix . 'ou_requests';
        $requests = $wpdb->get_results( $wpdb->prepare( 
            "SELECT * FROM $req_table WHERE status = 'pending' AND reminder_date IS NOT NULL AND reminder_date <= %s", 
            $now 
        ) );

        if ( $requests ) {
            foreach ( $requests as $request ) {
                \App\Services\NotificationService::send_reminder( $request, 'request' );
                $wpdb->update( $req_table, [ 'reminder_date' => null ], [ 'id' => $request->id ] );
            }
        }

        // 2. Check Expenses
        $exp_table = $wpdb->prefix . 'ou_expenses';
        $expenses = $wpdb->get_results( $wpdb->prepare( 
            "SELECT * FROM $exp_table WHERE status = 'pending' AND reminder_date IS NOT NULL AND reminder_date <= %s", 
            $now 
        ) );

        if ( $expenses ) {
            foreach ( $expenses as $expense ) {
                \App\Services\NotificationService::send_reminder( $expense, 'expense' );
                $wpdb->update( $exp_table, [ 'reminder_date' => null ], [ 'id' => $expense->id ] );
            }
        }
    }

    /**
     * Activation Hook
     */
    public function activate() {
        // Run migrations
        \App\Common\Database\Migrations::run();
        
        // Ensure roles are set up if needed
        add_role( 'office_employee', 'Office Employee', [ 'read' => true ] );
    }

    /**
     * Deactivation Hook
     */
    public function deactivate() {
        // Clear scheduled action
        if ( function_exists( 'as_unschedule_action' ) ) {
            as_unschedule_action( 'ou_daily_reminder_check' );
        }
    }

    /**
     * Register Admin Menu
     */
    public function register_admin_menu() {
        $user = wp_get_current_user();
        $is_member = get_user_meta( $user->ID, 'ou_is_member', true );
        
        // Allow access if user is Admin OR is a Team Member
        if ( current_user_can( 'manage_options' ) || $is_member ) {
            add_menu_page(
                __( 'Office Utilities', 'office-utilities' ),
                __( 'Office Utilities', 'office-utilities' ),
                'read', // Allow subscribers to see it (we restrict logic inside app)
                'office-utilities',
                [ $this, 'render_app' ],
                'dashicons-building',
                6
            );
        }
    }

    /**
     * Enqueue Scripts
     */
    public function enqueue_scripts( $hook ) {
        if ( $hook !== 'toplevel_page_office-utilities' ) {
            return;
        }

        $script_handle = 'office-utilities-app';
        $manifest_path = OU_PATH . 'assets/dist/.vite/manifest.json';
        
        $js_file = 'assets/dist/app.js';
        $css_file = 'assets/dist/app.css';

        if ( file_exists( $manifest_path ) ) {
            $manifest = json_decode( file_get_contents( $manifest_path ), true );
            if ( isset( $manifest['resources/js/app.js'] ) ) {
                $js_file = 'assets/dist/' . $manifest['resources/js/app.js']['file'];
                if ( ! empty( $manifest['resources/js/app.js']['css'] ) ) {
                    $css_file = 'assets/dist/' . $manifest['resources/js/app.js']['css'][0];
                }
            }
        }

        wp_enqueue_script( $script_handle, OU_URL . $js_file, [], self::VERSION, true );
        
        if ( $css_file ) {
            wp_enqueue_style( $script_handle, OU_URL . $css_file, [], self::VERSION );
        }

        wp_localize_script( $script_handle, 'OfficeUtilities', [
            'root'  => esc_url_raw( rest_url( 'office-utilities/v1' ) ),
            'nonce' => wp_create_nonce( 'wp_rest' ),
            'user' => [ // Changed from currentUser to user to match frontend App.vue expectation
                'id' => get_current_user_id(),
                'name' => wp_get_current_user()->display_name,
                'isAdmin' => current_user_can( 'manage_options' ),
                'isMember' => (bool) get_user_meta( get_current_user_id(), 'ou_is_member', true )
            ],
            'settings' => [
                'remindersEnabled' => get_option( 'ou_reminders_enabled', '0' ) === '1'
            ]
        ] );
    }

    /**
     * Render App
     */
    public function render_app() {
        echo '<div id="office-utilities-app"></div>';
    }
}

// Kick it off
OfficeUtilities::instance();

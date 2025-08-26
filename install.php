<?php
/**
 * Installation script for MemberPress Bulk Invoice Generator
 * 
 * This script verifies all dependencies and requirements before the plugin can be used.
 * It should be run once after plugin activation to ensure everything is set up correctly.
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    die( 'You are not allowed to call this page directly.' );
}

class MPBIG_Installer {
    
    private $errors = array();
    private $warnings = array();
    private $success = array();
    
    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_installer_page' ) );
        add_action( 'admin_init', array( $this, 'run_installation_check' ) );
    }
    
    /**
     * Add installer page to admin menu
     */
    public function add_installer_page() {
        add_submenu_page(
            'memberpress',
            __( 'Bulk Invoice Generator - Installation', 'memberpress-bulk-invoice-generator' ),
            __( 'Installation Check', 'memberpress-bulk-invoice-generator' ),
            'manage_options',
            'memberpress-bulk-invoice-installer',
            array( $this, 'installer_page' )
        );
    }
    
    /**
     * Run installation check
     */
    public function run_installation_check() {
        if ( isset( $_GET['page'] ) && $_GET['page'] === 'memberpress-bulk-invoice-installer' ) {
            $this->check_requirements();
        }
    }
    
    /**
     * Check all requirements
     */
    private function check_requirements() {
        // Check WordPress version
        if ( version_compare( get_bloginfo( 'version' ), '5.0', '<' ) ) {
            $this->errors[] = __( 'WordPress 5.0 or higher is required. Current version: ', 'memberpress-bulk-invoice-generator' ) . get_bloginfo( 'version' );
        } else {
            $this->success[] = __( 'WordPress version is compatible: ', 'memberpress-bulk-invoice-generator' ) . get_bloginfo( 'version' );
        }
        
        // Check PHP version
        if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
            $this->errors[] = __( 'PHP 7.4 or higher is required. Current version: ', 'memberpress-bulk-invoice-generator' ) . PHP_VERSION;
        } else {
            $this->success[] = __( 'PHP version is compatible: ', 'memberpress-bulk-invoice-generator' ) . PHP_VERSION;
        }
        
        // Check if MemberPress is active
        if ( ! function_exists( 'is_plugin_active' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        
        if ( ! is_plugin_active( 'memberpress/memberpress.php' ) && ! defined( 'MEPR_PLUGIN_NAME' ) ) {
            $this->errors[] = __( 'MemberPress plugin is not active. Please activate MemberPress first.', 'memberpress-bulk-invoice-generator' );
        } else {
            $this->success[] = __( 'MemberPress plugin is active.', 'memberpress-bulk-invoice-generator' );
        }
        
        // Check if MemberPress PDF Invoice add-on is active
        if ( ! is_plugin_active( 'memberpress-pdf-invoice/memberpress-pdf-invoice.php' ) ) {
            $this->errors[] = __( 'MemberPress PDF Invoice add-on is not active. Please install and activate the PDF Invoice add-on.', 'memberpress-bulk-invoice-generator' );
        } else {
            $this->success[] = __( 'MemberPress PDF Invoice add-on is active.', 'memberpress-bulk-invoice-generator' );
        }
        
        // Check PHP ZipArchive extension
        if ( ! class_exists( 'ZipArchive' ) ) {
            $this->warnings[] = __( 'PHP ZipArchive extension is not available. ZIP file creation will not work.', 'memberpress-bulk-invoice-generator' );
        } else {
            $this->success[] = __( 'PHP ZipArchive extension is available.', 'memberpress-bulk-invoice-generator' );
        }
        
        // Check upload directory permissions
        $upload_dir = wp_upload_dir();
        $mepr_dir = $upload_dir['basedir'] . '/mepr/mpdf/';
        
        if ( ! is_dir( $mepr_dir ) ) {
            if ( ! wp_mkdir_p( $mepr_dir ) ) {
                $this->errors[] = __( 'Cannot create the required directory: ', 'memberpress-bulk-invoice-generator' ) . $mepr_dir;
            } else {
                $this->success[] = __( 'Created required directory: ', 'memberpress-bulk-invoice-generator' ) . $mepr_dir;
            }
        } else {
            if ( ! is_writable( $mepr_dir ) ) {
                $this->warnings[] = __( 'Directory is not writable: ', 'memberpress-bulk-invoice-generator' ) . $mepr_dir;
            } else {
                $this->success[] = __( 'Directory is writable: ', 'memberpress-bulk-invoice-generator' ) . $mepr_dir;
            }
        }
        
        // Check database table
        global $wpdb;
        $table = $wpdb->prefix . 'mepr_transactions';
        $table_exists = $wpdb->get_var( "SHOW TABLES LIKE '$table'" ) === $table;
        
        if ( ! $table_exists ) {
            $this->errors[] = __( 'MemberPress transactions table not found. Please ensure MemberPress is properly installed.', 'memberpress-bulk-invoice-generator' );
        } else {
            $this->success[] = __( 'MemberPress transactions table exists.', 'memberpress-bulk-invoice-generator' );
        }
        
        // Check memory limit
        $memory_limit = ini_get( 'memory_limit' );
        $memory_limit_bytes = wp_convert_hr_to_bytes( $memory_limit );
        
        if ( $memory_limit_bytes < 128 * 1024 * 1024 ) { // 128MB
            $this->warnings[] = __( 'Memory limit is low (', 'memberpress-bulk-invoice-generator' ) . $memory_limit . __( '). Consider increasing it for large datasets.', 'memberpress-bulk-invoice-generator' );
        } else {
            $this->success[] = __( 'Memory limit is adequate: ', 'memberpress-bulk-invoice-generator' ) . $memory_limit;
        }
        
        // Check max execution time
        $max_execution_time = ini_get( 'max_execution_time' );
        if ( $max_execution_time > 0 && $max_execution_time < 300 ) { // 5 minutes
            $this->warnings[] = __( 'Max execution time is low (', 'memberpress-bulk-invoice-generator' ) . $max_execution_time . __( ' seconds). Consider increasing it for large datasets.', 'memberpress-bulk-invoice-generator' );
        } else {
            $this->success[] = __( 'Max execution time is adequate: ', 'memberpress-bulk-invoice-generator' ) . $max_execution_time . __( ' seconds', 'memberpress-bulk-invoice-generator' );
        }
    }
    
    /**
     * Installer page content
     */
    public function installer_page() {
        ?>
        <div class="wrap">
            <h1><?php _e( 'MemberPress Bulk Invoice Generator - Installation Check', 'memberpress-bulk-invoice-generator' ); ?></h1>
            
            <div class="mpbig-installer-container">
                <div class="mpbig-installer-card">
                    <h2><?php _e( 'System Requirements Check', 'memberpress-bulk-invoice-generator' ); ?></h2>
                    
                    <?php if ( ! empty( $this->errors ) ) : ?>
                        <div class="mpbig-notice mpbig-notice-error">
                            <h3><?php _e( 'Critical Issues Found:', 'memberpress-bulk-invoice-generator' ); ?></h3>
                            <ul>
                                <?php foreach ( $this->errors as $error ) : ?>
                                    <li><?php echo esc_html( $error ); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ( ! empty( $this->warnings ) ) : ?>
                        <div class="mpbig-notice mpbig-notice-warning">
                            <h3><?php _e( 'Warnings:', 'memberpress-bulk-invoice-generator' ); ?></h3>
                            <ul>
                                <?php foreach ( $this->warnings as $warning ) : ?>
                                    <li><?php echo esc_html( $warning ); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ( ! empty( $this->success ) ) : ?>
                        <div class="mpbig-notice mpbig-notice-success">
                            <h3><?php _e( 'All Checks Passed:', 'memberpress-bulk-invoice-generator' ); ?></h3>
                            <ul>
                                <?php foreach ( $this->success as $success ) : ?>
                                    <li><?php echo esc_html( $success ); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    
                    <div class="mpbig-installer-actions">
                        <?php if ( empty( $this->errors ) ) : ?>
                            <a href="<?php echo admin_url( 'admin.php?page=memberpress-bulk-invoice-generator' ); ?>" class="mpbig-button mpbig-button-primary">
                                <?php _e( 'Go to Bulk Invoice Generator', 'memberpress-bulk-invoice-generator' ); ?>
                            </a>
                        <?php else : ?>
                            <p class="mpbig-installer-error-message">
                                <?php _e( 'Please fix the critical issues above before using the plugin.', 'memberpress-bulk-invoice-generator' ); ?>
                            </p>
                        <?php endif; ?>
                        
                        <a href="<?php echo admin_url( 'plugins.php' ); ?>" class="mpbig-button mpbig-button-secondary">
                            <?php _e( 'Back to Plugins', 'memberpress-bulk-invoice-generator' ); ?>
                        </a>
                    </div>
                </div>
                
                <div class="mpbig-installer-card">
                    <h2><?php _e( 'Next Steps', 'memberpress-bulk-invoice-generator' ); ?></h2>
                    <ol>
                        <li><?php _e( 'Ensure all requirements are met (no critical errors above)', 'memberpress-bulk-invoice-generator' ); ?></li>
                        <li><?php _e( 'Click "Go to Bulk Invoice Generator" to access the tool', 'memberpress-bulk-invoice-generator' ); ?></li>
                        <li><?php _e( 'Review the transaction statistics on the main page', 'memberpress-bulk-invoice-generator' ); ?></li>
                        <li><?php _e( 'Choose your generation options and start creating invoices', 'memberpress-bulk-invoice-generator' ); ?></li>
                        <li><?php _e( 'Download the generated files before running the process again', 'memberpress-bulk-invoice-generator' ); ?></li>
                    </ol>
                </div>
            </div>
        </div>
        
        <style>
        .mpbig-installer-container {
            max-width: 800px;
            margin: 20px 0;
        }
        
        .mpbig-installer-card {
            background: #ffffff;
            border: 1px solid #e1e5e9;
            border-radius: 8px;
            padding: 24px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        
        .mpbig-installer-card h2 {
            margin-top: 0;
            color: #1d2327;
        }
        
        .mpbig-installer-actions {
            margin-top: 24px;
            text-align: center;
        }
        
        .mpbig-installer-actions .mpbig-button {
            margin: 0 10px;
        }
        
        .mpbig-installer-error-message {
            color: #dc3545;
            font-weight: 600;
            margin-bottom: 16px;
        }
        
        .mpbig-notice ul {
            margin: 10px 0;
            padding-left: 20px;
        }
        
        .mpbig-notice li {
            margin-bottom: 5px;
        }
        </style>
        <?php
    }
}

// Initialize installer
new MPBIG_Installer();

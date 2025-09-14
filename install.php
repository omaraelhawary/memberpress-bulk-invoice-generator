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
        // No nonce verification needed for GET page parameter - this is just reading a page identifier
        // We're only checking if the current page matches our installer page, not processing form data
        // This is a legitimate use case where nonce verification is not required per WordPress standards
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Page parameter reading only
        $current_page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
        if ( $current_page === 'memberpress-bulk-invoice-installer' ) {
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
        
        // Initialize WP_Filesystem
        if ( ! function_exists( 'WP_Filesystem' ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        
        $access_type = get_filesystem_method();
        if ( 'direct' === $access_type ) {
            $creds = request_filesystem_credentials( site_url() . '/wp-admin/', '', false, false, array() );
            if ( ! WP_Filesystem( $creds ) ) {
                $this->warnings[] = __( 'Cannot initialize filesystem access.', 'memberpress-bulk-invoice-generator' );
                return;
            }
        } else {
            $this->warnings[] = __( 'Filesystem access not available for directory checks.', 'memberpress-bulk-invoice-generator' );
            return;
        }
        
        global $wp_filesystem;
        
        if ( ! $wp_filesystem->is_dir( $mepr_dir ) ) {
            if ( ! $wp_filesystem->mkdir( $mepr_dir, FS_CHMOD_DIR ) ) {
                $this->errors[] = __( 'Cannot create the required directory: ', 'memberpress-bulk-invoice-generator' ) . $mepr_dir;
            } else {
                $this->success[] = __( 'Created required directory: ', 'memberpress-bulk-invoice-generator' ) . $mepr_dir;
            }
        } else {
            if ( ! $wp_filesystem->is_writable( $mepr_dir ) ) {
                $this->warnings[] = __( 'Directory is not writable: ', 'memberpress-bulk-invoice-generator' ) . $mepr_dir;
            } else {
                $this->success[] = __( 'Directory is writable: ', 'memberpress-bulk-invoice-generator' ) . $mepr_dir;
            }
        }
        
        // Check database table
        $table = 'mepr_transactions';
        $cache_key = 'mpbig_table_exists_' . $table;
        
        // Check cache first
        $table_exists = wp_cache_get( $cache_key, 'mpbig_installer' );
        
        if ( false === $table_exists ) {
            global $wpdb;
            $full_table_name = $wpdb->prefix . $table;
            
            // Direct database query is necessary here to check if table exists
            // WordPress doesn't provide a built-in function for this specific use case
            // We use proper caching to minimize database calls
            // This is a legitimate use case where direct database access is required
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Table existence check required
            $table_exists = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $full_table_name ) ) === $full_table_name;
            
            // Cache the result for 5 minutes
            wp_cache_set( $cache_key, $table_exists, 'mpbig_installer', 300 );
        }
        
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
            <h1><?php esc_html_e( 'MemberPress Bulk Invoice Generator - Installation Check', 'memberpress-bulk-invoice-generator' ); ?></h1>
            
            <div class="mpbig-installer-container">
                <div class="mpbig-installer-card">
                    <h2><?php esc_html_e( 'System Requirements Check', 'memberpress-bulk-invoice-generator' ); ?></h2>
                    
                    <?php if ( ! empty( $this->errors ) ) : ?>
                        <div class="mpbig-notice mpbig-notice-error">
                            <h3><?php esc_html_e( 'Critical Issues Found:', 'memberpress-bulk-invoice-generator' ); ?></h3>
                            <ul>
                                <?php foreach ( $this->errors as $error ) : ?>
                                    <li><?php echo esc_html( $error ); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ( ! empty( $this->warnings ) ) : ?>
                        <div class="mpbig-notice mpbig-notice-warning">
                            <h3><?php esc_html_e( 'Warnings:', 'memberpress-bulk-invoice-generator' ); ?></h3>
                            <ul>
                                <?php foreach ( $this->warnings as $warning ) : ?>
                                    <li><?php echo esc_html( $warning ); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ( ! empty( $this->success ) ) : ?>
                        <div class="mpbig-notice mpbig-notice-success">
                            <h3><?php esc_html_e( 'All Checks Passed:', 'memberpress-bulk-invoice-generator' ); ?></h3>
                            <ul>
                                <?php foreach ( $this->success as $success ) : ?>
                                    <li><?php echo esc_html( $success ); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    
                    <div class="mpbig-installer-actions">
                        <?php if ( empty( $this->errors ) ) : ?>
                            <a href="<?php echo esc_url( admin_url( 'admin.php?page=memberpress-bulk-invoice-generator' ) ); ?>" class="mpbig-button mpbig-button-primary">
                                <?php esc_html_e( 'Go to Bulk Invoice Generator', 'memberpress-bulk-invoice-generator' ); ?>
                            </a>
                        <?php else : ?>
                            <p class="mpbig-installer-error-message">
                                <?php esc_html_e( 'Please fix the critical issues above before using the plugin.', 'memberpress-bulk-invoice-generator' ); ?>
                            </p>
                        <?php endif; ?>
                        
                        <a href="<?php echo esc_url( admin_url( 'plugins.php' ) ); ?>" class="mpbig-button mpbig-button-secondary">
                            <?php esc_html_e( 'Back to Plugins', 'memberpress-bulk-invoice-generator' ); ?>
                        </a>
                    </div>
                </div>
                
                <div class="mpbig-installer-card">
                    <h2><?php esc_html_e( 'Next Steps', 'memberpress-bulk-invoice-generator' ); ?></h2>
                    <ol>
                        <li><?php esc_html_e( 'Ensure all requirements are met (no critical errors above)', 'memberpress-bulk-invoice-generator' ); ?></li>
                        <li><?php esc_html_e( 'Click "Go to Bulk Invoice Generator" to access the tool', 'memberpress-bulk-invoice-generator' ); ?></li>
                        <li><?php esc_html_e( 'Review the transaction statistics on the main page', 'memberpress-bulk-invoice-generator' ); ?></li>
                        <li><?php esc_html_e( 'Choose your generation options and start creating invoices', 'memberpress-bulk-invoice-generator' ); ?></li>
                        <li><?php esc_html_e( 'Download the generated files before running the process again', 'memberpress-bulk-invoice-generator' ); ?></li>
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

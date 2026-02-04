<?php
/**
 * Installation script for PDF Invoice Generator for MemberPress
 * 
 * This script verifies all dependencies and requirements before the plugin can be used.
 * It should be run once after plugin activation to ensure everything is set up correctly.
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    die( 'You are not allowed to call this page directly.' );
}

class MPFIG_Installer {
    
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
            __( 'PDF Invoices - Installation', 'pdf-invoice-generator-for-memberpress' ),
            __( 'Installation Check', 'pdf-invoice-generator-for-memberpress' ),
            'manage_options',
            'pdf-invoice-generator-for-memberpress-installer',
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
        if ( $current_page === 'pdf-invoice-generator-for-memberpress-installer' ) {
            $this->check_requirements();
        }
    }
    
    /**
     * Check all requirements
     */
    private function check_requirements() {
        // Check WordPress version
        if ( version_compare( get_bloginfo( 'version' ), '5.0', '<' ) ) {
            $this->errors[] = __( 'WordPress 5.0 or higher is required. Current version: ', 'pdf-invoice-generator-for-memberpress' ) . get_bloginfo( 'version' );
        } else {
            $this->success[] = __( 'WordPress version is compatible: ', 'pdf-invoice-generator-for-memberpress' ) . get_bloginfo( 'version' );
        }
        
        // Check PHP version
        if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
            $this->errors[] = __( 'PHP 7.4 or higher is required. Current version: ', 'pdf-invoice-generator-for-memberpress' ) . PHP_VERSION;
        } else {
            $this->success[] = __( 'PHP version is compatible: ', 'pdf-invoice-generator-for-memberpress' ) . PHP_VERSION;
        }
        
        // Check if MemberPress is active
        if ( ! function_exists( 'is_plugin_active' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        
        if ( ! is_plugin_active( 'memberpress/memberpress.php' ) && ! defined( 'MEPR_PLUGIN_NAME' ) ) {
            $this->errors[] = __( 'MemberPress plugin is not active. Please activate MemberPress first.', 'pdf-invoice-generator-for-memberpress' );
        } else {
            $this->success[] = __( 'MemberPress plugin is active.', 'pdf-invoice-generator-for-memberpress' );
        }
        
        // Check if MemberPress PDF Invoice add-on is active
        if ( ! is_plugin_active( 'memberpress-pdf-invoice/memberpress-pdf-invoice.php' ) ) {
            $this->errors[] = __( 'MemberPress PDF Invoice add-on is not active. Please install and activate the PDF Invoice add-on.', 'pdf-invoice-generator-for-memberpress' );
        } else {
            $this->success[] = __( 'MemberPress PDF Invoice add-on is active.', 'pdf-invoice-generator-for-memberpress' );
        }
        
        // Check PHP ZipArchive extension
        if ( ! class_exists( 'ZipArchive' ) ) {
            $this->warnings[] = __( 'PHP ZipArchive extension is not available. ZIP file creation will not work.', 'pdf-invoice-generator-for-memberpress' );
        } else {
            $this->success[] = __( 'PHP ZipArchive extension is available.', 'pdf-invoice-generator-for-memberpress' );
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
                $this->warnings[] = __( 'Cannot initialize filesystem access.', 'pdf-invoice-generator-for-memberpress' );
                return;
            }
        } else {
            $this->warnings[] = __( 'Filesystem access not available for directory checks.', 'pdf-invoice-generator-for-memberpress' );
            return;
        }
        
        global $wp_filesystem;
        
        if ( ! $wp_filesystem->is_dir( $mepr_dir ) ) {
            if ( ! $wp_filesystem->mkdir( $mepr_dir, FS_CHMOD_DIR ) ) {
                $this->errors[] = __( 'Cannot create the required directory: ', 'pdf-invoice-generator-for-memberpress' ) . $mepr_dir;
            } else {
                $this->success[] = __( 'Created required directory: ', 'pdf-invoice-generator-for-memberpress' ) . $mepr_dir;
            }
        } else {
            if ( ! $wp_filesystem->is_writable( $mepr_dir ) ) {
                $this->warnings[] = __( 'Directory is not writable: ', 'pdf-invoice-generator-for-memberpress' ) . $mepr_dir;
            } else {
                $this->success[] = __( 'Directory is writable: ', 'pdf-invoice-generator-for-memberpress' ) . $mepr_dir;
            }
        }
        
        // Check database table
        $table = 'mepr_transactions';
        $cache_key = 'mpfig_table_exists_' . $table;
        
        // Check cache first
        $table_exists = wp_cache_get( $cache_key, 'mpfig_installer' );
        
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
            wp_cache_set( $cache_key, $table_exists, 'mpfig_installer', 300 );
        }
        
        if ( ! $table_exists ) {
            $this->errors[] = __( 'MemberPress transactions table not found. Please ensure MemberPress is properly installed.', 'pdf-invoice-generator-for-memberpress' );
        } else {
            $this->success[] = __( 'MemberPress transactions table exists.', 'pdf-invoice-generator-for-memberpress' );
        }
        
        // Check memory limit
        $memory_limit = ini_get( 'memory_limit' );
        $memory_limit_bytes = wp_convert_hr_to_bytes( $memory_limit );
        
        if ( $memory_limit_bytes < 128 * 1024 * 1024 ) { // 128MB
            $this->warnings[] = __( 'Memory limit is low (', 'pdf-invoice-generator-for-memberpress' ) . $memory_limit . __( '). Consider increasing it for large datasets.', 'pdf-invoice-generator-for-memberpress' );
        } else {
            $this->success[] = __( 'Memory limit is adequate: ', 'pdf-invoice-generator-for-memberpress' ) . $memory_limit;
        }
        
        // Check max execution time
        $max_execution_time = ini_get( 'max_execution_time' );
        if ( $max_execution_time > 0 && $max_execution_time < 300 ) { // 5 minutes
            $this->warnings[] = __( 'Max execution time is low (', 'pdf-invoice-generator-for-memberpress' ) . $max_execution_time . __( ' seconds). Consider increasing it for large datasets.', 'pdf-invoice-generator-for-memberpress' );
        } else {
            $this->success[] = __( 'Max execution time is adequate: ', 'pdf-invoice-generator-for-memberpress' ) . $max_execution_time . __( ' seconds', 'pdf-invoice-generator-for-memberpress' );
        }
    }
    
    /**
     * Installer page content
     */
    public function installer_page() {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'PDF Invoice Generator for MemberPress - Installation Check', 'pdf-invoice-generator-for-memberpress' ); ?></h1>
            
            <div class="mpfig-installer-container">
                <div class="mpfig-installer-card">
                    <h2><?php esc_html_e( 'System Requirements Check', 'pdf-invoice-generator-for-memberpress' ); ?></h2>
                    
                    <?php if ( ! empty( $this->errors ) ) : ?>
                        <div class="mpfig-notice mpfig-notice-error">
                            <h3><?php esc_html_e( 'Critical Issues Found:', 'pdf-invoice-generator-for-memberpress' ); ?></h3>
                            <ul>
                                <?php foreach ( $this->errors as $error ) : ?>
                                    <li><?php echo esc_html( $error ); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ( ! empty( $this->warnings ) ) : ?>
                        <div class="mpfig-notice mpfig-notice-warning">
                            <h3><?php esc_html_e( 'Warnings:', 'pdf-invoice-generator-for-memberpress' ); ?></h3>
                            <ul>
                                <?php foreach ( $this->warnings as $warning ) : ?>
                                    <li><?php echo esc_html( $warning ); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ( ! empty( $this->success ) ) : ?>
                        <div class="mpfig-notice mpfig-notice-success">
                            <h3><?php esc_html_e( 'All Checks Passed:', 'pdf-invoice-generator-for-memberpress' ); ?></h3>
                            <ul>
                                <?php foreach ( $this->success as $success ) : ?>
                                    <li><?php echo esc_html( $success ); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    
                    <div class="mpfig-installer-actions">
                        <?php if ( empty( $this->errors ) ) : ?>
                            <a href="<?php echo esc_url( admin_url( 'admin.php?page=pdf-invoice-generator-for-memberpress' ) ); ?>" class="mpfig-button mpfig-button-primary">
                                <?php esc_html_e( 'Go to PDF Invoices', 'pdf-invoice-generator-for-memberpress' ); ?>
                            </a>
                        <?php else : ?>
                            <p class="mpfig-installer-error-message">
                                <?php esc_html_e( 'Please fix the critical issues above before using the plugin.', 'pdf-invoice-generator-for-memberpress' ); ?>
                            </p>
                        <?php endif; ?>
                        
                        <a href="<?php echo esc_url( admin_url( 'plugins.php' ) ); ?>" class="mpfig-button mpfig-button-secondary">
                            <?php esc_html_e( 'Back to Plugins', 'pdf-invoice-generator-for-memberpress' ); ?>
                        </a>
                    </div>
                </div>
                
                <div class="mpfig-installer-card">
                    <h2><?php esc_html_e( 'Next Steps', 'pdf-invoice-generator-for-memberpress' ); ?></h2>
                    <ol>
                        <li><?php esc_html_e( 'Ensure all requirements are met (no critical errors above)', 'pdf-invoice-generator-for-memberpress' ); ?></li>
                        <li><?php esc_html_e( 'Click "Go to PDF Invoices" to access the tool', 'pdf-invoice-generator-for-memberpress' ); ?></li>
                        <li><?php esc_html_e( 'Review the transaction statistics on the main page', 'pdf-invoice-generator-for-memberpress' ); ?></li>
                        <li><?php esc_html_e( 'Choose your generation options and start creating invoices', 'pdf-invoice-generator-for-memberpress' ); ?></li>
                        <li><?php esc_html_e( 'Download the generated files before running the process again', 'pdf-invoice-generator-for-memberpress' ); ?></li>
                    </ol>
                </div>
            </div>
        </div>
        
        <style>
        .mpfig-installer-container {
            max-width: 800px;
            margin: 20px 0;
        }
        
        .mpfig-installer-card {
            background: #ffffff;
            border: 1px solid #e1e5e9;
            border-radius: 8px;
            padding: 24px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        
        .mpfig-installer-card h2 {
            margin-top: 0;
            color: #1d2327;
        }
        
        .mpfig-installer-actions {
            margin-top: 24px;
            text-align: center;
        }
        
        .mpfig-installer-actions .mpfig-button {
            margin: 0 10px;
        }
        
        .mpfig-installer-error-message {
            color: #dc3545;
            font-weight: 600;
            margin-bottom: 16px;
        }
        
        .mpfig-notice ul {
            margin: 10px 0;
            padding-left: 20px;
        }
        
        .mpfig-notice li {
            margin-bottom: 5px;
        }
        </style>
        <?php
    }
}

// Initialize installer
new MPFIG_Installer();

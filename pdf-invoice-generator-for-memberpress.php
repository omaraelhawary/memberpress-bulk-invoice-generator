<?php
/*
Plugin Name: PDF Invoice Generator for MemberPress
Plugin URI: https://github.com/omaraelhawary/pdf-invoice-generator-for-memberpress
Description: Generate PDF invoices for MemberPress transactions with a user-friendly interface
Version: 1.0.2
Author: Omar ElHawary
Author URI: https://www.linkedin.com/in/omaraelhawary/
Text Domain: pdf-invoice-generator-for-memberpress
License: GPL v2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Requires at least: 5.0
Tested up to: 6.8
Requires PHP: 7.4
Copyright: 2025
*/

if ( ! defined( 'ABSPATH' ) ) {
	die( 'You are not allowed to call this page directly.' );
}

// Let's run the plugin
add_action( 'plugins_loaded', function() {
  if ( ! function_exists( 'is_plugin_active' ) ) {
    require_once ABSPATH . 'wp-admin/includes/plugin.php';
  }

  // Bail if MemberPress is not active
  if ( ! is_plugin_active( 'memberpress/memberpress.php' ) && ! defined( 'MEPR_PLUGIN_NAME' ) ) {
    return;
  }

  // Bail if MemberPress PDF Invoice add-on is not active
  if ( ! is_plugin_active( 'memberpress-pdf-invoice/memberpress-pdf-invoice.php' ) ) {
    return;
  }

	$plugin_data = get_plugin_data( __FILE__, false, false );

  // Define useful constants
  define( 'MPFIG_VERSION', $plugin_data['Version'] ?? '1.0.0' );
  define( 'MPFIG_SLUG', 'pdf-invoice-generator-for-memberpress' );
  define( 'MPFIG_FILE', MPFIG_SLUG . '/pdf-invoice-generator-for-memberpress.php' );
  define( 'MPFIG_PATH', plugin_dir_path( __FILE__ ) );
  define( 'MPFIG_URL', plugin_dir_url( __FILE__ ) );
  define( 'MPFIG_ADMIN_SLUG', 'pdf-invoice-generator-for-memberpress' );

  // Run the plugin
  new MPPdfInvoiceGenerator();
} );

/**
 * Main plugin class
 */
class MPPdfInvoiceGenerator {

  private $batch_size = 10; // Number of transactions to process per batch
  private $progress_key = 'mpfig_progress';

  public function __construct() {
    add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
    add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
    add_action( 'wp_ajax_mpfig_generate_invoices', array( $this, 'ajax_generate_invoices' ) );
    add_action( 'wp_ajax_mpfig_process_batch', array( $this, 'ajax_process_batch' ) );
    add_action( 'wp_ajax_mpfig_create_zip', array( $this, 'ajax_create_zip' ) );
    add_action( 'wp_ajax_mpfig_get_progress', array( $this, 'ajax_get_progress' ) );
    add_action( 'wp_ajax_mpfig_empty_folder', array( $this, 'ajax_empty_folder' ) );
    add_action( 'wp_ajax_mpfig_download_files', array( $this, 'ajax_download_files' ) );
    add_action( 'admin_notices', array( $this, 'admin_notices' ) );
    
    // Clean up old progress data on init (runs on every page load, but checks timestamp)
    add_action( 'init', array( $this, 'cleanup_old_progress' ) );
    // Also schedule a proper WordPress cron event for cleanup
    add_action( 'mpfig_cleanup_progress', array( $this, 'cleanup_old_progress' ) );
    add_action( 'admin_init', array( $this, 'schedule_cleanup_event' ) );
    
    // Apply filter to batch size
    $this->batch_size = apply_filters( 'mpfig_batch_size', $this->batch_size );
  }

  /**
   * Add admin menu
   */
  public function add_admin_menu() {
    add_submenu_page(
      'memberpress',
__( 'PDF Invoices', 'pdf-invoice-generator-for-memberpress' ),
    __( 'PDF Invoices', 'pdf-invoice-generator-for-memberpress' ),
      'manage_options',
      MPFIG_ADMIN_SLUG,
      array( $this, 'admin_page' )
    );
  }

  /**
   * Enqueue scripts and styles
   */
	public function enqueue_scripts( $hook ) {
		if ( 'memberpress_page_' . MPFIG_ADMIN_SLUG !== $hook ) {
			return;
		}

    wp_enqueue_script( 'jquery-ui-datepicker' );
    wp_enqueue_style( 'wp-jquery-ui-dialog' );
    // Use minified files in production, development files otherwise
    $css_file = defined( 'WP_DEBUG' ) && WP_DEBUG ? 'assets/css/admin.css' : 'assets/css/admin.min.css';
    $js_file = defined( 'WP_DEBUG' ) && WP_DEBUG ? 'assets/js/admin.js' : 'assets/js/admin.min.js';
    
    wp_enqueue_style( 'mpfig-admin', MPFIG_URL . $css_file, array(), MPFIG_VERSION );
    wp_enqueue_script( 'mpfig-admin', MPFIG_URL . $js_file, array( 'jquery', 'jquery-ui-datepicker' ), MPFIG_VERSION, true );
    wp_localize_script( 'mpfig-admin', 'mpfig_ajax', array(
      'ajax_url' => admin_url( 'admin-ajax.php' ),
      'nonce' => wp_create_nonce( 'mpfig_nonce' ),
      'generating' => __( 'Generating invoices...', 'pdf-invoice-generator-for-memberpress' ),
      'success' => __( 'Invoices generated successfully!', 'pdf-invoice-generator-for-memberpress' ),
      'error' => __( 'Error generating invoices.', 'pdf-invoice-generator-for-memberpress' ),
      'batch_size' => $this->get_batch_size(),
      'creating_zip' => __( 'Creating ZIP file...', 'pdf-invoice-generator-for-memberpress' ),
      'zip_created' => __( 'ZIP file created successfully!', 'pdf-invoice-generator-for-memberpress' ),
      'zip_error' => __( 'Error creating ZIP file.', 'pdf-invoice-generator-for-memberpress' )
    ) );
  }

  /**
   * Admin page content
   */
  public function admin_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
      return;
    }

    // Get transaction statistics
    $stats = $this->get_transaction_stats();
    ?>
    <div class="wrap mpfig-container">
      <h1><?php esc_html_e( 'PDF Invoice Generator for MemberPress', 'pdf-invoice-generator-for-memberpress' ); ?></h1>
      
      <div class="mpfig-notice mpfig-notice-info">
        <p><?php esc_html_e( 'This tool allows you to generate PDF invoices for MemberPress transactions in bulk. Make sure to download the generated files before running the process again, as they will be overwritten.', 'pdf-invoice-generator-for-memberpress' ); ?></p>
      </div>

      <!-- Statistics Card -->
      <div class="mpfig-card mpfig-stats">
        <h2><?php esc_html_e( 'Transaction Statistics', 'pdf-invoice-generator-for-memberpress' ); ?></h2>
        <div class="mpfig-stats-grid">
          <div class="mpfig-stat-item">
            <span class="mpfig-stat-number"><?php echo number_format( (int) $stats['total'] ); ?></span>
            <span class="mpfig-stat-label"><?php esc_html_e( 'Total Transactions', 'pdf-invoice-generator-for-memberpress' ); ?></span>
          </div>
          <div class="mpfig-stat-item">
            <span class="mpfig-stat-number"><?php echo number_format( (int) $stats['completed'] ); ?></span>
            <span class="mpfig-stat-label"><?php esc_html_e( 'Completed', 'pdf-invoice-generator-for-memberpress' ); ?></span>
          </div>
          <div class="mpfig-stat-item">
            <span class="mpfig-stat-number"><?php echo number_format( (int) $stats['pending'] ); ?></span>
            <span class="mpfig-stat-label"><?php esc_html_e( 'Pending', 'pdf-invoice-generator-for-memberpress' ); ?></span>
          </div>
          <div class="mpfig-stat-item">
            <span class="mpfig-stat-number"><?php echo number_format( (int) $stats['refunded'] ); ?></span>
            <span class="mpfig-stat-label"><?php esc_html_e( 'Refunded', 'pdf-invoice-generator-for-memberpress' ); ?></span>
          </div>
          <?php if ( (int) $stats['other'] > 0 ) : ?>
          <div class="mpfig-stat-item">
            <span class="mpfig-stat-number"><?php echo number_format( (int) $stats['other'] ); ?></span>
            <span class="mpfig-stat-label"><?php esc_html_e( 'Other', 'pdf-invoice-generator-for-memberpress' ); ?></span>
          </div>
          <?php endif; ?>
        </div>


      </div>

      <!-- Generate Invoices Card -->
      <div class="mpfig-card mpfig-options">
        <h2><?php esc_html_e( 'Generate Invoices', 'pdf-invoice-generator-for-memberpress' ); ?></h2>
        
        <form id="mpfig-form">
          <!-- Generation Settings Section -->
          <div class="mpfig-form-section">
            <h3 class="mpfig-section-title"><?php esc_html_e( 'Generation Settings', 'pdf-invoice-generator-for-memberpress' ); ?></h3>
            
            <div class="mpfig-form-row">
              <div class="mpfig-form-group mpfig-form-group-full">
                <label for="mpfig-type"><?php esc_html_e( 'Generation Type', 'pdf-invoice-generator-for-memberpress' ); ?></label>
                <select id="mpfig-type" name="type" class="mpfig-form-control mpfig-select">
                  <option value="all"><?php esc_html_e( 'Generate All Invoices', 'pdf-invoice-generator-for-memberpress' ); ?></option>
                  <option value="period"><?php esc_html_e( 'Generate Invoices for Specific Period', 'pdf-invoice-generator-for-memberpress' ); ?></option>
                </select>
              </div>
            </div>
            
            <div class="mpfig-form-row mpfig-hidden" id="mpfig-period-options">
              <div class="mpfig-form-group mpfig-form-group-half">
                <label for="mpfig-start-date"><?php esc_html_e( 'Start Date', 'pdf-invoice-generator-for-memberpress' ); ?></label>
                <input type="text" id="mpfig-start-date" name="start_date" class="mpfig-form-control mpfig-date-input mpfig-datepicker" placeholder="YYYY-MM-DD" />
              </div>
              
              <div class="mpfig-form-group mpfig-form-group-half">
                <label for="mpfig-end-date"><?php esc_html_e( 'End Date', 'pdf-invoice-generator-for-memberpress' ); ?></label>
                <input type="text" id="mpfig-end-date" name="end_date" class="mpfig-form-control mpfig-date-input mpfig-datepicker" placeholder="YYYY-MM-DD" />
              </div>
            </div>
          </div>

          <!-- Filter Settings Section -->
          <div class="mpfig-form-section">
            <h3 class="mpfig-section-title"><?php esc_html_e( 'Filter Settings', 'pdf-invoice-generator-for-memberpress' ); ?></h3>
            
            <div class="mpfig-form-row">
              <div class="mpfig-form-group mpfig-form-group-half">
                <label><?php esc_html_e( 'Membership Filter', 'pdf-invoice-generator-for-memberpress' ); ?></label>
                <select name="membership_id" class="mpfig-form-control mpfig-select">
                  <option value=""><?php esc_html_e( 'All Memberships', 'pdf-invoice-generator-for-memberpress' ); ?></option>
                  <?php
                  $memberships = get_posts( array(
                    'post_type' => 'memberpressproduct',
                    'numberposts' => -1,
                    'post_status' => 'publish',
                    'orderby' => 'title',
                    'order' => 'ASC'
                  ) );
                  
                  foreach ( $memberships as $membership ) {
                    echo '<option value="' . esc_attr( $membership->ID ) . '">' . esc_html( $membership->post_title ) . '</option>';
                  }
                  ?>
                </select>
              </div>

              <div class="mpfig-form-group mpfig-form-group-half">
                <label for="mpfig-customer-email"><?php esc_html_e( 'Customer Email Filter', 'pdf-invoice-generator-for-memberpress' ); ?></label>
                <input type="email" id="mpfig-customer-email" name="customer_email" class="mpfig-form-control" placeholder="<?php esc_attr_e( 'Enter customer email address (optional)', 'pdf-invoice-generator-for-memberpress' ); ?>" />
                <small class="mpfig-form-help"><?php esc_html_e( 'Leave empty to include all customers. Enter a specific email to filter transactions for that customer only.', 'pdf-invoice-generator-for-memberpress' ); ?></small>
              </div>
            </div>

            <div class="mpfig-form-row">
              <div class="mpfig-form-group mpfig-form-group-full">
                <label><?php esc_html_e( 'Transaction Status', 'pdf-invoice-generator-for-memberpress' ); ?></label>
                <div class="mpfig-checkbox-group">
                  <div class="mpfig-checkbox-item">
                    <input type="checkbox" name="status[]" value="complete" id="status-complete" checked />
                    <label for="status-complete"><?php esc_html_e( 'Complete', 'pdf-invoice-generator-for-memberpress' ); ?></label>
                  </div>
                  <div class="mpfig-checkbox-item">
                    <input type="checkbox" name="status[]" value="pending" id="status-pending" />
                    <label for="status-pending"><?php esc_html_e( 'Pending', 'pdf-invoice-generator-for-memberpress' ); ?></label>
                  </div>
                  <div class="mpfig-checkbox-item">
                    <input type="checkbox" name="status[]" value="refunded" id="status-refunded" checked />
                    <label for="status-refunded"><?php esc_html_e( 'Refunded', 'pdf-invoice-generator-for-memberpress' ); ?></label>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Output Settings Section -->
          <div class="mpfig-form-section">
            <h3 class="mpfig-section-title"><?php esc_html_e( 'Output Settings', 'pdf-invoice-generator-for-memberpress' ); ?></h3>
            
            <div class="mpfig-form-row">
              <div class="mpfig-form-group mpfig-form-group-full">
                <div class="mpfig-checkbox-item mpfig-checkbox-item-large">
                  <input type="checkbox" id="mpfig-create-zip" name="create_zip" value="1" checked />
                  <label for="mpfig-create-zip"><?php esc_html_e( 'Automatically create a ZIP file containing all generated PDFs', 'pdf-invoice-generator-for-memberpress' ); ?></label>
                </div>
              </div>
            </div>
          </div>
          
          <!-- Action Section -->
          <div class="mpfig-form-section mpfig-form-section-action">
            <div class="mpfig-form-row">
              <div class="mpfig-form-group mpfig-form-group-full">
                <button type="submit" class="mpfig-button mpfig-button-primary mpfig-button-large" id="mpfig-generate">
                  <span class="mpfig-spinner mpfig-hidden" id="mpfig-spinner"></span>
                  <?php esc_html_e( 'Generate Invoices', 'pdf-invoice-generator-for-memberpress' ); ?>
                </button>
                <span id="mpfig-progress" class="mpfig-hidden">
                  <span id="mpfig-progress-text"></span>
                </span>
              </div>
            </div>
          </div>
        </form>
      </div>

      <!-- Progress Card -->
      <div class="mpfig-card mpfig-progress-container mpfig-hidden" id="mpfig-progress-container">
        <div class="mpfig-progress-header">
          <h2><?php esc_html_e( 'Generation Progress', 'pdf-invoice-generator-for-memberpress' ); ?></h2>
        </div>
        <div class="mpfig-progress-bar">
          <div class="mpfig-progress-fill" id="mpfig-progress-fill"></div>
        </div>
        <div class="mpfig-progress-stats">
          <span id="mpfig-progress-current">0</span> / <span id="mpfig-progress-total">0</span> 
          (<span id="mpfig-progress-percentage">0%</span>)
        </div>
        <div class="mpfig-progress-status" id="mpfig-progress-status"></div>
      </div>

      <!-- Results Card -->
      <div class="mpfig-card mpfig-results mpfig-hidden" id="mpfig-results">
        <h2><?php esc_html_e( 'Generation Results', 'pdf-invoice-generator-for-memberpress' ); ?></h2>
        <div id="mpfig-results-content"></div>
      </div>

      <!-- File Management Card -->
      <div class="mpfig-card mpfig-file-management">
        <h2><?php esc_html_e( 'File Management', 'pdf-invoice-generator-for-memberpress' ); ?></h2>
        
        <div class="mpfig-file-stats">
          <?php
          $pdf_dir = WP_CONTENT_DIR . '/uploads/mepr/mpdf/';
          $file_count = 0;
          $total_size = 0;
          
          if ( is_dir( $pdf_dir ) ) {
            $files = glob( $pdf_dir . '*.pdf' );
            $file_count = count( $files );
            
            foreach ( $files as $file ) {
              $total_size += filesize( $file );
            }
          }
          ?>
          <div class="mpfig-file-stat-item">
            <span class="mpfig-file-stat-number"><?php echo number_format( (int) $file_count ); ?></span>
            <span class="mpfig-file-stat-label"><?php esc_html_e( 'PDF Files', 'pdf-invoice-generator-for-memberpress' ); ?></span>
          </div>
          <div class="mpfig-file-stat-item">
            <span class="mpfig-file-stat-number"><?php echo esc_html( size_format( $total_size, 2 ) ); ?></span>
            <span class="mpfig-file-stat-label"><?php esc_html_e( 'Total Size', 'pdf-invoice-generator-for-memberpress' ); ?></span>
          </div>
        </div>
        
        <div class="mpfig-file-actions">
          <button type="button" class="mpfig-button mpfig-button-primary" id="mpfig-download-files" <?php echo 0 < $file_count ? '' : 'disabled'; ?>>
            <span class="mpfig-spinner mpfig-hidden" id="mpfig-download-spinner"></span>
            <?php esc_html_e( 'Download All Files', 'pdf-invoice-generator-for-memberpress' ); ?>
          </button>
          <button type="button" class="mpfig-button mpfig-button-secondary" id="mpfig-empty-folder">
            <span class="mpfig-spinner mpfig-hidden" id="mpfig-empty-spinner"></span>
            <?php esc_html_e( 'Empty PDF Folder', 'pdf-invoice-generator-for-memberpress' ); ?>
          </button>
          <p class="mpfig-file-warning">
            <?php esc_html_e( 'This will permanently delete all PDF files in the mpdf folder. Make sure you have downloaded any important files first.', 'pdf-invoice-generator-for-memberpress' ); ?>
          </p>
        </div>
      </div>

      <!-- Information Card -->
      <div class="mpfig-card mpfig-info">
        <h2><?php esc_html_e( 'Important Information', 'pdf-invoice-generator-for-memberpress' ); ?></h2>
        <ul>
          <li><?php esc_html_e( 'Generated PDF files will be saved in: wp-content/uploads/mepr/mpdf/', 'pdf-invoice-generator-for-memberpress' ); ?></li>
          <li><?php esc_html_e( 'Download the files before running the process again to avoid overwriting.', 'pdf-invoice-generator-for-memberpress' ); ?></li>
          <li><?php esc_html_e( 'The process uses batch processing to handle large datasets efficiently.', 'pdf-invoice-generator-for-memberpress' ); ?></li>
          <li><?php esc_html_e( 'Only payment transactions with status: Complete, Pending, or Refunded will be processed (excludes confirmation and failed transactions).', 'pdf-invoice-generator-for-memberpress' ); ?></li>
          <li><?php esc_html_e( 'ZIP files are automatically created for easier download and organization.', 'pdf-invoice-generator-for-memberpress' ); ?></li>
        </ul>
      </div>
    </div>
    <?php
  }

  /**
   * Get transaction statistics
   */
  private function get_transaction_stats() {
    $cache_key = 'mpfig_transaction_stats';
    
    // Check cache first
    $stats = wp_cache_get( $cache_key, 'mpfig_stats' );
    
    if ( false === $stats ) {
      global $wpdb;
      
      $stats = array(
        'total' => 0,
        'completed' => 0,
        'pending' => 0,
        'refunded' => 0
      );

      $table = $wpdb->prefix . 'mepr_transactions';
      
      // Validate table name to prevent SQL injection
      $table = preg_replace( '/[^a-zA-Z0-9_]/', '', $table );
      
      // Check if MemberPress transactions table exists
      // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Table existence check required
      $table_exists = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table ) ) === $table;
      
      if ( $table_exists ) {
        // Match MemberPress default behavior: exclude confirmations, non-payment transactions, and failed transactions
        // This matches what's shown in the MemberPress transactions list by default, excluding failed transactions that have no invoices
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- MemberPress transaction counting required
        $stats['total'] = $wpdb->get_var( "SELECT COUNT(*) FROM `{$table}` WHERE txn_type = 'payment' AND status <> 'confirmed' AND status <> 'failed'" );
        
        // Status counts - only count payment transactions that can have invoices, exclude confirmed and failed status
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- MemberPress transaction counting required
        $stats['completed'] = $wpdb->get_var( "SELECT COUNT(*) FROM `{$table}` WHERE txn_type = 'payment' AND status = 'complete'" );
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- MemberPress transaction counting required
        $stats['pending'] = $wpdb->get_var( "SELECT COUNT(*) FROM `{$table}` WHERE txn_type = 'payment' AND status = 'pending'" );
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- MemberPress transaction counting required
        $stats['refunded'] = $wpdb->get_var( "SELECT COUNT(*) FROM `{$table}` WHERE txn_type = 'payment' AND status = 'refunded'" );
        
        // Count other statuses that might have invoices (excluding failed and confirmed)
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- MemberPress transaction counting required
        $stats['other'] = $wpdb->get_var( "SELECT COUNT(*) FROM `{$table}` WHERE txn_type = 'payment' AND status <> 'confirmed' AND status NOT IN ('complete', 'pending', 'refunded', 'failed')" );
      }

      // Cache the results for 5 minutes
      wp_cache_set( $cache_key, $stats, 'mpfig_stats', 300 );
    }

    return $stats;
  }

  /**
   * AJAX handler for generating invoices
   */
  public function ajax_generate_invoices() {
    try {
      if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => __( 'You do not have permission to perform this action.', 'pdf-invoice-generator-for-memberpress' ) ) );
        return;
      }

      if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'mpfig_nonce' ) ) {
        wp_send_json_error( array( 'message' => __( 'Security check failed.', 'pdf-invoice-generator-for-memberpress' ) ) );
        return;
      }

		$type = isset( $_POST['type'] ) ? sanitize_text_field( wp_unslash( $_POST['type'] ) ) : 'all';
		$statuses = isset( $_POST['status'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['status'] ) ) : array( 'complete', 'pending', 'refunded' );
		$create_zip = isset( $_POST['create_zip'] ) && '1' === $_POST['create_zip'];
		$membership_id = isset( $_POST['membership_id'] ) ? intval( $_POST['membership_id'] ) : 0;
		$customer_email = isset( $_POST['customer_email'] ) ? sanitize_email( wp_unslash( $_POST['customer_email'] ) ) : '';

		$start_date = '';
		$end_date = '';

		if ( 'period' === $type ) {
			$start_date = isset( $_POST['start_date'] ) ? sanitize_text_field( wp_unslash( $_POST['start_date'] ) ) . ' 00:00:00' : '';
			$end_date = isset( $_POST['end_date'] ) ? sanitize_text_field( wp_unslash( $_POST['end_date'] ) ) . ' 23:59:59' : '';

			// Validate date format
			if ( ! empty( $start_date ) && ! $this->validate_date( $start_date ) ) {
				wp_send_json_error( array( 'message' => __( 'Invalid start date format. Please use YYYY-MM-DD format.', 'pdf-invoice-generator-for-memberpress' ) ) );
				return;
			}

			if ( ! empty( $end_date ) && ! $this->validate_date( $end_date ) ) {
				wp_send_json_error( array( 'message' => __( 'Invalid end date format. Please use YYYY-MM-DD format.', 'pdf-invoice-generator-for-memberpress' ) ) );
				return;
			}

			if ( ! empty( $start_date ) && ! empty( $end_date ) && strtotime( $start_date ) > strtotime( $end_date ) ) {
				wp_send_json_error( array( 'message' => __( 'Start date must be before end date.', 'pdf-invoice-generator-for-memberpress' ) ) );
				return;
			}
		}

    // Get all transaction IDs that match criteria
    $txn_ids = $this->get_transaction_ids( $type, $statuses, $start_date, $end_date, $membership_id, $customer_email );

    if ( empty( $txn_ids ) ) {
      wp_send_json( array(
        'success' => true,
        'message' => __( 'No transactions found matching the criteria.', 'pdf-invoice-generator-for-memberpress' ),
        'count' => 0
      ) );
    }

    // Initialize progress tracking
    $progress_data = array(
      'total' => count( $txn_ids ),
      'processed' => 0,
      'successful' => 0,
      'errors' => array(),
      'txn_ids' => $txn_ids,
      'create_zip' => $create_zip,
      'start_time' => current_time( 'timestamp' )
    );

    update_option( $this->progress_key, $progress_data );

    wp_send_json( array(
      'success' => true,
      // translators: %d is the number of transactions to process
      'message' => sprintf( __( 'Starting batch processing for %d transactions...', 'pdf-invoice-generator-for-memberpress' ), count( $txn_ids ) ),
      'total' => count( $txn_ids ),
      'batch_size' => $this->get_batch_size()
    ) );
    
    } catch ( Exception $e ) {
      // Log error for debugging
      if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        error_log( 'MPFIG Error in ajax_generate_invoices: ' . $e->getMessage() );
        error_log( 'MPFIG Stack trace: ' . $e->getTraceAsString() );
      }
      wp_send_json_error( array( 'message' => __( 'An error occurred while processing your request: ', 'pdf-invoice-generator-for-memberpress' ) . $e->getMessage() ) );
    }
  }

  /**
   * AJAX handler for processing batches
   */
  public function ajax_process_batch() {
    if ( ! current_user_can( 'manage_options' ) ) {
      wp_die( esc_html__( 'You do not have permission to perform this action.', 'pdf-invoice-generator-for-memberpress' ) );
    }

    if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'mpfig_nonce' ) ) {
      wp_die( esc_html__( 'Security check failed.', 'pdf-invoice-generator-for-memberpress' ) );
    }

    $progress_data = get_option( $this->progress_key, array() );
    
    if ( empty( $progress_data ) || empty( $progress_data['txn_ids'] ) ) {
      wp_send_json( array(
        'success' => false,
        'message' => __( 'No progress data found.', 'pdf-invoice-generator-for-memberpress' )
      ) );
    }

    // Check if PDF Invoice add-on is active
    if ( ! class_exists( 'MePdfInvoicesCtrl' ) ) {
      wp_send_json( array(
        'success' => false,
        'message' => __( 'MemberPress PDF Invoice add-on is not active.', 'pdf-invoice-generator-for-memberpress' )
      ) );
    }

    $invoices_ctrl = new MePdfInvoicesCtrl();
    $batch_size = $this->get_batch_size();
    $processed_in_batch = 0;
    $successful_in_batch = 0;
    $errors_in_batch = array();

		// Process current batch
		while ( $processed_in_batch < $batch_size && ! empty( $progress_data['txn_ids'] ) ) {
			$txn_id = array_shift( $progress_data['txn_ids'] );

			try {
				$txn = new MeprTransaction( $txn_id );

				if ( 0 < $txn->id ) {
					$path = $invoices_ctrl->create_receipt_pdf( $txn );
					if ( $path ) {
						$successful_in_batch++;
					}
				}
			} catch ( Exception $e ) {
				// Log error for debugging
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( 'MPFIG Error generating invoice for transaction ' . $txn_id . ': ' . $e->getMessage() );
					error_log( 'MPFIG Stack trace: ' . $e->getTraceAsString() );
				}
				// translators: %1$d is the transaction ID, %2$s is the error message
				$errors_in_batch[] = sprintf( __( 'Error generating invoice for transaction %1$d: %2$s', 'pdf-invoice-generator-for-memberpress' ), $txn_id, $e->getMessage() );
			}

			$processed_in_batch++;
		}

    // Update progress
    $progress_data['processed'] += $processed_in_batch;
    $progress_data['successful'] += $successful_in_batch;
    $progress_data['errors'] = array_merge( $progress_data['errors'], $errors_in_batch );

    $is_complete = empty( $progress_data['txn_ids'] );
    
    if ( $is_complete ) {
      // Clean up progress data
      delete_option( $this->progress_key );
      
      $message = sprintf( 
        // translators: %d is the number of successfully generated invoices
        __( 'Successfully generated %d invoices.', 'pdf-invoice-generator-for-memberpress' ), 
        $progress_data['successful'] 
      );

      if ( ! empty( $progress_data['errors'] ) ) {
        // translators: %d is the number of errors that occurred
        $message .= ' ' . sprintf( __( '%d errors occurred.', 'pdf-invoice-generator-for-memberpress' ), count( $progress_data['errors'] ) );
      }

      wp_send_json( array(
        'success' => true,
        'complete' => true,
        'message' => $message,
        'processed' => $progress_data['processed'],
        'successful' => $progress_data['successful'],
        'errors' => $progress_data['errors'],
        'create_zip' => $progress_data['create_zip']
      ) );
    } else {
      // Save updated progress
      update_option( $this->progress_key, $progress_data );
      
      wp_send_json( array(
        'success' => true,
        'complete' => false,
        'processed' => $progress_data['processed'],
        'successful' => $progress_data['successful'],
        'errors' => $errors_in_batch,
        'remaining' => count( $progress_data['txn_ids'] )
      ) );
    }
  }

  /**
   * AJAX handler for creating ZIP file
   */
  public function ajax_create_zip() {
    if ( ! current_user_can( 'manage_options' ) ) {
      wp_die( esc_html__( 'You do not have permission to perform this action.', 'pdf-invoice-generator-for-memberpress' ) );
    }

    if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'mpfig_nonce' ) ) {
      wp_die( esc_html__( 'Security check failed.', 'pdf-invoice-generator-for-memberpress' ) );
    }

    $pdf_dir = WP_CONTENT_DIR . '/uploads/mepr/mpdf/';
    
    if ( ! is_dir( $pdf_dir ) ) {
      if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        error_log( 'MPFIG Error: PDF directory not found: ' . $pdf_dir );
      }
      wp_send_json( array(
        'success' => false,
        'message' => __( 'PDF directory not found.', 'pdf-invoice-generator-for-memberpress' )
      ) );
    }

    $zip_file = $this->create_zip_file( $pdf_dir );
    
    if ( $zip_file ) {
      $zip_url = content_url( '/uploads/mepr/mpdf/' . basename( $zip_file ) );
      wp_send_json( array(
        'success' => true,
        'message' => __( 'ZIP file created successfully!', 'pdf-invoice-generator-for-memberpress' ),
        'zip_url' => $zip_url,
        'zip_filename' => basename( $zip_file )
      ) );
    } else {
      if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        error_log( 'MPFIG Error: Failed to create ZIP file in directory: ' . $pdf_dir );
      }
      wp_send_json( array(
        'success' => false,
        'message' => __( 'Failed to create ZIP file.', 'pdf-invoice-generator-for-memberpress' )
      ) );
    }
  }

  /**
   * AJAX handler for getting progress
   */
  public function ajax_get_progress() {
    if ( ! current_user_can( 'manage_options' ) ) {
      wp_die( esc_html__( 'You do not have permission to perform this action.', 'pdf-invoice-generator-for-memberpress' ) );
    }

    $progress_data = get_option( $this->progress_key, array() );
    
    if ( empty( $progress_data ) ) {
      wp_send_json( array(
        'success' => false,
        'message' => __( 'No progress data found.', 'pdf-invoice-generator-for-memberpress' )
      ) );
    }

    wp_send_json( array(
      'success' => true,
      'processed' => $progress_data['processed'],
      'total' => $progress_data['total'],
      'successful' => $progress_data['successful'],
      'errors' => $progress_data['errors']
    ) );
  }

  /**
   * AJAX handler for emptying the PDF folder
   */
  public function ajax_empty_folder() {
    if ( ! current_user_can( 'manage_options' ) ) {
      wp_die( esc_html__( 'You do not have permission to perform this action.', 'pdf-invoice-generator-for-memberpress' ) );
    }

    if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'mpfig_nonce' ) ) {
      wp_die( esc_html__( 'Security check failed.', 'pdf-invoice-generator-for-memberpress' ) );
    }

    $pdf_dir = WP_CONTENT_DIR . '/uploads/mepr/mpdf/';
    
    if ( ! is_dir( $pdf_dir ) ) {
      wp_send_json( array(
        'success' => false,
        'message' => __( 'PDF directory not found.', 'pdf-invoice-generator-for-memberpress' )
      ) );
    }

    $deleted_count = 0;
    $errors = array();

    // Get all PDF files
    $files = glob( $pdf_dir . '*.pdf' );
    
    foreach ( $files as $file ) {
      if ( is_file( $file ) ) {
        if ( wp_delete_file( $file ) ) {
          $deleted_count++;
        } else {
          // translators: %s is the filename that failed to delete
          $errors[] = sprintf( __( 'Failed to delete: %s', 'pdf-invoice-generator-for-memberpress' ), basename( $file ) );
        }
      }
    }

    // Also delete ZIP files
    $zip_files = glob( $pdf_dir . '*.zip' );
    foreach ( $zip_files as $file ) {
      if ( is_file( $file ) ) {
        if ( wp_delete_file( $file ) ) {
          $deleted_count++;
        } else {
          // translators: %s is the filename that failed to delete
          $errors[] = sprintf( __( 'Failed to delete: %s', 'pdf-invoice-generator-for-memberpress' ), basename( $file ) );
        }
      }
    }

    if ( $deleted_count > 0 ) {
      $message = sprintf( 
        // translators: %d is the number of files successfully deleted
        __( 'Successfully deleted %d files from the PDF folder.', 'pdf-invoice-generator-for-memberpress' ), 
        $deleted_count 
      );
      
      if ( ! empty( $errors ) ) {
        // translators: %d is the number of files that could not be deleted
        $message .= ' ' . sprintf( __( '%d files could not be deleted.', 'pdf-invoice-generator-for-memberpress' ), count( $errors ) );
      }

      wp_send_json( array(
        'success' => true,
        'message' => $message,
        'deleted_count' => $deleted_count,
        'errors' => $errors
      ) );
    } else {
      wp_send_json( array(
        'success' => false,
        'message' => __( 'No files found to delete.', 'pdf-invoice-generator-for-memberpress' )
      ) );
    }
  }

  /**
   * AJAX handler for downloading files
   */
  public function ajax_download_files() {
    if ( ! current_user_can( 'manage_options' ) ) {
      wp_die( esc_html__( 'You do not have permission to perform this action.', 'pdf-invoice-generator-for-memberpress' ) );
    }

    if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'mpfig_nonce' ) ) {
      wp_die( esc_html__( 'Security check failed.', 'pdf-invoice-generator-for-memberpress' ) );
    }

    $pdf_dir = WP_CONTENT_DIR . '/uploads/mepr/mpdf/';
    
    if ( ! is_dir( $pdf_dir ) ) {
      wp_send_json( array(
        'success' => false,
        'message' => __( 'PDF directory not found.', 'pdf-invoice-generator-for-memberpress' )
      ) );
    }

    // Check if there are any PDF files
    $pdf_files = glob( $pdf_dir . '*.pdf' );
    if ( empty( $pdf_files ) ) {
      wp_send_json( array(
        'success' => false,
        'message' => __( 'No PDF files found to download.', 'pdf-invoice-generator-for-memberpress' )
      ) );
    }

    // Create ZIP file
    $zip_file = $this->create_zip_file( $pdf_dir );
    
    if ( $zip_file ) {
      $zip_url = content_url( '/uploads/mepr/mpdf/' . basename( $zip_file ) );
      wp_send_json( array(
        'success' => true,
        'message' => __( 'ZIP file created successfully!', 'pdf-invoice-generator-for-memberpress' ),
        'zip_url' => $zip_url,
        'zip_filename' => basename( $zip_file ),
        'file_count' => count( $pdf_files )
      ) );
    } else {
      wp_send_json( array(
        'success' => false,
        'message' => __( 'Failed to create ZIP file.', 'pdf-invoice-generator-for-memberpress' )
      ) );
    }
  }

  /**
   * Validate date format
   */
  private function validate_date( $date ) {
    $d = DateTime::createFromFormat( 'Y-m-d H:i:s', $date );
    return $d && $d->format( 'Y-m-d H:i:s' ) === $date;
  }

  /**
   * Get transaction IDs based on criteria
   */
  private function get_transaction_ids( $type, $statuses, $start_date = '', $end_date = '', $membership_id = 0, $customer_email = '' ) {
    // Create cache key based on parameters
    $cache_key = 'mpfig_transaction_ids_' . md5( serialize( array( $type, $statuses, $start_date, $end_date, $membership_id, $customer_email ) ) );
    
    // Check cache first
    $txn_ids = wp_cache_get( $cache_key, 'mpfig_transactions' );
    
    if ( false === $txn_ids ) {
      global $wpdb;

      $table = $wpdb->prefix . 'mepr_transactions';
      
      // Validate table name to prevent SQL injection - more strict validation
      $table = preg_replace( '/[^a-zA-Z0-9_]/', '', $table );
      
      // Additional safety: ensure table name matches expected pattern
      if ( ! preg_match( '/^[a-zA-Z0-9_]+$/', $table ) || strpos( $table, 'mepr_transactions' ) === false ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
          error_log( 'MPFIG Security: Invalid table name detected: ' . $table );
        }
        return array();
      }
      
      // Check if MemberPress transactions table exists
      // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Table existence check required
      $table_exists = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table ) ) === $table;
      
      if ( $table_exists ) {
        // Validate statuses array
        $valid_statuses = array( 'complete', 'pending', 'refunded', 'confirmed', 'failed' );
        $statuses = array_intersect( $statuses, $valid_statuses );
        
        if ( empty( $statuses ) ) {
          return array();
        }
        
        $status_placeholders = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );
        
        // Build query parts - use esc_sql for table name as additional safety layer
        $table_escaped = esc_sql( $table );
        $base_query = "SELECT t.id FROM `{$table_escaped}` t";
        
        // Add user table join if customer email filter is specified
        if ( ! empty( $customer_email ) ) {
          $users_table = esc_sql( $wpdb->users );
          $base_query .= " INNER JOIN `{$users_table}` u ON t.user_id = u.ID";
        }
        
        // Match MemberPress default behavior: exclude confirmations, non-payment transactions, and failed transactions
        $base_query .= " WHERE t.txn_type = 'payment' AND t.status <> 'confirmed' AND t.status <> 'failed' AND t.status IN ({$status_placeholders})";
        $query_args = $statuses;

        if ( $type === 'period' && ! empty( $start_date ) && ! empty( $end_date ) ) {
          $base_query .= " AND t.created_at > %s AND t.created_at < %s";
          $query_args[] = $start_date;
          $query_args[] = $end_date;
        }

        // Add membership filter if specified
        if ( $membership_id > 0 ) {
          $base_query .= " AND t.product_id = %d";
          $query_args[] = absint( $membership_id );
        }

        // Add customer email filter if specified
        if ( ! empty( $customer_email ) ) {
          $base_query .= " AND u.user_email = %s";
          $query_args[] = sanitize_email( $customer_email );
        }

        $base_query .= " ORDER BY t.created_at ASC";

        // Direct database query is necessary here for transaction ID retrieval
        // WordPress doesn't provide built-in functions for MemberPress transaction queries
        // We use prepared statements and proper caching for security and performance
        // This is a legitimate use case where direct database access is required
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.NotPrepared -- MemberPress transaction queries required
        $results = $wpdb->get_results( $wpdb->prepare( $base_query, $query_args ) );

        if ( $wpdb->last_error ) {
          if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'MPFIG Database Error: ' . $wpdb->last_error );
          }
          return array();
        }

        $txn_ids = array_map( function( $row ) { return absint( $row->id ); }, $results );
      } else {
        $txn_ids = array();
      }
      
      // Cache the results for 2 minutes (shorter cache for dynamic queries)
      wp_cache_set( $cache_key, $txn_ids, 'mpfig_transactions', 120 );
    }

    return $txn_ids;
  }

  /**
   * Create ZIP file from PDF directory
   */
  private function create_zip_file( $pdf_dir ) {
    if ( ! class_exists( 'ZipArchive' ) ) {
      if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        error_log( 'MPFIG Error: ZipArchive class not available' );
      }
      return false;
    }

    $zip_filename = 'memberpress-invoices-' . gmdate( 'Y-m-d-H-i-s' ) . '.zip';
    $zip_path = $pdf_dir . $zip_filename;

    $zip = new ZipArchive();
    
		$result = $zip->open( $zip_path, ZipArchive::CREATE );
		if ( true !== $result ) {
      if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        error_log( 'MPFIG Error: Failed to open ZIP file. Error code: ' . $result . ', Path: ' . $zip_path );
      }
      return false;
    }

    $files = glob( $pdf_dir . '*.pdf' );
    
    if ( empty( $files ) ) {
      if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        error_log( 'MPFIG Warning: No PDF files found to add to ZIP in directory: ' . $pdf_dir );
      }
    }
    
    foreach ( $files as $file ) {
      if ( is_file( $file ) ) {
        $zip->addFile( $file, basename( $file ) );
      }
    }

    $zip->close();
    
    if ( ! file_exists( $zip_path ) ) {
      if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        error_log( 'MPFIG Error: ZIP file was not created at path: ' . $zip_path );
      }
      return false;
    }
    
    return $zip_path;
  }

  /**
   * Clean up old progress data
   */
  public function cleanup_old_progress() {
    // Clean up progress data older than 1 hour
    $progress_data = get_option( $this->progress_key, array() );
    
    if ( ! empty( $progress_data ) && isset( $progress_data['start_time'] ) ) {
      $one_hour_ago = current_time( 'timestamp' ) - 3600;
      
      if ( $progress_data['start_time'] < $one_hour_ago ) {
        delete_option( $this->progress_key );
      }
    }
  }

  /**
   * Schedule cleanup event if not already scheduled
   */
  public function schedule_cleanup_event() {
    if ( ! wp_next_scheduled( 'mpfig_cleanup_progress' ) ) {
      wp_schedule_event( time(), 'hourly', 'mpfig_cleanup_progress' );
    }
  }

  /**
   * Get the current batch size (allows for dynamic batch size)
   * 
   * Filter: mpfig_batch_size
   * 
   * Usage example:
   * add_filter( 'mpfig_batch_size', function( $batch_size ) {
   *     return 25; // Process 25 transactions per batch instead of 10
   * });
   * 
   * @param int $batch_size The current batch size
   * @return int The filtered batch size
   */
  public function get_batch_size() {
    return apply_filters( 'mpfig_batch_size', $this->batch_size );
  }

  /**
   * Clear plugin caches
   */
  private function clear_plugin_caches() {
    wp_cache_delete( 'mpfig_transaction_stats', 'mpfig_stats' );
    // Note: We don't clear transaction IDs cache as it's based on specific parameters
    // and will naturally expire. Clearing all would be too aggressive.
  }

  /**
   * Display admin notices
   */
  public function admin_notices() {
    if ( ! is_plugin_active( 'memberpress-pdf-invoice/memberpress-pdf-invoice.php' ) ) {
      echo '<div class="notice notice-error"><p>';
      printf( 
        // translators: %1$s is the opening link tag, %2$s is the closing link tag
        esc_html__( 'PDF Invoice Generator for MemberPress requires the MemberPress PDF Invoice add-on to be active. Please %1$sactivate it%2$s.', 'pdf-invoice-generator-for-memberpress' ),
        '<a href="' . esc_url( admin_url( 'plugins.php' ) ) . '">',
        '</a>'
      );
      echo '</p></div>';
    }
  }
}

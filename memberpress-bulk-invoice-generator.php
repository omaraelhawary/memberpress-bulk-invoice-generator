<?php
/*
Plugin Name: MemberPress Bulk Invoice Generator
Plugin URI: https://github.com/omarelhawary/memberpress-bulk-invoice-generator
Description: Generate bulk PDF invoices for MemberPress transactions with a user-friendly interface
Version: 1.0.0
Author: Omar ElHawary
Author URI: https://www.linkedin.com/in/omaraelhawary/
Text Domain: memberpress-bulk-invoice-generator
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

  $plugin_data = get_plugin_data(__FILE__, false, false);

  // Define useful constants
  define( 'MPBIG_VERSION', $plugin_data['Version'] ?? '1.0.0' );
  define( 'MPBIG_SLUG', 'memberpress-bulk-invoice-generator' );
  define( 'MPBIG_FILE', MPBIG_SLUG . '/memberpress-bulk-invoice-generator.php' );
  define( 'MPBIG_PATH', plugin_dir_path( __FILE__ ) );
  define( 'MPBIG_URL', plugin_dir_url( __FILE__ ) );

  // Run the plugin
  new MPBulkInvoiceGenerator();
} );

/**
 * Main plugin class
 */
class MPBulkInvoiceGenerator {

  private $batch_size = 10; // Number of transactions to process per batch
  private $progress_key = 'mpbig_progress';

  public function __construct() {
    add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
    add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
    add_action( 'wp_ajax_mpbig_generate_invoices', array( $this, 'ajax_generate_invoices' ) );
    add_action( 'wp_ajax_mpbig_process_batch', array( $this, 'ajax_process_batch' ) );
    add_action( 'wp_ajax_mpbig_create_zip', array( $this, 'ajax_create_zip' ) );
    add_action( 'wp_ajax_mpbig_get_progress', array( $this, 'ajax_get_progress' ) );
    add_action( 'wp_ajax_mpbig_empty_folder', array( $this, 'ajax_empty_folder' ) );
    add_action( 'admin_notices', array( $this, 'admin_notices' ) );
    
    // Clean up old progress data
    add_action( 'wp_scheduled_delete', array( $this, 'cleanup_old_progress' ) );
  }

  /**
   * Add admin menu
   */
  public function add_admin_menu() {
    add_submenu_page(
      'memberpress',
      __( 'Bulk Invoice Generator', 'memberpress-bulk-invoice-generator' ),
      __( 'Bulk Invoice Generator', 'memberpress-bulk-invoice-generator' ),
      'manage_options',
      'memberpress-bulk-invoice-generator',
      array( $this, 'admin_page' )
    );
  }

  /**
   * Enqueue scripts and styles
   */
  public function enqueue_scripts( $hook ) {
    if ( $hook !== 'memberpress_page_memberpress-bulk-invoice-generator' ) {
      return;
    }

    wp_enqueue_script( 'jquery-ui-datepicker' );
    wp_enqueue_style( 'jquery-ui', 'https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css' );
    // Use minified files in production, development files otherwise
    $css_file = defined( 'WP_DEBUG' ) && WP_DEBUG ? 'assets/css/admin.css' : 'assets/css/admin.min.css';
    $js_file = defined( 'WP_DEBUG' ) && WP_DEBUG ? 'assets/js/admin.js' : 'assets/js/admin.min.js';
    
    wp_enqueue_style( 'mpbig-admin', MPBIG_URL . $css_file, array(), MPBIG_VERSION );
    wp_enqueue_script( 'mpbig-admin', MPBIG_URL . $js_file, array( 'jquery', 'jquery-ui-datepicker' ), MPBIG_VERSION );
    wp_localize_script( 'mpbig-admin', 'mpbig_ajax', array(
      'ajax_url' => admin_url( 'admin-ajax.php' ),
      'nonce' => wp_create_nonce( 'mpbig_nonce' ),
      'generating' => __( 'Generating invoices...', 'memberpress-bulk-invoice-generator' ),
      'success' => __( 'Invoices generated successfully!', 'memberpress-bulk-invoice-generator' ),
      'error' => __( 'Error generating invoices.', 'memberpress-bulk-invoice-generator' ),
      'batch_size' => $this->batch_size,
      'creating_zip' => __( 'Creating ZIP file...', 'memberpress-bulk-invoice-generator' ),
      'zip_created' => __( 'ZIP file created successfully!', 'memberpress-bulk-invoice-generator' ),
      'zip_error' => __( 'Error creating ZIP file.', 'memberpress-bulk-invoice-generator' )
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
    <div class="wrap mpbig-container">
      <h1><?php _e( 'MemberPress Bulk Invoice Generator', 'memberpress-bulk-invoice-generator' ); ?></h1>
      
      <div class="mpbig-notice mpbig-notice-info">
        <p><?php _e( 'This tool allows you to generate PDF invoices for MemberPress transactions in bulk. Make sure to download the generated files before running the process again, as they will be overwritten.', 'memberpress-bulk-invoice-generator' ); ?></p>
      </div>

      <!-- Statistics Card -->
      <div class="mpbig-card mpbig-stats">
        <h2><?php _e( 'Transaction Statistics', 'memberpress-bulk-invoice-generator' ); ?></h2>
        <div class="mpbig-stats-grid">
          <div class="mpbig-stat-item">
            <span class="mpbig-stat-number"><?php echo number_format( $stats['total'] ); ?></span>
            <span class="mpbig-stat-label"><?php _e( 'Total Transactions', 'memberpress-bulk-invoice-generator' ); ?></span>
          </div>
          <div class="mpbig-stat-item">
            <span class="mpbig-stat-number"><?php echo number_format( $stats['completed'] ); ?></span>
            <span class="mpbig-stat-label"><?php _e( 'Completed', 'memberpress-bulk-invoice-generator' ); ?></span>
          </div>

          <div class="mpbig-stat-item">
            <span class="mpbig-stat-number"><?php echo number_format( $stats['pending'] ); ?></span>
            <span class="mpbig-stat-label"><?php _e( 'Pending', 'memberpress-bulk-invoice-generator' ); ?></span>
          </div>
          <div class="mpbig-stat-item">
            <span class="mpbig-stat-number"><?php echo number_format( $stats['refunded'] ); ?></span>
            <span class="mpbig-stat-label"><?php _e( 'Refunded', 'memberpress-bulk-invoice-generator' ); ?></span>
          </div>
        </div>


      </div>

      <!-- Generate Invoices Card -->
      <div class="mpbig-card mpbig-options">
        <h2><?php _e( 'Generate Invoices', 'memberpress-bulk-invoice-generator' ); ?></h2>
        
        <form id="mpbig-form">
          <div class="mpbig-form-group">
            <label for="mpbig-type"><?php _e( 'Generation Type', 'memberpress-bulk-invoice-generator' ); ?></label>
            <select id="mpbig-type" name="type" class="mpbig-form-control mpbig-select">
              <option value="all"><?php _e( 'Generate All Invoices', 'memberpress-bulk-invoice-generator' ); ?></option>
              <option value="period"><?php _e( 'Generate Invoices for Specific Period', 'memberpress-bulk-invoice-generator' ); ?></option>
            </select>
          </div>
          
          <div class="mpbig-date-row" id="mpbig-period-options" style="display: none;">
            <div class="mpbig-form-group">
              <label for="mpbig-start-date"><?php _e( 'Start Date', 'memberpress-bulk-invoice-generator' ); ?></label>
              <input type="text" id="mpbig-start-date" name="start_date" class="mpbig-form-control mpbig-date-input mpbig-datepicker" placeholder="YYYY-MM-DD" />
            </div>
            
            <div class="mpbig-form-group">
              <label for="mpbig-end-date"><?php _e( 'End Date', 'memberpress-bulk-invoice-generator' ); ?></label>
              <input type="text" id="mpbig-end-date" name="end_date" class="mpbig-form-control mpbig-date-input mpbig-datepicker" placeholder="YYYY-MM-DD" />
            </div>
          </div>
          
          <div class="mpbig-form-group">
            <label><?php _e( 'Membership Filter', 'memberpress-bulk-invoice-generator' ); ?></label>
            <select name="membership_id" class="mpbig-form-control mpbig-select">
              <option value=""><?php _e( 'All Memberships', 'memberpress-bulk-invoice-generator' ); ?></option>
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

          <div class="mpbig-form-group">
            <label><?php _e( 'Transaction Status', 'memberpress-bulk-invoice-generator' ); ?></label>
            <div class="mpbig-checkbox-group">
              <div class="mpbig-checkbox-item">
                <input type="checkbox" name="status[]" value="complete" id="status-complete" checked />
                <label for="status-complete"><?php _e( 'Complete', 'memberpress-bulk-invoice-generator' ); ?></label>
              </div>
              <div class="mpbig-checkbox-item">
                <input type="checkbox" name="status[]" value="pending" id="status-pending" />
                <label for="status-pending"><?php _e( 'Pending', 'memberpress-bulk-invoice-generator' ); ?></label>
              </div>
              <div class="mpbig-checkbox-item">
                <input type="checkbox" name="status[]" value="refunded" id="status-refunded" checked />
                <label for="status-refunded"><?php _e( 'Refunded', 'memberpress-bulk-invoice-generator' ); ?></label>
              </div>
            </div>
          </div>

          <div class="mpbig-form-group">
            <div class="mpbig-checkbox-item">
              <input type="checkbox" id="mpbig-create-zip" name="create_zip" value="1" checked />
              <label for="mpbig-create-zip"><?php _e( 'Automatically create a ZIP file containing all generated PDFs', 'memberpress-bulk-invoice-generator' ); ?></label>
            </div>
          </div>
          
          <div class="mpbig-form-group">
            <button type="submit" class="mpbig-button mpbig-button-primary" id="mpbig-generate">
              <span class="mpbig-spinner" id="mpbig-spinner" style="display: none;"></span>
              <?php _e( 'Generate Invoices', 'memberpress-bulk-invoice-generator' ); ?>
            </button>
            <span id="mpbig-progress" style="display: none;">
              <span id="mpbig-progress-text"></span>
            </span>
          </div>
                </form>
      </div>

      <!-- Progress Card -->
      <div class="mpbig-card mpbig-progress-container" id="mpbig-progress-container" style="display: none;">
        <div class="mpbig-progress-header">
          <h2><?php _e( 'Generation Progress', 'memberpress-bulk-invoice-generator' ); ?></h2>
        </div>
        <div class="mpbig-progress-bar">
          <div class="mpbig-progress-fill" id="mpbig-progress-fill"></div>
        </div>
        <div class="mpbig-progress-stats">
          <span id="mpbig-progress-current">0</span> / <span id="mpbig-progress-total">0</span> 
          (<span id="mpbig-progress-percentage">0%</span>)
        </div>
        <div class="mpbig-progress-status" id="mpbig-progress-status"></div>
      </div>

      <!-- Results Card -->
      <div class="mpbig-card mpbig-results" id="mpbig-results" style="display: none;">
        <h2><?php _e( 'Generation Results', 'memberpress-bulk-invoice-generator' ); ?></h2>
        <div id="mpbig-results-content"></div>
      </div>

      <!-- File Management Card -->
      <div class="mpbig-card mpbig-file-management">
        <h2><?php _e( 'File Management', 'memberpress-bulk-invoice-generator' ); ?></h2>
        
        <div class="mpbig-file-stats">
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
          <div class="mpbig-file-stat-item">
            <span class="mpbig-file-stat-number"><?php echo number_format( $file_count ); ?></span>
            <span class="mpbig-file-stat-label"><?php _e( 'PDF Files', 'memberpress-bulk-invoice-generator' ); ?></span>
          </div>
          <div class="mpbig-file-stat-item">
            <span class="mpbig-file-stat-number"><?php echo size_format( $total_size, 2 ); ?></span>
            <span class="mpbig-file-stat-label"><?php _e( 'Total Size', 'memberpress-bulk-invoice-generator' ); ?></span>
          </div>
        </div>
        
        <div class="mpbig-file-actions">
          <button type="button" class="mpbig-button mpbig-button-secondary" id="mpbig-empty-folder">
            <span class="mpbig-spinner" id="mpbig-empty-spinner" style="display: none;"></span>
            <?php _e( 'Empty PDF Folder', 'memberpress-bulk-invoice-generator' ); ?>
          </button>
          <p class="mpbig-file-warning">
            <?php _e( 'This will permanently delete all PDF files in the mpdf folder. Make sure you have downloaded any important files first.', 'memberpress-bulk-invoice-generator' ); ?>
          </p>
        </div>
      </div>

      <!-- Information Card -->
      <div class="mpbig-card mpbig-info">
        <h2><?php _e( 'Important Information', 'memberpress-bulk-invoice-generator' ); ?></h2>
        <ul>
          <li><?php _e( 'Generated PDF files will be saved in: wp-content/uploads/mepr/mpdf/', 'memberpress-bulk-invoice-generator' ); ?></li>
          <li><?php _e( 'Download the files before running the process again to avoid overwriting.', 'memberpress-bulk-invoice-generator' ); ?></li>
          <li><?php _e( 'The process uses batch processing to handle large datasets efficiently.', 'memberpress-bulk-invoice-generator' ); ?></li>
          <li><?php _e( 'Only transactions with status: Complete, Confirmed, Pending, or Refunded will be processed.', 'memberpress-bulk-invoice-generator' ); ?></li>
          <li><?php _e( 'ZIP files are automatically created for easier download and organization.', 'memberpress-bulk-invoice-generator' ); ?></li>
        </ul>
      </div>
    </div>
    <?php
  }

  /**
   * Get transaction statistics
   */
  private function get_transaction_stats() {
    global $wpdb;
    
    $stats = array(
      'total' => 0,
      'completed' => 0,
      'pending' => 0,
      'refunded' => 0
    );

    $table = $wpdb->prefix . 'mepr_transactions';
    
    // Total transactions
    $stats['total'] = $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
    
    // Status counts
    $stats['completed'] = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE status = %s", 'complete' ) );
    $stats['pending'] = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE status = %s", 'pending' ) );
    $stats['refunded'] = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE status = %s", 'refunded' ) );



    return $stats;
  }

  /**
   * AJAX handler for generating invoices
   */
  public function ajax_generate_invoices() {
    if ( ! current_user_can( 'manage_options' ) ) {
      wp_die( __( 'You do not have permission to perform this action.', 'memberpress-bulk-invoice-generator' ) );
    }

    if ( ! wp_verify_nonce( $_POST['nonce'], 'mpbig_nonce' ) ) {
      wp_die( __( 'Security check failed.', 'memberpress-bulk-invoice-generator' ) );
    }

    $type = sanitize_text_field( $_POST['type'] );
    $statuses = isset( $_POST['status'] ) ? array_map( 'sanitize_text_field', $_POST['status'] ) : array( 'complete', 'pending', 'refunded' );
    $create_zip = isset( $_POST['create_zip'] ) && $_POST['create_zip'] === '1';
    $membership_id = isset( $_POST['membership_id'] ) ? intval( $_POST['membership_id'] ) : 0;
    
    $start_date = '';
    $end_date = '';
    
    if ( $type === 'period' ) {
      $start_date = sanitize_text_field( $_POST['start_date'] ) . ' 00:00:00';
      $end_date = sanitize_text_field( $_POST['end_date'] ) . ' 23:59:59';
    }

    // Get all transaction IDs that match criteria
    $txn_ids = $this->get_transaction_ids( $type, $statuses, $start_date, $end_date, $membership_id );

    if ( empty( $txn_ids ) ) {
      wp_send_json( array(
        'success' => true,
        'message' => __( 'No transactions found matching the criteria.', 'memberpress-bulk-invoice-generator' ),
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
      'message' => sprintf( __( 'Starting batch processing for %d transactions...', 'memberpress-bulk-invoice-generator' ), count( $txn_ids ) ),
      'total' => count( $txn_ids ),
      'batch_size' => $this->batch_size
    ) );
  }

  /**
   * AJAX handler for processing batches
   */
  public function ajax_process_batch() {
    if ( ! current_user_can( 'manage_options' ) ) {
      wp_die( __( 'You do not have permission to perform this action.', 'memberpress-bulk-invoice-generator' ) );
    }

    if ( ! wp_verify_nonce( $_POST['nonce'], 'mpbig_nonce' ) ) {
      wp_die( __( 'Security check failed.', 'memberpress-bulk-invoice-generator' ) );
    }

    $progress_data = get_option( $this->progress_key, array() );
    
    if ( empty( $progress_data ) || empty( $progress_data['txn_ids'] ) ) {
      wp_send_json( array(
        'success' => false,
        'message' => __( 'No progress data found.', 'memberpress-bulk-invoice-generator' )
      ) );
    }

    // Check if PDF Invoice add-on is active
    if ( ! class_exists( 'MePdfInvoicesCtrl' ) ) {
      wp_send_json( array(
        'success' => false,
        'message' => __( 'MemberPress PDF Invoice add-on is not active.', 'memberpress-bulk-invoice-generator' )
      ) );
    }

    $invoices_ctrl = new MePdfInvoicesCtrl();
    $batch_size = $this->batch_size;
    $processed_in_batch = 0;
    $successful_in_batch = 0;
    $errors_in_batch = array();

    // Process current batch
    while ( $processed_in_batch < $batch_size && ! empty( $progress_data['txn_ids'] ) ) {
      $txn_id = array_shift( $progress_data['txn_ids'] );
      
      try {
        $txn = new MeprTransaction( $txn_id );
        
        if ( $txn->id > 0 ) {
          $path = $invoices_ctrl->create_receipt_pdf( $txn );
          if ( $path ) {
            $successful_in_batch++;
          }
        }
      } catch ( Exception $e ) {
        $errors_in_batch[] = sprintf( __( 'Error generating invoice for transaction %d: %s', 'memberpress-bulk-invoice-generator' ), $txn_id, $e->getMessage() );
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
        __( 'Successfully generated %d invoices.', 'memberpress-bulk-invoice-generator' ), 
        $progress_data['successful'] 
      );

      if ( ! empty( $progress_data['errors'] ) ) {
        $message .= ' ' . sprintf( __( '%d errors occurred.', 'memberpress-bulk-invoice-generator' ), count( $progress_data['errors'] ) );
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
      wp_die( __( 'You do not have permission to perform this action.', 'memberpress-bulk-invoice-generator' ) );
    }

    if ( ! wp_verify_nonce( $_POST['nonce'], 'mpbig_nonce' ) ) {
      wp_die( __( 'Security check failed.', 'memberpress-bulk-invoice-generator' ) );
    }

    $pdf_dir = WP_CONTENT_DIR . '/uploads/mepr/mpdf/';
    
    if ( ! is_dir( $pdf_dir ) ) {
      wp_send_json( array(
        'success' => false,
        'message' => __( 'PDF directory not found.', 'memberpress-bulk-invoice-generator' )
      ) );
    }

    $zip_file = $this->create_zip_file( $pdf_dir );
    
    if ( $zip_file ) {
      $zip_url = content_url( '/uploads/mepr/mpdf/' . basename( $zip_file ) );
      wp_send_json( array(
        'success' => true,
        'message' => __( 'ZIP file created successfully!', 'memberpress-bulk-invoice-generator' ),
        'zip_url' => $zip_url,
        'zip_filename' => basename( $zip_file )
      ) );
    } else {
      wp_send_json( array(
        'success' => false,
        'message' => __( 'Failed to create ZIP file.', 'memberpress-bulk-invoice-generator' )
      ) );
    }
  }

  /**
   * AJAX handler for getting progress
   */
  public function ajax_get_progress() {
    if ( ! current_user_can( 'manage_options' ) ) {
      wp_die( __( 'You do not have permission to perform this action.', 'memberpress-bulk-invoice-generator' ) );
    }

    $progress_data = get_option( $this->progress_key, array() );
    
    if ( empty( $progress_data ) ) {
      wp_send_json( array(
        'success' => false,
        'message' => __( 'No progress data found.', 'memberpress-bulk-invoice-generator' )
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
      wp_die( __( 'You do not have permission to perform this action.', 'memberpress-bulk-invoice-generator' ) );
    }

    if ( ! wp_verify_nonce( $_POST['nonce'], 'mpbig_nonce' ) ) {
      wp_die( __( 'Security check failed.', 'memberpress-bulk-invoice-generator' ) );
    }

    $pdf_dir = WP_CONTENT_DIR . '/uploads/mepr/mpdf/';
    
    if ( ! is_dir( $pdf_dir ) ) {
      wp_send_json( array(
        'success' => false,
        'message' => __( 'PDF directory not found.', 'memberpress-bulk-invoice-generator' )
      ) );
    }

    $deleted_count = 0;
    $errors = array();

    // Get all PDF files
    $files = glob( $pdf_dir . '*.pdf' );
    
    foreach ( $files as $file ) {
      if ( is_file( $file ) ) {
        if ( unlink( $file ) ) {
          $deleted_count++;
        } else {
          $errors[] = sprintf( __( 'Failed to delete: %s', 'memberpress-bulk-invoice-generator' ), basename( $file ) );
        }
      }
    }

    // Also delete ZIP files
    $zip_files = glob( $pdf_dir . '*.zip' );
    foreach ( $zip_files as $file ) {
      if ( is_file( $file ) ) {
        if ( unlink( $file ) ) {
          $deleted_count++;
        } else {
          $errors[] = sprintf( __( 'Failed to delete: %s', 'memberpress-bulk-invoice-generator' ), basename( $file ) );
        }
      }
    }

    if ( $deleted_count > 0 ) {
      $message = sprintf( 
        __( 'Successfully deleted %d files from the PDF folder.', 'memberpress-bulk-invoice-generator' ), 
        $deleted_count 
      );
      
      if ( ! empty( $errors ) ) {
        $message .= ' ' . sprintf( __( '%d files could not be deleted.', 'memberpress-bulk-invoice-generator' ), count( $errors ) );
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
        'message' => __( 'No files found to delete.', 'memberpress-bulk-invoice-generator' )
      ) );
    }
  }

  /**
   * Get transaction IDs based on criteria
   */
  private function get_transaction_ids( $type, $statuses, $start_date = '', $end_date = '', $membership_id = 0 ) {
    global $wpdb;

    $table = $wpdb->prefix . 'mepr_transactions';
    $status_placeholders = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );
    
    // Build query
    $query = "SELECT id FROM {$table} WHERE status IN ({$status_placeholders})";
    $query_args = $statuses;

    if ( $type === 'period' && ! empty( $start_date ) && ! empty( $end_date ) ) {
      $query .= " AND created_at > %s AND created_at < %s";
      $query_args[] = $start_date;
      $query_args[] = $end_date;
    }

    // Add membership filter if specified
    if ( $membership_id > 0 ) {
      $query .= " AND product_id = %d";
      $query_args[] = $membership_id;
    }

    $query .= " ORDER BY created_at ASC";

    $prepared_query = $wpdb->prepare( $query, $query_args );
    $results = $wpdb->get_results( $prepared_query );

    return array_map( function( $row ) { return $row->id; }, $results );
  }

  /**
   * Create ZIP file from PDF directory
   */
  private function create_zip_file( $pdf_dir ) {
    $zip_filename = 'memberpress-invoices-' . date( 'Y-m-d-H-i-s' ) . '.zip';
    $zip_path = $pdf_dir . $zip_filename;

    $zip = new ZipArchive();
    
    if ( $zip->open( $zip_path, ZipArchive::CREATE ) !== TRUE ) {
      return false;
    }

    $files = glob( $pdf_dir . '*.pdf' );
    
    foreach ( $files as $file ) {
      $zip->addFile( $file, basename( $file ) );
    }

    $zip->close();
    
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
   * Display admin notices
   */
  public function admin_notices() {
    if ( ! is_plugin_active( 'memberpress-pdf-invoice/memberpress-pdf-invoice.php' ) ) {
      echo '<div class="notice notice-error"><p>';
      printf( 
        __( 'MemberPress Bulk Invoice Generator requires the MemberPress PDF Invoice add-on to be active. Please %sactivate it%s.', 'memberpress-bulk-invoice-generator' ),
        '<a href="' . admin_url( 'plugins.php' ) . '">',
        '</a>'
      );
      echo '</p></div>';
    }
  }
}

<?php
/**
 * MemberPress Bulk Invoice Generator - Installation Script
 * 
 * This script helps verify the installation and dependencies
 * Run this file directly in your browser to check the setup
 */

// Prevent direct access if not in WordPress
if ( ! defined( 'ABSPATH' ) ) {
    // Check if we're in WordPress context
    if ( ! file_exists( dirname( __FILE__ ) . '/../../../wp-config.php' ) ) {
        die( 'This script must be run from within a WordPress installation.' );
    }
    
    // Load WordPress
    require_once dirname( __FILE__ ) . '/../../../wp-config.php';
}

// Check if we're in admin
if ( ! is_admin() ) {
    wp_die( 'This script must be run from the WordPress admin area.' );
}

// Check user capabilities
if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( 'You do not have sufficient permissions to access this page.' );
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>MemberPress Bulk Invoice Generator - Installation Check</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .check { margin: 10px 0; padding: 10px; border-radius: 4px; }
        .success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .warning { background: #fff3cd; color: #856404; border: 1px solid #ffeaa7; }
        .info { background: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }
        .check h3 { margin: 0 0 10px 0; }
        .check ul { margin: 10px 0; padding-left: 20px; }
    </style>
</head>
<body>
    <h1>MemberPress Bulk Invoice Generator - Installation Check</h1>
    
    <?php
    $checks = array();
    
    // Check WordPress version
    global $wp_version;
    if ( version_compare( $wp_version, '5.0', '>=' ) ) {
        $checks[] = array( 'type' => 'success', 'message' => 'WordPress version: ' . $wp_version . ' ✓' );
    } else {
        $checks[] = array( 'type' => 'error', 'message' => 'WordPress version: ' . $wp_version . ' (Requires 5.0+) ✗' );
    }
    
    // Check PHP version
    if ( version_compare( PHP_VERSION, '7.4', '>=' ) ) {
        $checks[] = array( 'type' => 'success', 'message' => 'PHP version: ' . PHP_VERSION . ' ✓' );
    } else {
        $checks[] = array( 'type' => 'error', 'message' => 'PHP version: ' . PHP_VERSION . ' (Requires 7.4+) ✗' );
    }
    
    // Check if MemberPress is active
    if ( is_plugin_active( 'memberpress/memberpress.php' ) || defined( 'MEPR_PLUGIN_NAME' ) ) {
        $checks[] = array( 'type' => 'success', 'message' => 'MemberPress plugin is active ✓' );
    } else {
        $checks[] = array( 'type' => 'error', 'message' => 'MemberPress plugin is not active ✗' );
    }
    
    // Check if MemberPress PDF Invoice add-on is active
    if ( is_plugin_active( 'memberpress-pdf-invoice/memberpress-pdf-invoice.php' ) ) {
        $checks[] = array( 'type' => 'success', 'message' => 'MemberPress PDF Invoice add-on is active ✓' );
    } else {
        $checks[] = array( 'type' => 'error', 'message' => 'MemberPress PDF Invoice add-on is not active ✗' );
    }
    
    // Check if MePdfInvoicesCtrl class exists
    if ( class_exists( 'MePdfInvoicesCtrl' ) ) {
        $checks[] = array( 'type' => 'success', 'message' => 'MePdfInvoicesCtrl class is available ✓' );
    } else {
        $checks[] = array( 'type' => 'error', 'message' => 'MePdfInvoicesCtrl class is not available ✗' );
    }
    
    // Check if MeprTransaction class exists
    if ( class_exists( 'MeprTransaction' ) ) {
        $checks[] = array( 'type' => 'success', 'message' => 'MeprTransaction class is available ✓' );
    } else {
        $checks[] = array( 'type' => 'error', 'message' => 'MeprTransaction class is not available ✗' );
    }
    
    // Check upload directory
    $upload_dir = wp_upload_dir();
    $mpdf_dir = $upload_dir['basedir'] . '/mepr/mpdf';
    if ( is_dir( $mpdf_dir ) ) {
        $checks[] = array( 'type' => 'success', 'message' => 'MPDF upload directory exists: ' . $mpdf_dir . ' ✓' );
        
        // Check if directory is writable
        if ( is_writable( $mpdf_dir ) ) {
            $checks[] = array( 'type' => 'success', 'message' => 'MPDF upload directory is writable ✓' );
        } else {
            $checks[] = array( 'type' => 'error', 'message' => 'MPDF upload directory is not writable ✗' );
        }
    } else {
        $checks[] = array( 'type' => 'warning', 'message' => 'MPDF upload directory does not exist: ' . $mpdf_dir . ' (Will be created automatically)' );
    }
    
    // Check database table
    global $wpdb;
    $table = $wpdb->prefix . 'mepr_transactions';
    $table_exists = $wpdb->get_var( "SHOW TABLES LIKE '$table'" ) == $table;
    if ( $table_exists ) {
        $checks[] = array( 'type' => 'success', 'message' => 'MemberPress transactions table exists ✓' );
        
        // Count transactions
        $count = $wpdb->get_var( "SELECT COUNT(*) FROM $table" );
        $checks[] = array( 'type' => 'info', 'message' => 'Total transactions in database: ' . number_format( $count ) );
    } else {
        $checks[] = array( 'type' => 'error', 'message' => 'MemberPress transactions table does not exist ✗' );
    }
    
    // Check plugin files
    $plugin_file = __FILE__;
    $plugin_dir = dirname( $plugin_file );
    
    $required_files = array(
        'memberpress-bulk-invoice-generator.php',
        'js/admin.js',
        'css/admin.css',
        'README.md'
    );
    
    foreach ( $required_files as $file ) {
        $file_path = $plugin_dir . '/' . $file;
        if ( file_exists( $file_path ) ) {
            $checks[] = array( 'type' => 'success', 'message' => 'Plugin file exists: ' . $file . ' ✓' );
        } else {
            $checks[] = array( 'type' => 'error', 'message' => 'Plugin file missing: ' . $file . ' ✗' );
        }
    }
    
    // Display results
    foreach ( $checks as $check ) {
        echo '<div class="check ' . $check['type'] . '">';
        echo '<h3>' . ucfirst( $check['type'] ) . '</h3>';
        echo '<p>' . $check['message'] . '</p>';
        echo '</div>';
    }
    
    // Summary
    $success_count = count( array_filter( $checks, function( $check ) { return $check['type'] === 'success'; } ) );
    $error_count = count( array_filter( $checks, function( $check ) { return $check['type'] === 'error'; } ) );
    $warning_count = count( array_filter( $checks, function( $check ) { return $check['type'] === 'warning'; } ) );
    
    echo '<div class="check info">';
    echo '<h3>Summary</h3>';
    echo '<p>Total checks: ' . count( $checks ) . '</p>';
    echo '<p>Successful: ' . $success_count . '</p>';
    echo '<p>Errors: ' . $error_count . '</p>';
    echo '<p>Warnings: ' . $warning_count . '</p>';
    
    if ( $error_count === 0 ) {
        echo '<p><strong>✅ Installation appears to be successful!</strong></p>';
        echo '<p>You can now access the Bulk Invoice Generator from MemberPress > Bulk Invoice Generator in your WordPress admin.</p>';
    } else {
        echo '<p><strong>❌ Please fix the errors above before using the plugin.</strong></p>';
    }
    echo '</div>';
    
    // Next steps
    echo '<div class="check info">';
    echo '<h3>Next Steps</h3>';
    echo '<ul>';
    echo '<li>If all checks pass, go to MemberPress > Bulk Invoice Generator</li>';
    echo '<li>If there are errors, fix them and refresh this page</li>';
    echo '<li>Make sure to download generated PDF files before running the generator again</li>';
    echo '<li>For large numbers of transactions, consider running the generator during off-peak hours</li>';
    echo '</ul>';
    echo '</div>';
    ?>
    
    <p><a href="<?php echo admin_url( 'admin.php?page=memberpress-bulk-invoice-generator' ); ?>">Go to Bulk Invoice Generator</a> | 
    <a href="<?php echo admin_url( 'plugins.php' ); ?>">Manage Plugins</a></p>
</body>
</html>

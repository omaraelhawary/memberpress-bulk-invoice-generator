<?php
/**
 * Uninstall script for PDF Invoice Generator for MemberPress
 * 
 * This script runs when the plugin is deleted from WordPress.
 * It cleans up all plugin data, options, and generated files.
 */

// If uninstall not called from WordPress, exit
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// Check if we should clean up files
$cleanup_files = get_option( 'mpfig_cleanup_files_on_uninstall', true );

if ( $cleanup_files ) {
    // Clean up generated PDF files
    $upload_dir = wp_upload_dir();
    $pdf_dir = $upload_dir['basedir'] . '/mepr/mpdf/';
    
    if ( is_dir( $pdf_dir ) ) {
        // Get all PDF and ZIP files created by this plugin
        $files = array_merge(
            glob( $pdf_dir . '*.pdf' ),
            glob( $pdf_dir . 'memberpress-invoices-*.zip' )
        );
        
        foreach ( $files as $file ) {
            if ( is_file( $file ) ) {
                wp_delete_file( $file );
            }
        }
        
        // Remove the directory if it's empty using WP_Filesystem
        if ( ! function_exists( 'WP_Filesystem' ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        
        $access_type = get_filesystem_method();
        if ( 'direct' === $access_type ) {
            $creds = request_filesystem_credentials( site_url() . '/wp-admin/', '', false, false, array() );
            if ( WP_Filesystem( $creds ) ) {
                global $wp_filesystem;
                if ( count( glob( $pdf_dir . '*' ) ) === 0 ) {
                    $wp_filesystem->rmdir( $pdf_dir );
                }
            }
        }
    }
}

// Clean up plugin options
$options_to_delete = array(
    'mpfig_progress',
    'mpfig_cleanup_files_on_uninstall',
    'mpfig_last_generation_time',
    'mpfig_generation_stats'
);

foreach ( $options_to_delete as $option ) {
    delete_option( $option );
}

// Clean up any transients
delete_transient( 'mpfig_progress_data' );
delete_transient( 'mpfig_generation_status' );
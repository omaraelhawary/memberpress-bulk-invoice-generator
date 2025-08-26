<?php
/**
 * Uninstall script for MemberPress Bulk Invoice Generator
 * 
 * This file is executed when the plugin is deleted from WordPress
 */

// If uninstall not called from WordPress, exit
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// Clean up any options or data if needed
// Note: This plugin doesn't create any database tables or options,
// so there's nothing to clean up in the database.

// Remove any scheduled events if they exist
wp_clear_scheduled_hook( 'mpbig_cleanup_temp_files' );

// Log the uninstallation for debugging purposes
if ( function_exists( 'error_log' ) ) {
    error_log( 'MemberPress Bulk Invoice Generator plugin uninstalled at ' . date( 'Y-m-d H:i:s' ) );
}

// Note: We don't delete the generated PDF files in wp-content/uploads/mepr/mpdf/
// as these might be important business documents that the user wants to keep.
// The user should manually clean up these files if needed.

=== PDF Invoice Generator for MemberPress ===
Contributors: omaraelhawary
Tags: memberpress, invoice, pdf, bulk, transactions, billing
Requires at least: 5.0
Tested up to: 6.4
Stable tag: 1.0.2
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Generate bulk PDF invoices for MemberPress transactions with a user-friendly interface.

== Description ==

This plugin extends the functionality of MemberPress and the MemberPress PDF Invoice add-on by providing a convenient admin interface to generate PDF invoices for multiple transactions at once. It supports both generating invoices for all transactions and generating invoices for a specific time period.

= Features =

* **User-friendly Admin Interface**: Clean, intuitive interface integrated into the MemberPress admin menu
* **Flexible Generation Options**: 
  * Generate invoices for all transactions
  * Generate invoices for a specific date range
* **Transaction Status Filtering**: Choose which transaction statuses to include (Complete, Pending, Refunded)
* **Real-time Statistics**: View transaction counts before generation
* **Batch Processing**: Efficiently handles large datasets by processing transactions in small batches to avoid timeouts
* **Visual Progress Tracking**: Real-time progress bar with detailed statistics during generation
* **Automatic ZIP Creation**: Option to automatically create a ZIP file containing all generated PDFs for easy download
* **Error Handling**: Detailed error reporting for failed generations
* **Security**: Proper nonce verification and capability checks
* **Performance Optimization**: Minified CSS and JavaScript for faster loading
* **WordPress Standards**: Compliant with WordPress coding standards

= Requirements =

* WordPress 5.0 or higher
* MemberPress plugin (active)
* MemberPress PDF Invoice add-on (active)
* PHP 7.4 or higher
* PHP ZipArchive extension (for ZIP file creation)

== Installation ==

1. **Upload the Plugin**:
   * Upload the `pdf-invoice-generator-for-memberpress` folder to your `/wp-content/plugins/` directory
   * Or zip the folder and upload via WordPress admin

2. **Activate the Plugin**:
   * Go to **Plugins** > **Installed Plugins**
   * Find "PDF Invoice Generator for MemberPress" and click **Activate**

3. **Verify Dependencies**:
   * Ensure MemberPress is active
   * Ensure MemberPress PDF Invoice add-on is active
   * The plugin will show an error notice if dependencies are missing

== Frequently Asked Questions ==

= Does this plugin work without MemberPress? =

No, this plugin requires both MemberPress and the MemberPress PDF Invoice add-on to be installed and active.

= Where are the generated PDF files saved? =

Generated PDF invoices are saved in: `wp-content/uploads/mepr/mpdf/`

= Can I generate invoices for a specific date range? =

Yes, you can select "Generate Invoices for Specific Period" and set start and end dates.

= What happens if I run the generator multiple times? =

New generations will overwrite existing files with the same names. Make sure to download existing files before running again.

= Can I filter by customer email? =

Yes, you can filter transactions by entering a specific customer email address.

= What transaction statuses are supported? =

The plugin supports Complete, Pending, and Refunded transaction statuses.

== Screenshots ==

1. Dashboard showing transaction statistics and generation options

== Changelog ==

= 1.0.2 =
* Fix Blank Screen
* Enhanced batch processing system for more efficient invoice generation
* Improved batch size handling and progress tracking during bulk operations

= 1.0.1 =
* Added customer email filter
* Enhanced UI layout with distinct sections
* Modern datepicker styling
* Better error handling and form validation
* Improved transaction counting accuracy
* Fixed UI stuck issues and progress state management

= 1.0.0 =
* Initial release
* User-friendly admin interface
* Bulk PDF invoice generation
* Support for all transactions or specific date ranges
* Transaction status filtering
* Real-time statistics display
* Batch processing
* Visual progress tracking
* Automatic ZIP file creation
* Comprehensive error handling
* Security features
* Modern, responsive UI
* File management tools

== Upgrade Notice ==

= 1.0.2 =
Enhanced batch processing and fixed blank screen issue. Recommended update.

= 1.0.1 =
Added customer email filtering and improved UI. Recommended update.

= 1.0.0 =
Initial release of PDF Invoice Generator for MemberPress.

== Developer Hooks ==

The plugin provides filter hooks for customization:

**`mpfig_batch_size`** - Modify the number of transactions processed per batch

Example:
[php]
add_filter( 'mpfig_batch_size', function( $batch_size ) {
    return 25; // Process 25 transactions per batch instead of 10
});
[/php]

== Support ==

For support and feature requests, please contact the plugin developer or visit the GitHub repository.

== Credits ==

* Built for MemberPress
* Uses the MemberPress PDF Invoice add-on functionality
* jQuery UI Datepicker for date selection
* PHP ZipArchive for ZIP file creation

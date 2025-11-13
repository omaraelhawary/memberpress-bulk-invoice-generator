# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.2] - 2025-01-26

### Changed
- Version bump to 1.0.2

## [1.0.1] - 2025-01-26

### Added
- **Customer Email Filter**: Added ability to filter transactions by specific customer email address
- **Enhanced UI Layout**: Improved form organization with distinct sections for better user experience
- **Modern Datepicker Styling**: Implemented clean, professional datepicker design matching modern UI standards
- **Better Error Handling**: Enhanced error recovery and user feedback when invalid data is entered
- **Form Validation**: Added client-side and server-side validation for email format and date ranges

### Improved
- **UI/UX Enhancements**: 
  - Reorganized form into logical sections (Generation Settings, Filter Settings, Output Settings, Action Section)
  - Improved spacing and visual hierarchy
  - Better alignment of form elements
  - Centered and properly sized "Generate Invoices" button
  - Removed unnecessary frames around action buttons
- **Error Recovery**: Fixed issue where plugin would get stuck showing "No progress data found"
- **Date Validation**: Added proper date format validation and range checking
- **Transaction Counting**: Improved accuracy to match MemberPress default behavior (excludes confirmation and failed transactions)

### Fixed
- **UI Stuck Issues**: Resolved problem where invalid data entry would cause the interface to freeze
- **Progress State Management**: Better handling of processing states and error recovery
- **Datepicker Overlap**: Fixed datepicker positioning and z-index issues
- **Form Layout**: Improved responsive design and element alignment

### Technical Improvements
- Enhanced AJAX error handling with try-catch blocks
- Better state management for batch processing
- Improved form validation with specific error messages
- Enhanced caching and performance optimization

## [1.0.0] - 2025-01-26

### Added
- Initial release of MemberPress Bulk Invoice Generator
- User-friendly admin interface integrated into MemberPress menu
- Bulk PDF invoice generation for MemberPress transactions
- Support for generating invoices for all transactions or specific date ranges
- Transaction status filtering (Complete, Pending, Refunded)
- Real-time transaction statistics display
- Batch processing to handle large datasets efficiently
- Visual progress tracking with detailed statistics
- Automatic ZIP file creation for easy download
- Comprehensive error handling and reporting
- Security features with nonce verification and capability checks
- Modern, responsive UI with smooth animations
- File management tools for cleaning up generated PDFs
- Support for membership-specific filtering
- Detailed documentation and usage instructions
- **Asset Minification System**: CSS and JavaScript minification for improved performance
- **WordPress Standards Compliance**: Proper asset organization following WordPress coding standards
- **Build Process**: Webpack-based build system with development and production modes
- **Performance Optimization**: 27.6% CSS and 57.7% JavaScript file size reduction

### Features
- **Admin Interface**: Clean, modern interface with card-based layout
- **Generation Options**: All transactions or date-specific generation
- **Progress Tracking**: Real-time progress bar with batch processing
- **ZIP Creation**: Automatic ZIP file generation for bulk downloads
- **Error Handling**: Detailed error reporting for failed generations
- **File Management**: Tools to manage and clean up generated files
- **Responsive Design**: Works on desktop and mobile devices
- **Security**: Proper WordPress security practices implemented

### Technical Requirements
- WordPress 5.0 or higher
- MemberPress plugin (active)
- MemberPress PDF Invoice add-on (active)
- PHP 7.4 or higher
- PHP ZipArchive extension

### File Structure
```
memberpress-bulk-invoice-generator/
├── memberpress-bulk-invoice-generator.php (Main plugin file)
├── assets/
│   ├── css/
│   │   ├── admin.css (Source styles)
│   │   └── admin.min.css (Minified styles)
│   └── js/
│       ├── admin.js (Source functionality)
│       └── admin.min.js (Minified functionality)
├── README.md (Documentation)
├── CHANGELOG.md (This file)
├── LICENSE (GPL v2.0 license)
├── composer.json (Composer configuration)
├── install.php (Installation script)
└── uninstall.php (Cleanup script)
```

### Security
- Nonce verification for all AJAX requests
- Capability checks for admin functions
- Input sanitization and validation
- Proper file path handling
- Secure file operations

### Performance
- Batch processing to prevent timeouts
- Efficient database queries
- Optimized file operations
- Memory-conscious processing
- **Asset Minification**: CSS and JavaScript files are minified for faster loading
- **WordPress Standards**: Proper asset organization for better caching
- **Build Optimization**: Production-ready minified assets with source maps for development

### Compatibility
- Tested with WordPress 5.0+
- Compatible with MemberPress latest versions
- Works with MemberPress PDF Invoice add-on
- Cross-browser compatible admin interface

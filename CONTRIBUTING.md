# Contributing to MemberPress Bulk Invoice Generator

Thank you for your interest in contributing to the MemberPress Bulk Invoice Generator plugin! This document provides guidelines and information for contributors.

## Table of Contents

- [Code of Conduct](#code-of-conduct)
- [How Can I Contribute?](#how-can-i-contribute)
- [Development Setup](#development-setup)
- [Coding Standards](#coding-standards)
- [Testing](#testing)
- [Pull Request Process](#pull-request-process)
- [Reporting Bugs](#reporting-bugs)
- [Feature Requests](#feature-requests)
- [Questions and Support](#questions-and-support)

## Code of Conduct

### Our Pledge

We as members, contributors, and leaders pledge to make participation in our
community a harassment-free experience for everyone, regardless of age, body
size, visible or invisible disability, ethnicity, sex characteristics, gender
identity and expression, level of experience, education, socio-economic status,
nationality, personal appearance, race, religion, or sexual identity
and orientation.

### Our Standards

Examples of behavior that contributes to a positive environment for our
community include:

* Demonstrating empathy and kindness toward other people
* Being respectful of differing opinions, viewpoints, and experiences
* Giving and gracefully accepting constructive feedback
* Accepting responsibility and apologizing to those affected by our mistakes
* Focusing on what is best for the overall community

Examples of unacceptable behavior include:

* The use of sexualized language or imagery, and sexual attention or advances
* Trolling, insulting or derogatory comments, and personal or political attacks
* Public or private harassment
* Publishing others' private information without explicit permission
* Other conduct which could reasonably be considered inappropriate

## How Can I Contribute?

### Reporting Bugs

Before creating bug reports, please check the existing issues to see if the problem has already been reported. When creating a bug report, include as many details as possible:

- **WordPress Version**: The version of WordPress you're using
- **MemberPress Version**: The version of MemberPress installed
- **PHP Version**: Your server's PHP version
- **Plugin Version**: The version of this plugin
- **Description**: A clear description of what the bug is
- **Steps to Reproduce**: Step-by-step instructions to reproduce the issue
- **Expected Behavior**: What you expected to happen
- **Actual Behavior**: What actually happened
- **Screenshots**: If applicable, add screenshots to help explain the problem
- **Error Logs**: Any error messages or logs that might be relevant

### Suggesting Enhancements

If you have a suggestion for a new feature or enhancement, please:

1. Check if the feature has already been requested
2. Provide a clear description of the feature
3. Explain why this feature would be useful
4. Include any mockups or examples if possible

### Pull Requests

We welcome pull requests! Please follow these guidelines:

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Make your changes following our coding standards
4. Test your changes thoroughly
5. Commit your changes (`git commit -m 'Add amazing feature'`)
6. Push to the branch (`git push origin feature/amazing-feature`)
7. Open a Pull Request

## Development Setup

### Prerequisites

- WordPress development environment (local or staging)
- MemberPress plugin installed and activated
- MemberPress PDF Invoice add-on installed and activated
- PHP 7.4 or higher
- Git

### Local Development

1. **Clone the repository**:
   ```bash
   git clone https://github.com/yourusername/memberpress-bulk-invoice-generator.git
   cd memberpress-bulk-invoice-generator
   ```

2. **Set up WordPress**:
   - Install WordPress locally (using tools like Local by Flywheel, XAMPP, or similar)
   - Install and activate MemberPress
   - Install and activate MemberPress PDF Invoice add-on

3. **Install the plugin**:
   - Copy the plugin files to your WordPress plugins directory
   - Activate the plugin through WordPress admin

4. **Development tools** (optional):
   ```bash
   # Install PHP CodeSniffer for WordPress
   composer require --dev squizlabs/php_codesniffer
   composer require --dev wp-coding-standards/wpcs
   
   # Install Node.js dependencies (if using build tools)
   npm install
   ```

### Testing Environment

- Use a staging environment for testing
- Create test transactions in MemberPress
- Test with different transaction statuses
- Test with various date ranges
- Test with large datasets

## Coding Standards

### PHP Standards

We follow WordPress Coding Standards and best practices:

- **WordPress Coding Standards**: Follow [WordPress PHP Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/php/)
- **File Naming**: Use lowercase with hyphens for file names
- **Class Naming**: Use PascalCase for class names
- **Function Naming**: Use snake_case for function names
- **Variable Naming**: Use snake_case for variables
- **Constants**: Use UPPER_CASE for constants

### Code Style

```php
<?php
/**
 * Example function following our coding standards
 *
 * @param int    $user_id User ID.
 * @param string $status  Status to check.
 * @return bool True if user has access, false otherwise.
 */
function mpbig_check_user_access( $user_id, $status ) {
    if ( ! is_numeric( $user_id ) ) {
        return false;
    }

    $user = get_user_by( 'id', $user_id );
    
    if ( ! $user ) {
        return false;
    }

    return user_can( $user_id, 'manage_options' );
}
```

### JavaScript Standards

- Follow WordPress JavaScript Coding Standards
- Use jQuery for DOM manipulation
- Use WordPress AJAX for server communication
- Include proper error handling

### CSS Standards

- Use BEM methodology for class naming
- Follow WordPress CSS Coding Standards
- Use CSS custom properties for theming
- Ensure responsive design

## Testing

### Manual Testing

Before submitting a pull request, please test:

1. **Installation**: Fresh installation on a clean WordPress site
2. **Activation**: Plugin activation with and without dependencies
3. **Functionality**: All major features work as expected
4. **Error Handling**: Proper error messages and handling
5. **Performance**: No significant performance degradation
6. **Security**: No security vulnerabilities introduced
7. **Compatibility**: Works with different WordPress versions
8. **Responsive Design**: Works on different screen sizes

### Automated Testing

If you add automated tests:

- Use PHPUnit for PHP testing
- Use Jest for JavaScript testing
- Ensure good test coverage
- Include integration tests

### Testing Checklist

- [ ] Plugin installs without errors
- [ ] Plugin activates without errors
- [ ] All admin pages load correctly
- [ ] AJAX requests work properly
- [ ] Error handling works as expected
- [ ] Progress tracking functions correctly
- [ ] ZIP file creation works
- [ ] File cleanup functions work
- [ ] Security measures are in place
- [ ] No PHP errors or warnings
- [ ] No JavaScript console errors
- [ ] Responsive design works on mobile

## Pull Request Process

### Before Submitting

1. **Test thoroughly**: Ensure all functionality works
2. **Follow coding standards**: Use proper formatting and naming
3. **Add documentation**: Update README if needed
4. **Update changelog**: Add entries to CHANGELOG.md
5. **Check compatibility**: Test with different WordPress versions

### Pull Request Template

When creating a pull request, please include:

```markdown
## Description
Brief description of the changes made.

## Type of Change
- [ ] Bug fix (non-breaking change which fixes an issue)
- [ ] New feature (non-breaking change which adds functionality)
- [ ] Breaking change (fix or feature that would cause existing functionality to not work as expected)
- [ ] Documentation update

## Testing
- [ ] Tested on WordPress [version]
- [ ] Tested with MemberPress [version]
- [ ] Tested with PHP [version]
- [ ] All existing functionality still works
- [ ] New functionality works as expected

## Checklist
- [ ] My code follows the coding standards
- [ ] I have tested my changes
- [ ] I have updated the documentation
- [ ] I have updated the changelog
- [ ] My changes generate no new warnings
- [ ] I have added tests if applicable

## Screenshots
If applicable, add screenshots to help explain your changes.
```

### Review Process

1. **Automated checks**: CI/CD pipeline will run tests
2. **Code review**: Maintainers will review your code
3. **Testing**: Changes will be tested in various environments
4. **Approval**: Once approved, changes will be merged

## Reporting Bugs

### Bug Report Template

```markdown
## Bug Description
Clear and concise description of the bug.

## Steps to Reproduce
1. Go to '...'
2. Click on '...'
3. Scroll down to '...'
4. See error

## Expected Behavior
What you expected to happen.

## Actual Behavior
What actually happened.

## Environment
- WordPress Version: [e.g., 6.0]
- MemberPress Version: [e.g., 1.9.0]
- PHP Version: [e.g., 8.0]
- Plugin Version: [e.g., 1.0.0]
- Browser: [e.g., Chrome 90]

## Additional Information
Any additional context, screenshots, or error logs.
```

## Feature Requests

### Feature Request Template

```markdown
## Feature Description
Clear and concise description of the feature.

## Problem Statement
What problem does this feature solve?

## Proposed Solution
How would you like this feature to work?

## Alternative Solutions
Any alternative solutions you've considered.

## Additional Context
Any additional context, mockups, or examples.
```

## Questions and Support

### Getting Help

- **GitHub Issues**: For bugs and feature requests
- **GitHub Discussions**: For questions and general discussion
- **Documentation**: Check the README.md and inline code comments

### Community Guidelines

- Be respectful and constructive
- Provide clear, detailed information
- Help others when possible
- Follow the code of conduct

## Release Process

### Version Numbering

We follow [Semantic Versioning](https://semver.org/):

- **MAJOR**: Incompatible API changes
- **MINOR**: New functionality in a backward-compatible manner
- **PATCH**: Backward-compatible bug fixes

### Release Checklist

- [ ] All tests pass
- [ ] Documentation is updated
- [ ] Changelog is updated
- [ ] Version number is updated
- [ ] Release notes are prepared
- [ ] Tag is created
- [ ] Release is published

## License

By contributing to this project, you agree that your contributions will be licensed under the same license as the project (GPL v2.0).

## Acknowledgments

Thank you to all contributors who have helped make this plugin better!

---

**Note**: This contributing guide is a living document. If you have suggestions for improvements, please submit a pull request or open an issue.

# Build Process for MemberPress Bulk Invoice Generator

This plugin now includes a build process to minify CSS and JavaScript files for better performance.

## Prerequisites

- Node.js (version 14 or higher)
- npm (comes with Node.js)

## Setup

1. Install dependencies:
   ```bash
   npm install
   ```

## Build Commands

### Development Build
```bash
npm run build:dev
```
Creates unminified files with source maps for debugging.

### Production Build
```bash
npm run build
```
Creates minified files optimized for production use.

### Watch Mode
```bash
npm run watch
```
Watches for file changes and automatically rebuilds during development.

### Clean Build
```bash
npm run clean
```
Removes all build artifacts.

## File Structure

After building, the following files will be created:

- `assets/css/admin.min.css` - Minified CSS file
- `assets/js/admin.min.js` - Minified JavaScript file

## WordPress Integration

The plugin automatically uses:
- **Minified files** in production (when `WP_DEBUG` is false)
- **Development files** when debugging (when `WP_DEBUG` is true)

## Source Files

The source files are located in:
- `assets/css/admin.css` - Source CSS file
- `assets/js/admin.js` - Source JavaScript file

## Release Process

When creating a release:
1. Run `npm run build` to generate minified files
2. Zip the plugin folder (excluding development files like `node_modules/`, `package.json`, etc.)
3. The release package will include both development and minified files

## Performance Benefits

- **CSS**: Reduces file size by ~60-70%
- **JavaScript**: Reduces file size by ~50-60%
- **Faster loading**: Smaller files load faster
- **Better caching**: Minified files are more cache-friendly

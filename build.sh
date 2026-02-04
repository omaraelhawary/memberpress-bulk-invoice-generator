#!/bin/bash

# PDF Invoice Generator for MemberPress - Build Script
# This script builds the minified CSS and JS files

echo "🔨 Building PDF Invoice Generator for MemberPress..."

# Check if Node.js is installed
if ! command -v node &> /dev/null; then
    echo "❌ Node.js is not installed. Please install Node.js first."
    exit 1
fi

# Check if npm is installed
if ! command -v npm &> /dev/null; then
    echo "❌ npm is not installed. Please install npm first."
    exit 1
fi

# Install dependencies if node_modules doesn't exist
if [ ! -d "node_modules" ]; then
    echo "📦 Installing dependencies..."
    npm install
fi

# Build the project
echo "🏗️  Building minified files..."
npm run build

if [ $? -eq 0 ]; then
    echo "✅ Build completed successfully!"
    echo ""
    echo "📊 File sizes:"
    echo "   CSS: $(ls -lh assets/css/admin.min.css | awk '{print $5}') (minified)"
    echo "   JS:  $(ls -lh assets/js/admin.min.js | awk '{print $5}') (minified)"
    echo ""
    
    # Create release ZIP file
    echo "📦 Creating release ZIP file..."
    
    # Clean up existing release folder
    rm -rf release/pdf-invoice-generator-for-memberpress
    mkdir -p release/pdf-invoice-generator-for-memberpress
    
    # Copy files to release folder (excluding screenshots)
    cp -r assets pdf-invoice-generator-for-memberpress.php install.php uninstall.php README.md CHANGELOG.md LICENSE composer.json release/pdf-invoice-generator-for-memberpress/
    
    # Create ZIP file
    cd release
    rm -f pdf-invoice-generator-for-memberpress.zip
    zip -r pdf-invoice-generator-for-memberpress.zip pdf-invoice-generator-for-memberpress/
    
    if [ $? -eq 0 ]; then
        echo "✅ Release ZIP created successfully!"
        echo "📁 Location: release/pdf-invoice-generator-for-memberpress.zip"
        echo "📏 Size: $(ls -lh pdf-invoice-generator-for-memberpress.zip | awk '{print $5}')"
        echo ""
        echo "🎉 Ready for production and distribution!"
    else
        echo "❌ ZIP creation failed!"
        exit 1
    fi
    
    cd ..
else
    echo "❌ Build failed!"
    exit 1
fi

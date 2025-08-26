#!/bin/bash

# MemberPress Bulk Invoice Generator - Build Script
# This script builds the minified CSS and JS files

echo "🔨 Building MemberPress Bulk Invoice Generator..."

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
    echo "🎉 Ready for production!"
else
    echo "❌ Build failed!"
    exit 1
fi

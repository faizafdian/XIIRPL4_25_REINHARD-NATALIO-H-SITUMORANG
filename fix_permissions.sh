#!/bin/bash
# Script to fix upload permissions for XAMPP

cd "/Applications/XAMPP/xamppfiles/htdocs/Aplikasi PO"

echo "Creating upload directories..."
mkdir -p uploads/invoices
mkdir -p uploads/completion

echo "Setting permissions..."
chmod -R 777 uploads
chmod -R 777 uploads/invoices
chmod -R 777 uploads/completion

echo "Checking permissions..."
ls -la uploads
ls -la uploads/invoices

echo ""
echo "✓ Permissions fixed!"
echo "If you still have issues, try running with sudo:"
echo "sudo chmod -R 777 uploads"


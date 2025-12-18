<?php
/**
 * File Upload Helper Functions
 */

function uploadFile($file, $folder = 'invoices', $allowed_types = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png']) {
    // Get absolute path to uploads directory
    $base_path = dirname(dirname(__FILE__));
    $upload_dir = $base_path . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . $folder . DIRECTORY_SEPARATOR;
    
    // Create directory if not exists with proper permissions
    if (!is_dir($upload_dir)) {
        if (!mkdir($upload_dir, 0755, true)) {
            throw new RuntimeException('Failed to create upload directory: ' . $upload_dir);
        }
    }
    
    // Ensure directory is writable
    if (!is_writable($upload_dir)) {
        // Try to fix permissions
        @chmod($upload_dir, 0777);
        // Also try to fix parent directories
        $parent_dir = dirname($upload_dir);
        if (is_dir($parent_dir) && !is_writable($parent_dir)) {
            @chmod($parent_dir, 0777);
        }
        
        if (!is_writable($upload_dir)) {
            // Last attempt: try to get current user and set ownership
            $current_user = get_current_user();
            if (function_exists('posix_getpwuid') && function_exists('posix_geteuid')) {
                $user_info = posix_getpwuid(posix_geteuid());
                $current_user = $user_info['name'] ?? $current_user;
            }
            
            throw new RuntimeException('Upload directory is not writable: ' . $upload_dir . 
                '. Please run: chmod -R 777 "' . $upload_dir . '" or contact administrator.');
        }
    }
    
    if (!isset($file['error']) || is_array($file['error'])) {
        throw new RuntimeException('Invalid parameters.');
    }
    
    switch ($file['error']) {
        case UPLOAD_ERR_OK:
            break;
        case UPLOAD_ERR_NO_FILE:
            throw new RuntimeException('No file sent.');
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            throw new RuntimeException('Exceeded filesize limit.');
        default:
            throw new RuntimeException('Unknown errors.');
    }
    
    if ($file['size'] > 10000000) { // 10MB max
        throw new RuntimeException('Exceeded filesize limit (max 10MB).');
    }
    
    $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if (!in_array($file_ext, $allowed_types)) {
        throw new RuntimeException('Invalid file type. Allowed: ' . implode(', ', $allowed_types));
    }
    
    $filename = uniqid('po_', true) . '.' . $file_ext;
    $filepath = $upload_dir . $filename;
    
    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        throw new RuntimeException('Failed to move uploaded file to: ' . $filepath . '. Check directory permissions.');
    }
    
    // Set file permissions
    chmod($filepath, 0644);
    
    return 'uploads/' . $folder . '/' . $filename;
}

function deleteFile($filepath) {
    if ($filepath) {
        $base_path = dirname(dirname(__FILE__));
        $full_path = $base_path . DIRECTORY_SEPARATOR . $filepath;
        if (file_exists($full_path)) {
            unlink($full_path);
        }
    }
}

function getFileUrl($filepath) {
    if (!$filepath) return '';
    return BASE_URL . $filepath;
}
?>


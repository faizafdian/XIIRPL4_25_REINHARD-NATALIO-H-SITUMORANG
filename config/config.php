<?php
// Application Configuration
session_start();

// Base URL
define('BASE_URL', 'http://localhost/Aplikasi%20PO/');
define('BASE_PATH', __DIR__ . '/../');

// Timezone
date_default_timezone_set('Asia/Jakarta');

// Error Reporting (set to 0 in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include database
require_once __DIR__ . '/database.php';

// Include file upload helper
require_once __DIR__ . '/file_upload.php';

// Helper Functions
function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['role']);
}

function isAdmin() {
    return isLoggedIn() && $_SESSION['role'] === 'admin';
}

function isPartner() {
    return isLoggedIn() && $_SESSION['role'] === 'partner';
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: ' . BASE_URL . 'index.php');
        exit;
    }
}

function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        header('Location: ' . BASE_URL . 'partner/dashboard.php');
        exit;
    }
}

function requirePartner() {
    requireLogin();
    if (!isPartner()) {
        header('Location: ' . BASE_URL . 'admin/dashboard.php');
        exit;
    }
}

function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

function generatePONumber() {
    $prefix = 'PO';
    $year = date('Y');
    $month = date('m');
    $random = strtoupper(substr(uniqid(), -6));
    return $prefix . '-' . $year . $month . '-' . $random;
}

function logActivity($pdo, $user_id, $po_id, $action, $description = '') {
    try {
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        // Validate user_id exists
        if ($user_id) {
            $check_stmt = $pdo->prepare("SELECT id FROM users WHERE id = ?");
            $check_stmt->execute([$user_id]);
            if (!$check_stmt->fetch()) {
                $user_id = null; // Set to null if user doesn't exist
            }
        }
        
        // Validate po_id exists if provided
        if ($po_id) {
            $check_stmt = $pdo->prepare("SELECT id FROM purchase_orders WHERE id = ?");
            $check_stmt->execute([$po_id]);
            if (!$check_stmt->fetch()) {
                $po_id = null; // Set to null if PO doesn't exist
            }
        }
        
        $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, po_id, action, description, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$user_id, $po_id, $action, $description, $ip_address, $user_agent]);
    } catch (PDOException $e) {
        // Log error but don't break the application
        error_log("Activity log error: " . $e->getMessage());
    }
}

function createNotification($pdo, $user_id, $po_id, $type, $title, $message) {
    $stmt = $pdo->prepare("INSERT INTO notifications (user_id, po_id, type, title, message) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$user_id, $po_id, $type, $title, $message]);
}

function getUnreadNotifications($pdo, $user_id) {
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$user_id]);
    return $stmt->fetch()['count'];
}

/**
 * Get all PO statuses from database dynamically
 * This will get all distinct statuses that exist in purchase_orders table
 * and also try to get enum values from the column definition
 * This ensures all statuses are included even if they don't have data yet
 */
function getAllPOStatuses($pdo) {
    $statuses = [];
    
    // Method 1: Get enum values from column definition (most reliable)
    // This gets all possible statuses defined in the database schema
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM purchase_orders WHERE Field = 'status'");
        $column = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($column && isset($column['Type'])) {
            // Extract enum values from Type string like "enum('draft','sent','pending_review',...)"
            preg_match_all("/'([^']+)'/", $column['Type'], $matches);
            if (!empty($matches[1])) {
                $enum_statuses = $matches[1];
                $statuses = array_merge($statuses, $enum_statuses);
            }
        }
    } catch (PDOException $e) {
        error_log("Error getting enum values: " . $e->getMessage());
    }
    
    // Method 2: Get distinct statuses from actual data (fallback)
    // This ensures we also get any statuses that might exist in data but not in enum
    try {
        $stmt = $pdo->query("SELECT DISTINCT status FROM purchase_orders WHERE status IS NOT NULL ORDER BY status");
        $data_statuses = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $statuses = array_merge($statuses, $data_statuses);
    } catch (PDOException $e) {
        error_log("Error getting distinct statuses: " . $e->getMessage());
    }
    
    // Remove duplicates
    $statuses = array_unique($statuses);
    
    // If no statuses found, return default list as fallback
    if (empty($statuses)) {
        $statuses = ['draft', 'sent', 'pending_review', 'approved', 'rejected', 'in_progress', 'completed', 'closed'];
    }
    
    // Sort statuses according to user's preferred order
    $statuses = sortStatusesByOrder($statuses);
    
    return $statuses;
}

/**
 * Sort statuses according to the preferred order:
 * 1. Disetujui (approved)
 * 2. Ditutup (closed)
 * 3. Selesai (completed)
 * 4. Draft (draft)
 * 5. Sedang di proses (in_progress)
 * 6. Menunggu review (pending_review)
 * 7. Ditolak (rejected)
 * 8. Dikirim (sent)
 */
function sortStatusesByOrder($statuses) {
    $preferred_order = [
        'approved',      // 1. Disetujui
        'closed',        // 2. Ditutup
        'completed',     // 3. Selesai
        'draft',         // 4. Draft
        'in_progress',   // 5. Sedang di proses
        'pending_review', // 6. Menunggu review
        'rejected',      // 7. Ditolak
        'sent'           // 8. Dikirim
    ];
    
    // Create ordered array
    $ordered = [];
    $remaining = [];
    
    // First, add statuses in preferred order
    foreach ($preferred_order as $status) {
        if (in_array($status, $statuses)) {
            $ordered[] = $status;
        }
    }
    
    // Then, add any remaining statuses that are not in preferred order
    foreach ($statuses as $status) {
        if (!in_array($status, $preferred_order)) {
            $remaining[] = $status;
        }
    }
    
    // Combine ordered and remaining
    return array_merge($ordered, $remaining);
}

/**
 * Get status label in Indonesian
 * Automatically handles new statuses by converting them to readable format
 */
function getStatusLabel($status) {
    $labels = [
        'draft' => '📝 Draft',
        'sent' => '📤 Dikirim',
        'pending_review' => '⏳ Menunggu Review',
        'approved' => '✅ Disetujui',
        'rejected' => '❌ Ditolak',
        'in_progress' => '⚙️ Sedang Diproses',
        'completed' => '✅ Selesai',
        'closed' => '🔒 Ditutup'
    ];
    
    // If status exists in labels, return it
    if (isset($labels[$status])) {
        return $labels[$status];
    }
    
    // Otherwise, convert status to readable format
    // e.g., "new_status" -> "New Status"
    $formatted = str_replace('_', ' ', $status);
    $formatted = ucwords($formatted);
    return $formatted;
}

/**
 * Get status color
 * Automatically assigns colors to new statuses based on keywords
 */
function getStatusColor($status) {
    $colors = [
        'draft' => '#6b7280',
        'sent' => '#3b82f6',
        'pending_review' => '#f59e0b',
        'approved' => '#10b981',
        'rejected' => '#ef4444',
        'in_progress' => '#8b5cf6',
        'completed' => '#10b981',
        'closed' => '#6b7280'
    ];
    
    // If status exists in colors, return it
    if (isset($colors[$status])) {
        return $colors[$status];
    }
    
    // Auto-assign color based on status keywords
    $status_lower = strtolower($status);
    if (strpos($status_lower, 'reject') !== false || strpos($status_lower, 'cancel') !== false) {
        return '#ef4444'; // Red for rejected/cancelled
    } elseif (strpos($status_lower, 'approv') !== false || strpos($status_lower, 'accept') !== false) {
        return '#10b981'; // Green for approved/accepted
    } elseif (strpos($status_lower, 'pending') !== false || strpos($status_lower, 'wait') !== false) {
        return '#f59e0b'; // Orange for pending/waiting
    } elseif (strpos($status_lower, 'progress') !== false || strpos($status_lower, 'process') !== false) {
        return '#8b5cf6'; // Purple for in progress/processing
    } elseif (strpos($status_lower, 'complete') !== false || strpos($status_lower, 'done') !== false) {
        return '#10b981'; // Green for completed/done
    } elseif (strpos($status_lower, 'close') !== false || strpos($status_lower, 'finish') !== false) {
        return '#6b7280'; // Gray for closed/finished
    } else {
        return '#6b7280'; // Default gray
    }
}

/**
 * Get status priority for sorting (lower number = higher priority)
 * Automatically assigns priority to new statuses
 */
function getStatusPriority($status) {
    $priorities = [
        'rejected' => 1,
        'pending_review' => 2,
        'sent' => 3,
        'approved' => 4,
        'in_progress' => 5,
        'draft' => 6,
        'completed' => 7,
        'closed' => 8
    ];
    
    // If status exists in priorities, return it
    if (isset($priorities[$status])) {
        return $priorities[$status];
    }
    
    // Auto-assign priority based on status keywords
    $status_lower = strtolower($status);
    if (strpos($status_lower, 'reject') !== false || strpos($status_lower, 'cancel') !== false) {
        return 1; // Highest priority for rejected/cancelled
    } elseif (strpos($status_lower, 'pending') !== false || strpos($status_lower, 'wait') !== false) {
        return 2; // High priority for pending/waiting
    } elseif (strpos($status_lower, 'sent') !== false || strpos($status_lower, 'send') !== false) {
        return 3; // Medium-high priority for sent
    } elseif (strpos($status_lower, 'approv') !== false || strpos($status_lower, 'accept') !== false) {
        return 4; // Medium priority for approved/accepted
    } elseif (strpos($status_lower, 'progress') !== false || strpos($status_lower, 'process') !== false) {
        return 5; // Medium-low priority for in progress
    } elseif (strpos($status_lower, 'draft') !== false) {
        return 6; // Low priority for draft
    } elseif (strpos($status_lower, 'complete') !== false || strpos($status_lower, 'done') !== false) {
        return 7; // Very low priority for completed
    } elseif (strpos($status_lower, 'close') !== false || strpos($status_lower, 'finish') !== false) {
        return 8; // Lowest priority for closed
    } else {
        return 50; // Default medium priority for unknown statuses
    }
}
?>


<?php
if (!isLoggedIn()) {
    header('Location: ' . BASE_URL . 'index.php');
    exit;
}

$pdo = getDBConnection();
$unread_notifications = getUnreadNotifications($pdo, $_SESSION['user_id']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . ' - ' : ''; ?>PO Management System</title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/style.css">
</head>
<body>
    <!-- Mobile Menu Toggle -->
    <button class="mobile-menu-toggle" onclick="toggleSidebar()" aria-label="Toggle Menu">
        ☰
    </button>
    
    <!-- Mobile Overlay -->
    <div class="mobile-menu-overlay" onclick="closeSidebar()"></div>
    
    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <h1 class="sidebar-brand">PO Management</h1>
            <p class="sidebar-subtitle">Sistem Manajemen Purchase Order</p>
        </div>
        
        <nav class="sidebar-nav">
            <?php if (isAdmin()): ?>
                <a href="<?php echo BASE_URL; ?>admin/dashboard.php" class="nav-item <?php echo (strpos($_SERVER['PHP_SELF'], 'admin/dashboard.php') !== false) ? 'active' : ''; ?>">
                    <span class="nav-icon">📊</span>
                    <span class="nav-text">Dashboard</span>
                </a>
                <a href="<?php echo BASE_URL; ?>admin/po_list.php" class="nav-item <?php echo (strpos($_SERVER['PHP_SELF'], 'admin/po_list.php') !== false || strpos($_SERVER['PHP_SELF'], 'admin/po_create.php') !== false || strpos($_SERVER['PHP_SELF'], 'admin/po_edit.php') !== false || strpos($_SERVER['PHP_SELF'], 'admin/po_view.php') !== false) ? 'active' : ''; ?>">
                    <span class="nav-icon">📋</span>
                    <span class="nav-text">Manajemen PO</span>
                </a>
                <a href="<?php echo BASE_URL; ?>admin/partners.php" class="nav-item <?php echo (strpos($_SERVER['PHP_SELF'], 'admin/partners.php') !== false || strpos($_SERVER['PHP_SELF'], 'admin/partner_') !== false) ? 'active' : ''; ?>">
                    <span class="nav-icon">👥</span>
                    <span class="nav-text">Manajemen Partner</span>
                </a>
                <a href="<?php echo BASE_URL; ?>admin/activity_logs.php" class="nav-item <?php echo (strpos($_SERVER['PHP_SELF'], 'admin/activity_logs.php') !== false) ? 'active' : ''; ?>">
                    <span class="nav-icon">📝</span>
                    <span class="nav-text">Log Aktivitas</span>
                </a>
            <?php else: ?>
                <a href="<?php echo BASE_URL; ?>partner/dashboard.php" class="nav-item <?php echo (strpos($_SERVER['PHP_SELF'], 'partner/dashboard.php') !== false) ? 'active' : ''; ?>">
                    <span class="nav-icon">📊</span>
                    <span class="nav-text">Dashboard</span>
                </a>
                <a href="<?php echo BASE_URL; ?>partner/po_list.php" class="nav-item <?php echo (strpos($_SERVER['PHP_SELF'], 'partner/po_list.php') !== false || strpos($_SERVER['PHP_SELF'], 'partner/po_view.php') !== false || strpos($_SERVER['PHP_SELF'], 'partner/po_accept.php') !== false || strpos($_SERVER['PHP_SELF'], 'partner/po_reject.php') !== false) ? 'active' : ''; ?>">
                    <span class="nav-icon">📋</span>
                    <span class="nav-text">Daftar PO</span>
                </a>
                <a href="<?php echo BASE_URL; ?>partner/notifications.php" class="nav-item <?php echo (strpos($_SERVER['PHP_SELF'], 'partner/notifications.php') !== false) ? 'active' : ''; ?>">
                    <span class="nav-icon">🔔</span>
                    <span class="nav-text">Notifikasi</span>
                    <?php if ($unread_notifications > 0): ?>
                        <span class="nav-badge"><?php echo $unread_notifications; ?></span>
                    <?php endif; ?>
                </a>
            <?php endif; ?>
        </nav>
        
        <div class="sidebar-footer">
            <div class="user-profile">
                <div class="user-avatar"><?php echo strtoupper(substr($_SESSION['full_name'], 0, 1)); ?></div>
                <div class="user-details">
                    <div class="user-name"><?php echo htmlspecialchars($_SESSION['full_name']); ?></div>
                    <div class="user-role"><?php echo isAdmin() ? 'Administrator' : 'Partner'; ?></div>
                </div>
            </div>
            <a href="<?php echo BASE_URL; ?>logout.php" class="nav-item logout-item">
                <span class="nav-icon">🚪</span>
                <span class="nav-text">Keluar</span>
            </a>
        </div>
    </aside>
    
    <!-- Main Content -->
    <div class="main-wrapper">
        <main class="main-content">


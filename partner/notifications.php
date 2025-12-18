<?php
require_once '../config/config.php';
requirePartner();

$pdo = getDBConnection();
$page_title = 'Notifikasi';

$partner_id = $_SESSION['user_id'];

// Mark all as read if requested
if (isset($_GET['mark_all_read'])) {
    $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
    $stmt->execute([$partner_id]);
    header('Location: notifications.php');
    exit;
}

// Mark single as read
if (isset($_GET['mark_read'])) {
    $notif_id = intval($_GET['mark_read']);
    $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
    $stmt->execute([$notif_id, $partner_id]);
    header('Location: notifications.php');
    exit;
}

// Pagination
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;

$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM notifications WHERE user_id = ?");
$stmt->execute([$partner_id]);
$total = $stmt->fetch()['total'];
$total_pages = ceil($total / $per_page);

$stmt = $pdo->prepare("
    SELECT n.*, po.po_number, po.title as po_title
    FROM notifications n
    LEFT JOIN purchase_orders po ON n.po_id = po.id
    WHERE n.user_id = ?
    ORDER BY n.created_at DESC
    LIMIT ? OFFSET ?
");
$stmt->execute([$partner_id, $per_page, $offset]);
$notifications = $stmt->fetchAll();

include '../includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h2 class="card-title">Notifikasi</h2>
        <a href="notifications.php?mark_all_read=1" class="btn btn-sm btn-primary">Tandai Semua Dibaca</a>
    </div>
    
    <div style="display: flex; flex-direction: column; gap: 0.75rem;">
        <?php if (empty($notifications)): ?>
            <p style="text-align: center; color: var(--text-secondary); padding: 2rem;">Tidak ada notifikasi</p>
        <?php else: ?>
            <?php foreach ($notifications as $notif): ?>
                <div style="padding: 1rem; background-color: <?php echo $notif['is_read'] ? 'var(--card-bg)' : '#dbeafe'; ?>; border-radius: 6px; border-left: 4px solid var(--primary-color);">
                    <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 0.5rem;">
                        <div style="flex: 1;">
                            <h3 style="margin-bottom: 0.25rem; font-size: 1rem;"><?php echo htmlspecialchars($notif['title']); ?></h3>
                            <p style="color: var(--text-secondary); font-size: 0.875rem; margin-bottom: 0.5rem;">
                                <?php echo htmlspecialchars($notif['message']); ?>
                            </p>
                            <?php if ($notif['po_number']): ?>
                                <div style="margin-top: 0.5rem;">
                                    <span style="font-size: 0.8125rem; color: var(--text-secondary);">PO: </span>
                                    <a href="po_view.php?id=<?php echo $notif['po_id']; ?>" style="color: var(--primary-color); font-weight: 600;">
                                        <?php echo htmlspecialchars($notif['po_number']); ?>
                                    </a>
                                    <span style="font-size: 0.8125rem; color: var(--text-secondary); margin-left: 0.5rem;">
                                        - <?php echo htmlspecialchars($notif['po_title']); ?>
                                    </span>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div style="text-align: right; margin-left: 1rem;">
                            <span style="font-size: 0.75rem; color: var(--text-secondary); display: block; margin-bottom: 0.5rem;">
                                <?php echo date('d/m/Y H:i', strtotime($notif['created_at'])); ?>
                            </span>
                            <?php if (!$notif['is_read']): ?>
                                <a href="notifications.php?mark_read=<?php echo $notif['id']; ?>" class="btn btn-sm btn-outline">Tandai Dibaca</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    
    <?php if ($total_pages > 1): ?>
        <div class="pagination" style="margin-top: 1.5rem;">
            <?php if ($page > 1): ?>
                <a href="?page=<?php echo $page - 1; ?>">« Prev</a>
            <?php endif; ?>
            
            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                <a href="?page=<?php echo $i; ?>" class="<?php echo $i === $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
            <?php endfor; ?>
            
            <?php if ($page < $total_pages): ?>
                <a href="?page=<?php echo $page + 1; ?>">Next »</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>


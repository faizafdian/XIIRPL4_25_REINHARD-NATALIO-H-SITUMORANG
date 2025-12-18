<?php
require_once '../config/config.php';
requirePartner();

$pdo = getDBConnection();
$page_title = 'Dashboard Partner';

// Get statistics for this partner
$partner_id = $_SESSION['user_id'];

// Get all statuses dynamically from database
$all_statuses = getAllPOStatuses($pdo);

// Status operasional yang penting untuk ditampilkan
$important_statuses = ['approved', 'in_progress', 'rejected'];

// Get statistics untuk semua status
$stats = [];
foreach ($all_statuses as $status) {
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM purchase_orders WHERE partner_id = ? AND status = ?");
    $stmt->execute([$partner_id, $status]);
    $stats[$status] = $stmt->fetch()['count'];
}

// Total semua PO untuk partner ini - hitung langsung dari database
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM purchase_orders WHERE partner_id = ?");
$stmt->execute([$partner_id]);
$stats['total'] = $stmt->fetch()['count'];

// Get PO dengan status operasional untuk partner ini
$operational_statuses = array_filter($all_statuses, function($status) {
    return !in_array($status, ['draft', 'completed', 'closed']);
});

if (!empty($operational_statuses)) {
    $placeholders = str_repeat('?,', count($operational_statuses) - 1) . '?';
    $status_list = array_values($operational_statuses);
    $params = array_merge([$partner_id], $status_list);
    
    // Build ORDER BY clause dynamically
    $order_cases = [];
    foreach ($status_list as $status) {
        $order_cases[] = "WHEN '$status' THEN " . getStatusPriority($status);
    }
    $order_by = "CASE po.status " . implode(' ', $order_cases) . " END";
    
    $stmt = $pdo->prepare("
        SELECT po.*, creator.full_name as creator_name
        FROM purchase_orders po
        JOIN users creator ON po.created_by = creator.id
        WHERE po.partner_id = ? AND po.status IN ($placeholders)
        ORDER BY $order_by, po.updated_at DESC, po.created_at DESC
        LIMIT 20
    ");
    $stmt->execute($params);
    $operational_pos = $stmt->fetchAll();
} else {
    $operational_pos = [];
}

include '../includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h2 class="card-title">Dashboard PO</h2>
    </div>
    
    <div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));">
        <a href="po_list.php" class="stat-card" style="text-decoration: none; display: block; cursor: pointer;">
            <div class="stat-value"><?php echo $stats['total']; ?></div>
            <div class="stat-label">Total PO</div>
        </a>
        
        <?php foreach ($important_statuses as $status): ?>
            <?php if (in_array($status, $all_statuses)): ?>
                <a href="po_list.php?status=<?php echo urlencode($status); ?>" class="stat-card" style="text-decoration: none; display: block; cursor: pointer;">
                    <div class="stat-value" style="color: <?php echo getStatusColor($status); ?>;"><?php echo $stats[$status]; ?></div>
                    <div class="stat-label"><?php echo getStatusLabel($status); ?></div>
                </a>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
</div>

<div class="card" style="margin-top: 1.5rem;">
    <div class="card-header">
        <h3 class="card-title">Daftar PO</h3>
        <a href="po_list.php" class="btn btn-sm btn-primary">Lihat Semua</a>
    </div>
    
    <?php if (empty($operational_pos)): ?>
        <div style="padding: 2rem; text-align: center; color: var(--text-secondary);">
            <p>Tidak ada PO dengan status operasional saat ini</p>
        </div>
    <?php else: ?>
        <table class="table">
            <thead>
                <tr>
                    <th>No. PO</th>
                    <th>Judul</th>
                    <th>Total</th>
                    <th>Status</th>
                    <th>Tanggal Update</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($operational_pos as $po): ?>
                    <tr>
                        <td><strong><a href="po_view.php?id=<?php echo $po['id']; ?>" style="color: var(--primary-color);"><?php echo htmlspecialchars($po['po_number']); ?></a></strong></td>
                        <td><?php echo htmlspecialchars($po['title']); ?></td>
                        <td><strong>Rp <?php echo number_format($po['total_amount'], 0, ',', '.'); ?></strong></td>
                        <td>
                            <?php
                            $label = getStatusLabel($po['status']);
                            $color = getStatusColor($po['status']);
                            if ($po['status'] === 'sent') {
                                $label = '📥 Dikirim ke Saya';
                            }
                            ?>
                            <span class="status-badge status-<?php echo $po['status']; ?>" style="background: <?php echo $color; ?>15; color: <?php echo $color; ?>; border: 1px solid <?php echo $color; ?>30;">
                                <?php echo $label; ?>
                            </span>
                        </td>
                        <td>
                            <?php 
                            $date = $po['updated_at'] ? $po['updated_at'] : $po['created_at'];
                            echo date('d/m/Y H:i', strtotime($date)); 
                            ?>
                        </td>
                        <td>
                            <a href="po_view.php?id=<?php echo $po['id']; ?>" class="btn btn-sm btn-primary">Detail</a>
                            <?php if ($po['status'] === 'sent' || $po['status'] === 'pending_review'): ?>
                                <a href="po_accept.php?id=<?php echo $po['id']; ?>" class="btn btn-sm btn-success" onclick="return confirm('Setujui PO ini?');">Setujui</a>
                                <a href="po_reject.php?id=<?php echo $po['id']; ?>" class="btn btn-sm btn-danger">Tolak</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>


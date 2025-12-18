<?php
require_once '../config/config.php';
requireAdmin();

$pdo = getDBConnection();
$page_title = 'Manajemen PO';

// Get all statuses dynamically from database
$all_statuses = getAllPOStatuses($pdo);

// Search and filter
$search = sanitizeInput($_GET['search'] ?? '');
$status_filter = sanitizeInput($_GET['status'] ?? '');

$where = [];
$params = [];

if (!empty($search)) {
    $where[] = "(po.po_number LIKE ? OR po.title LIKE ? OR u.full_name LIKE ? OR u.company_name LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
}

if (!empty($status_filter)) {
    if ($status_filter === 'operational') {
        // Filter untuk status operasional (exclude draft, completed, closed)
        $operational_statuses = array_filter($all_statuses, function($status) {
            return !in_array($status, ['draft', 'completed', 'closed']);
        });
        if (!empty($operational_statuses)) {
            $placeholders = str_repeat('?,', count($operational_statuses) - 1) . '?';
            $where[] = "po.status IN ($placeholders)";
            $params = array_merge($params, array_values($operational_statuses));
        }
    } else {
        $where[] = "po.status = ?";
        $params[] = $status_filter;
    }
}

$where_sql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

// Pagination
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;

$stmt = $pdo->prepare("
    SELECT COUNT(*) as total
    FROM purchase_orders po
    JOIN users u ON po.partner_id = u.id
    $where_sql
");
$stmt->execute($params);
$total = $stmt->fetch()['total'];
$total_pages = ceil($total / $per_page);

$stmt = $pdo->prepare("
    SELECT po.*, u.full_name as partner_name, u.company_name, u.email as partner_email
    FROM purchase_orders po
    JOIN users u ON po.partner_id = u.id
    $where_sql
    ORDER BY po.created_at DESC
    LIMIT ? OFFSET ?
");
$params[] = $per_page;
$params[] = $offset;
$stmt->execute($params);
$pos = $stmt->fetchAll();

include '../includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h2 class="card-title">Manajemen Purchase Order</h2>
        <a href="po_create.php" class="btn btn-primary">Buat PO Baru</a>
    </div>
    
    <div class="search-filter">
        <form method="GET" action="" style="display: flex; gap: 1rem; width: 100%;">
            <input type="text" name="search" class="form-control" placeholder="Cari PO, partner, atau judul..." value="<?php echo htmlspecialchars($search); ?>">
            <select name="status" class="form-control" style="max-width: 200px;">
                <option value="">Semua Status</option>
                <?php foreach ($all_statuses as $status): ?>
                    <option value="<?php echo htmlspecialchars($status); ?>" <?php echo $status_filter === $status ? 'selected' : ''; ?>>
                        <?php echo getStatusLabel($status); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-primary">Cari</button>
            <a href="po_list.php" class="btn btn-outline">Reset</a>
        </form>
    </div>
    
    <table class="table">
        <thead>
            <tr>
                <th>No. PO</th>
                <th>Judul</th>
                <th>Partner</th>
                <th>Total</th>
                <th>Status</th>
                <th>Tanggal Dibuat</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($pos)): ?>
                <tr>
                    <td colspan="7" style="text-align: center; color: var(--text-secondary); padding: 2rem;">Tidak ada data</td>
                </tr>
            <?php else: ?>
                <?php foreach ($pos as $po): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($po['po_number']); ?></strong></td>
                        <td><?php echo htmlspecialchars($po['title']); ?></td>
                        <td>
                            <?php echo htmlspecialchars($po['partner_name']); ?>
                            <?php if ($po['company_name']): ?>
                                <br><small style="color: var(--text-secondary);"><?php echo htmlspecialchars($po['company_name']); ?></small>
                            <?php endif; ?>
                        </td>
                        <td>Rp <?php echo number_format($po['total_amount'], 0, ',', '.'); ?></td>
                        <td>
                            <?php
                            $label = getStatusLabel($po['status']);
                            $color = getStatusColor($po['status']);
                            ?>
                            <span class="status-badge status-<?php echo $po['status']; ?>" style="background: <?php echo $color; ?>15; color: <?php echo $color; ?>; border: 1px solid <?php echo $color; ?>30;">
                                <?php echo $label; ?>
                            </span>
                        </td>
                        <td><?php echo date('d/m/Y H:i', strtotime($po['created_at'])); ?></td>
                        <td>
                            <div class="action-buttons">
                                <a href="po_view.php?id=<?php echo $po['id']; ?>" class="btn btn-sm btn-primary">Lihat</a>
                                <?php if ($po['status'] === 'draft'): ?>
                                    <a href="po_edit.php?id=<?php echo $po['id']; ?>" class="btn btn-sm btn-warning">Edit</a>
                                <?php endif; ?>
                                <?php if ($po['status'] === 'draft' || $po['status'] === 'revised'): ?>
                                    <a href="po_send.php?id=<?php echo $po['id']; ?>" class="btn btn-sm btn-success" onclick="return confirm('Kirim PO ini ke partner?');">Kirim</a>
                                <?php endif; ?>
                                <a href="po_report.php?id=<?php echo $po['id']; ?>" class="btn btn-sm btn-secondary" target="_blank">Cetak</a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
    
    <?php if ($total_pages > 1): ?>
        <div class="pagination">
            <?php if ($page > 1): ?>
                <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>">« Prev</a>
            <?php endif; ?>
            
            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>" class="<?php echo $i === $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
            <?php endfor; ?>
            
            <?php if ($page < $total_pages): ?>
                <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>">Next »</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>


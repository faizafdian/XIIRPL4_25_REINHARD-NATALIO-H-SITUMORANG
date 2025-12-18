<?php
require_once '../config/config.php';
requireAdmin();

$pdo = getDBConnection();
$page_title = 'Log Aktivitas';

// Search and filter
$search = sanitizeInput($_GET['search'] ?? '');
$action_filter = sanitizeInput($_GET['action'] ?? '');

$where = [];
$params = [];

if (!empty($search)) {
    $where[] = "(u.full_name LIKE ? OR po.po_number LIKE ? OR al.description LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param]);
}

if (!empty($action_filter)) {
    $where[] = "al.action = ?";
    $params[] = $action_filter;
}

$where_sql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

// Pagination
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 50;
$offset = ($page - 1) * $per_page;

$stmt = $pdo->prepare("
    SELECT COUNT(*) as total
    FROM activity_logs al
    LEFT JOIN users u ON al.user_id = u.id
    LEFT JOIN purchase_orders po ON al.po_id = po.id
    $where_sql
");
$stmt->execute($params);
$total = $stmt->fetch()['total'];
$total_pages = ceil($total / $per_page);

$stmt = $pdo->prepare("
    SELECT al.*, u.full_name, u.username, po.po_number, po.title as po_title
    FROM activity_logs al
    LEFT JOIN users u ON al.user_id = u.id
    LEFT JOIN purchase_orders po ON al.po_id = po.id
    $where_sql
    ORDER BY al.created_at DESC
    LIMIT ? OFFSET ?
");
$params[] = $per_page;
$params[] = $offset;
$stmt->execute($params);
$logs = $stmt->fetchAll();

// Get unique actions for filter
$stmt = $pdo->query("SELECT DISTINCT action FROM activity_logs ORDER BY action");
$actions = $stmt->fetchAll(PDO::FETCH_COLUMN);

include '../includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h2 class="card-title">Log Aktivitas Sistem</h2>
    </div>
    
    <div class="search-filter">
        <form method="GET" action="" style="display: flex; gap: 1rem; width: 100%;">
            <input type="text" name="search" class="form-control" placeholder="Cari aktivitas..." value="<?php echo htmlspecialchars($search); ?>">
            <select name="action" class="form-control" style="max-width: 200px;">
                <option value="">Semua Aksi</option>
                <?php foreach ($actions as $action): ?>
                    <option value="<?php echo htmlspecialchars($action); ?>" <?php echo $action_filter === $action ? 'selected' : ''; ?>>
                        <?php echo ucfirst(str_replace('_', ' ', $action)); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-primary">Cari</button>
            <a href="activity_logs.php" class="btn btn-outline">Reset</a>
        </form>
    </div>
    
    <div style="max-height: 600px; overflow-y: auto;">
        <table class="table">
            <thead>
                <tr>
                    <th style="position: sticky; top: 0; background: var(--bg-color);">Waktu</th>
                    <th style="position: sticky; top: 0; background: var(--bg-color);">User</th>
                    <th style="position: sticky; top: 0; background: var(--bg-color);">Aksi</th>
                    <th style="position: sticky; top: 0; background: var(--bg-color);">PO</th>
                    <th style="position: sticky; top: 0; background: var(--bg-color);">Deskripsi</th>
                    <th style="position: sticky; top: 0; background: var(--bg-color);">IP Address</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($logs)): ?>
                    <tr>
                        <td colspan="6" style="text-align: center; color: var(--text-secondary); padding: 2rem;">Tidak ada data</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($logs as $log): ?>
                        <tr>
                            <td style="font-size: 0.8125rem; color: var(--text-secondary);">
                                <?php echo date('d/m/Y H:i:s', strtotime($log['created_at'])); ?>
                            </td>
                            <td>
                                <?php if ($log['full_name']): ?>
                                    <?php echo htmlspecialchars($log['full_name']); ?>
                                    <br><small style="color: var(--text-secondary);"><?php echo htmlspecialchars($log['username']); ?></small>
                                <?php else: ?>
                                    <span style="color: var(--text-secondary);">System</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="status-badge" style="background: var(--info-color); color: white;">
                                    <?php echo ucfirst(str_replace('_', ' ', $log['action'])); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($log['po_number']): ?>
                                    <a href="po_view.php?id=<?php echo $log['po_id']; ?>" style="color: var(--primary-color);">
                                        <?php echo htmlspecialchars($log['po_number']); ?>
                                    </a>
                                    <br><small style="color: var(--text-secondary);"><?php echo htmlspecialchars($log['po_title']); ?></small>
                                <?php else: ?>
                                    <span style="color: var(--text-secondary);">-</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($log['description']); ?></td>
                            <td style="font-size: 0.8125rem; color: var(--text-secondary);">
                                <?php echo htmlspecialchars($log['ip_address']); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <?php if ($total_pages > 1): ?>
        <div class="pagination">
            <?php if ($page > 1): ?>
                <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&action=<?php echo urlencode($action_filter); ?>">« Prev</a>
            <?php endif; ?>
            
            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&action=<?php echo urlencode($action_filter); ?>" class="<?php echo $i === $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
            <?php endfor; ?>
            
            <?php if ($page < $total_pages): ?>
                <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&action=<?php echo urlencode($action_filter); ?>">Next »</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>


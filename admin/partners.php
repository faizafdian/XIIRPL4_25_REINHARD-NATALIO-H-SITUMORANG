<?php
require_once '../config/config.php';
requireAdmin();

$pdo = getDBConnection();
$page_title = 'Manajemen Partner';

// Search
$search = sanitizeInput($_GET['search'] ?? '');

$where = [];
$params = [];

if (!empty($search)) {
    $where[] = "(full_name LIKE ? OR company_name LIKE ? OR email LIKE ? OR username LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
}

$where_sql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) . ' AND role = "partner"' : 'WHERE role = "partner"';

// Pagination
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;

$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM users $where_sql");
$stmt->execute($params);
$total = $stmt->fetch()['total'];
$total_pages = ceil($total / $per_page);

$stmt = $pdo->prepare("SELECT * FROM users $where_sql ORDER BY created_at DESC LIMIT ? OFFSET ?");
$params[] = $per_page;
$params[] = $offset;
$stmt->execute($params);
$partners = $stmt->fetchAll();

include '../includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h2 class="card-title">Manajemen Partner</h2>
        <a href="partner_create.php" class="btn btn-primary">Tambah Partner</a>
    </div>
    
    <div class="search-filter">
        <form method="GET" action="" style="display: flex; gap: 1rem; width: 100%;">
            <input type="text" name="search" class="form-control" placeholder="Cari partner..." value="<?php echo htmlspecialchars($search); ?>">
            <button type="submit" class="btn btn-primary">Cari</button>
            <a href="partners.php" class="btn btn-outline">Reset</a>
        </form>
    </div>
    
    <table class="table">
        <thead>
            <tr>
                <th>Username</th>
                <th>Nama Lengkap</th>
                <th>Perusahaan</th>
                <th>Email</th>
                <th>Telepon</th>
                <th>Status</th>
                <th>Tanggal Daftar</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($partners)): ?>
                <tr>
                    <td colspan="8" style="text-align: center; color: var(--text-secondary); padding: 2rem;">Tidak ada data</td>
                </tr>
            <?php else: ?>
                <?php foreach ($partners as $partner): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($partner['username']); ?></strong></td>
                        <td><?php echo htmlspecialchars($partner['full_name']); ?></td>
                        <td><?php echo htmlspecialchars($partner['company_name'] ?? '-'); ?></td>
                        <td><?php echo htmlspecialchars($partner['email']); ?></td>
                        <td><?php echo htmlspecialchars($partner['phone'] ?? '-'); ?></td>
                        <td>
                            <span class="status-badge <?php echo $partner['status'] === 'active' ? 'status-approved' : 'status-rejected'; ?>">
                                <?php echo ucfirst($partner['status']); ?>
                            </span>
                        </td>
                        <td><?php echo date('d/m/Y', strtotime($partner['created_at'])); ?></td>
                        <td>
                            <div class="action-buttons">
                                <a href="partner_view.php?id=<?php echo $partner['id']; ?>" class="btn btn-sm btn-primary">Lihat</a>
                                <a href="partner_edit.php?id=<?php echo $partner['id']; ?>" class="btn btn-sm btn-warning">Edit</a>
                                <a href="partner_delete.php?id=<?php echo $partner['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Yakin hapus partner ini?');">Hapus</a>
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
                <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>">« Prev</a>
            <?php endif; ?>
            
            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>" class="<?php echo $i === $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
            <?php endfor; ?>
            
            <?php if ($page < $total_pages): ?>
                <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>">Next »</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>


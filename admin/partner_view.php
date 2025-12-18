<?php
require_once '../config/config.php';
requireAdmin();

$pdo = getDBConnection();
$page_title = 'Detail Partner';

$partner_id = intval($_GET['id'] ?? 0);

if (!$partner_id) {
    header('Location: partners.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role = 'partner'");
$stmt->execute([$partner_id]);
$partner = $stmt->fetch();

if (!$partner) {
    header('Location: partners.php');
    exit;
}

// Get PO history
$stmt = $pdo->prepare("
    SELECT * FROM purchase_orders 
    WHERE partner_id = ? 
    ORDER BY created_at DESC
    LIMIT 20
");
$stmt->execute([$partner_id]);
$po_history = $stmt->fetchAll();

include '../includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h2 class="card-title">Detail Partner</h2>
        <div class="action-buttons">
            <a href="partner_edit.php?id=<?php echo $partner_id; ?>" class="btn btn-warning">Edit</a>
            <a href="partners.php" class="btn btn-outline">Kembali</a>
        </div>
    </div>
    
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
        <div>
            <h3 style="margin-bottom: 0.75rem; color: var(--text-primary);">Informasi Akun</h3>
            <table style="width: 100%;">
                <tr>
                    <td style="padding: 0.5rem 0; color: var(--text-secondary); width: 40%;">Username:</td>
                    <td style="padding: 0.5rem 0;"><strong><?php echo htmlspecialchars($partner['username']); ?></strong></td>
                </tr>
                <tr>
                    <td style="padding: 0.5rem 0; color: var(--text-secondary);">Nama Lengkap:</td>
                    <td style="padding: 0.5rem 0;"><?php echo htmlspecialchars($partner['full_name']); ?></td>
                </tr>
                <tr>
                    <td style="padding: 0.5rem 0; color: var(--text-secondary);">Email:</td>
                    <td style="padding: 0.5rem 0;"><?php echo htmlspecialchars($partner['email']); ?></td>
                </tr>
                <tr>
                    <td style="padding: 0.5rem 0; color: var(--text-secondary);">Status:</td>
                    <td style="padding: 0.5rem 0;">
                        <span class="status-badge <?php echo $partner['status'] === 'active' ? 'status-approved' : 'status-rejected'; ?>">
                            <?php echo ucfirst($partner['status']); ?>
                        </span>
                    </td>
                </tr>
            </table>
        </div>
        
        <div>
            <h3 style="margin-bottom: 0.75rem; color: var(--text-primary);">Informasi Perusahaan</h3>
            <table style="width: 100%;">
                <?php if ($partner['company_name']): ?>
                    <tr>
                        <td style="padding: 0.5rem 0; color: var(--text-secondary); width: 40%;">Perusahaan:</td>
                        <td style="padding: 0.5rem 0;"><?php echo htmlspecialchars($partner['company_name']); ?></td>
                    </tr>
                <?php endif; ?>
                <?php if ($partner['phone']): ?>
                    <tr>
                        <td style="padding: 0.5rem 0; color: var(--text-secondary);">Telepon:</td>
                        <td style="padding: 0.5rem 0;"><?php echo htmlspecialchars($partner['phone']); ?></td>
                    </tr>
                <?php endif; ?>
                <?php if ($partner['address']): ?>
                    <tr>
                        <td style="padding: 0.5rem 0; color: var(--text-secondary);">Alamat:</td>
                        <td style="padding: 0.5rem 0;"><?php echo nl2br(htmlspecialchars($partner['address'])); ?></td>
                    </tr>
                <?php endif; ?>
                <tr>
                    <td style="padding: 0.5rem 0; color: var(--text-secondary);">Tanggal Daftar:</td>
                    <td style="padding: 0.5rem 0;"><?php echo date('d/m/Y H:i', strtotime($partner['created_at'])); ?></td>
                </tr>
            </table>
        </div>
    </div>
    
    <h3 style="margin-bottom: 0.75rem; color: var(--text-primary);">Riwayat PO</h3>
    <table class="table">
        <thead>
            <tr>
                <th>No. PO</th>
                <th>Judul</th>
                <th>Total</th>
                <th>Status</th>
                <th>Tanggal</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($po_history)): ?>
                <tr>
                    <td colspan="6" style="text-align: center; color: var(--text-secondary);">Tidak ada riwayat PO</td>
                </tr>
            <?php else: ?>
                <?php foreach ($po_history as $po): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($po['po_number']); ?></td>
                        <td><?php echo htmlspecialchars($po['title']); ?></td>
                        <td>Rp <?php echo number_format($po['total_amount'], 0, ',', '.'); ?></td>
                        <td><span class="status-badge status-<?php echo $po['status']; ?>"><?php echo ucfirst($po['status']); ?></span></td>
                        <td><?php echo date('d/m/Y', strtotime($po['created_at'])); ?></td>
                        <td><a href="po_view.php?id=<?php echo $po['id']; ?>" class="btn btn-sm btn-primary">Lihat</a></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php include '../includes/footer.php'; ?>


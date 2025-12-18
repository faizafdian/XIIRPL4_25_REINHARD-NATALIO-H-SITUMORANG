<?php
require_once '../config/config.php';
require_once '../config/file_upload.php';
requireAdmin();

$pdo = getDBConnection();
$page_title = 'Detail PO';

$po_id = intval($_GET['id'] ?? 0);

if (!$po_id) {
    header('Location: po_list.php');
    exit;
}

$stmt = $pdo->prepare("
    SELECT po.*, 
           u.full_name as partner_name, u.company_name, u.email as partner_email, u.phone as partner_phone,
           creator.full_name as creator_name
    FROM purchase_orders po
    JOIN users u ON po.partner_id = u.id
    JOIN users creator ON po.created_by = creator.id
    WHERE po.id = ?
");
$stmt->execute([$po_id]);
$po = $stmt->fetch();

if (!$po) {
    header('Location: po_list.php');
    exit;
}

// Items tidak diperlukan lagi karena data hanya ringkasan dari invoice

// Get revisions
$stmt = $pdo->prepare("
    SELECT pr.*, u.full_name
    FROM po_revisions pr
    JOIN users u ON pr.revised_by = u.id
    WHERE pr.po_id = ?
    ORDER BY pr.revision_number DESC
");
$stmt->execute([$po_id]);
$revisions = $stmt->fetchAll();

$success = isset($_GET['success']) ? 'PO berhasil dibuat!' : '';

include '../includes/header.php';
?>

<?php if ($success): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h2 class="card-title">Detail Purchase Order</h2>
        <div class="action-buttons">
            <?php if ($po['status'] === 'draft'): ?>
                <a href="po_edit.php?id=<?php echo $po_id; ?>" class="btn btn-warning">Edit</a>
            <?php endif; ?>
            <?php if ($po['status'] === 'draft'): ?>
                <?php if ($po['attachment']): ?>
                    <a href="po_send.php?id=<?php echo $po_id; ?>" class="btn btn-success" onclick="return confirm('Kirim PO ini ke partner untuk review? Pastikan file invoice sudah benar.');">📤 Kirim ke Partner</a>
                <?php else: ?>
                    <div style="background: var(--danger-light); padding: 0.75rem; border-radius: 6px; display: inline-block; margin-right: 0.5rem;">
                        <span style="color: var(--danger-color);">⚠️ File invoice wajib diupload sebelum kirim ke partner!</span>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
            <?php if ($po['status'] === 'rejected'): ?>
                <a href="po_revise.php?id=<?php echo $po_id; ?>" class="btn btn-warning">📝 Revisi PO</a>
            <?php endif; ?>
            <?php if ($po['status'] === 'approved'): ?>
                <div style="background: var(--info-light); padding: 0.75rem; border-radius: 6px; display: inline-block; margin-right: 0.5rem;">
                    <span style="color: var(--info-color);">⏳ Menunggu partner untuk memulai proses pengiriman...</span>
                </div>
            <?php endif; ?>
            <?php if ($po['status'] === 'in_progress'): ?>
                <a href="po_complete.php?id=<?php echo $po_id; ?>" class="btn btn-success">✅ Tandai Selesai</a>
            <?php endif; ?>
            <?php if ($po['status'] === 'completed'): ?>
                <a href="po_close.php?id=<?php echo $po_id; ?>" class="btn btn-secondary" onclick="return confirm('Tutup PO ini? Pastikan semua sudah selesai.');">🔒 Tutup PO</a>
            <?php endif; ?>
            <a href="po_report.php?id=<?php echo $po_id; ?>" class="btn btn-secondary" target="_blank">Cetak Laporan</a>
            <a href="po_list.php" class="btn btn-outline">Kembali</a>
        </div>
    </div>
    
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem; margin-bottom: 1.5rem;">
        <div>
            <h3 style="margin-bottom: 0.75rem; color: var(--text-primary);">Informasi PO</h3>
            <table style="width: 100%;">
                <tr>
                    <td style="padding: 0.5rem 0; color: var(--text-secondary); width: 40%;">No. PO:</td>
                    <td style="padding: 0.5rem 0;"><strong><?php echo htmlspecialchars($po['po_number']); ?></strong></td>
                </tr>
                <tr>
                    <td style="padding: 0.5rem 0; color: var(--text-secondary);">Judul:</td>
                    <td style="padding: 0.5rem 0;"><?php echo htmlspecialchars($po['title']); ?></td>
                </tr>
                <tr>
                    <td style="padding: 0.5rem 0; color: var(--text-secondary);">Status:</td>
                    <td style="padding: 0.5rem 0;">
                        <?php
                        $label = getStatusLabel($po['status']);
                        $color = getStatusColor($po['status']);
                        ?>
                        <span class="status-badge status-<?php echo $po['status']; ?>" style="background: <?php echo $color; ?>15; color: <?php echo $color; ?>; border: 1px solid <?php echo $color; ?>30;">
                            <?php echo $label; ?>
                        </span>
                    </td>
                </tr>
                <tr>
                    <td style="padding: 0.5rem 0; color: var(--text-secondary);">Total:</td>
                    <td style="padding: 0.5rem 0;"><strong style="font-size: 1.125rem; color: var(--primary-color);">Rp <?php echo number_format($po['total_amount'], 0, ',', '.'); ?></strong></td>
                </tr>
                <tr>
                    <td style="padding: 0.5rem 0; color: var(--text-secondary);">Dibuat oleh:</td>
                    <td style="padding: 0.5rem 0;"><?php echo htmlspecialchars($po['creator_name']); ?></td>
                </tr>
                <tr>
                    <td style="padding: 0.5rem 0; color: var(--text-secondary);">Tanggal dibuat:</td>
                    <td style="padding: 0.5rem 0;"><?php echo date('d/m/Y H:i', strtotime($po['created_at'])); ?></td>
                </tr>
                <?php if ($po['sent_at']): ?>
                    <tr>
                        <td style="padding: 0.5rem 0; color: var(--text-secondary);">Dikirim pada:</td>
                        <td style="padding: 0.5rem 0;"><?php echo date('d/m/Y H:i', strtotime($po['sent_at'])); ?></td>
                    </tr>
                <?php endif; ?>
                <?php if ($po['approved_at']): ?>
                    <tr>
                        <td style="padding: 0.5rem 0; color: var(--text-secondary);">Disetujui pada:</td>
                        <td style="padding: 0.5rem 0;"><?php echo date('d/m/Y H:i', strtotime($po['approved_at'])); ?></td>
                    </tr>
                <?php endif; ?>
                <?php if ($po['rejected_at']): ?>
                    <tr>
                        <td style="padding: 0.5rem 0; color: var(--text-secondary);">Ditolak pada:</td>
                        <td style="padding: 0.5rem 0;"><?php echo date('d/m/Y H:i', strtotime($po['rejected_at'])); ?></td>
                    </tr>
                <?php endif; ?>
                <?php if ($po['approved_at_partner']): ?>
                    <tr>
                        <td style="padding: 0.5rem 0; color: var(--text-secondary);">Disetujui Partner:</td>
                        <td style="padding: 0.5rem 0;"><?php echo date('d/m/Y H:i', strtotime($po['approved_at_partner'])); ?></td>
                    </tr>
                <?php endif; ?>
                <?php if ($po['delivery_date']): ?>
                    <tr>
                        <td style="padding: 0.5rem 0; color: var(--text-secondary);">Tanggal Pengiriman:</td>
                        <td style="padding: 0.5rem 0;"><?php echo date('d/m/Y', strtotime($po['delivery_date'])); ?></td>
                    </tr>
                <?php endif; ?>
            </table>
        </div>
        
        <div>
            <h3 style="margin-bottom: 0.75rem; color: var(--text-primary);">Informasi Partner & Supplier</h3>
            <table style="width: 100%;">
                <tr>
                    <td style="padding: 0.5rem 0; color: var(--text-secondary); width: 40%;">Nama Partner:</td>
                    <td style="padding: 0.5rem 0;"><?php echo htmlspecialchars($po['partner_name']); ?></td>
                </tr>
                <?php if ($po['company_name_partner']): ?>
                    <tr>
                        <td style="padding: 0.5rem 0; color: var(--text-secondary);">Perusahaan Partner:</td>
                        <td style="padding: 0.5rem 0;"><strong><?php echo htmlspecialchars($po['company_name_partner']); ?></strong></td>
                    </tr>
                <?php endif; ?>
                <?php if ($po['supplier_name']): ?>
                    <tr>
                        <td style="padding: 0.5rem 0; color: var(--text-secondary);">Nama Supplier:</td>
                        <td style="padding: 0.5rem 0;"><strong><?php echo htmlspecialchars($po['supplier_name']); ?></strong></td>
                    </tr>
                <?php endif; ?>
                <?php if ($po['project_name']): ?>
                    <tr>
                        <td style="padding: 0.5rem 0; color: var(--text-secondary);">Nama Proyek:</td>
                        <td style="padding: 0.5rem 0;"><?php echo htmlspecialchars($po['project_name']); ?></td>
                    </tr>
                <?php endif; ?>
                <tr>
                    <td style="padding: 0.5rem 0; color: var(--text-secondary);">Email:</td>
                    <td style="padding: 0.5rem 0;"><?php echo htmlspecialchars($po['partner_email']); ?></td>
                </tr>
                <?php if ($po['partner_phone']): ?>
                    <tr>
                        <td style="padding: 0.5rem 0; color: var(--text-secondary);">Telepon:</td>
                        <td style="padding: 0.5rem 0;"><?php echo htmlspecialchars($po['partner_phone']); ?></td>
                    </tr>
                <?php endif; ?>
            </table>
        </div>
    </div>
    
    <?php if ($po['description']): ?>
        <div style="margin-bottom: 1.5rem;">
            <h3 style="margin-bottom: 0.75rem; color: var(--text-primary);">Deskripsi Barang/Jasa (Ringkasan)</h3>
            <div style="background: var(--bg-color); padding: 1rem; border-radius: 8px;">
                <p style="color: var(--text-primary); margin: 0;"><?php echo nl2br(htmlspecialchars($po['description'])); ?></p>
            </div>
        </div>
    <?php endif; ?>
    
    <?php if ($po['attachment']): ?>
        <div style="margin-bottom: 1.5rem;">
            <h3 style="margin-bottom: 0.75rem; color: var(--text-primary);">📎 Lampiran Dokumen PO (Invoice)</h3>
            <div style="background: var(--success-light); padding: 1rem; border-radius: 8px; border-left: 4px solid var(--success-color);">
                <div style="display: flex; align-items: center; justify-content: space-between;">
                    <div>
                        <strong style="color: var(--success-color);">File Invoice PO</strong>
                        <p style="margin: 0.25rem 0 0 0; color: var(--text-secondary); font-size: 0.875rem;">
                            <?php echo basename($po['attachment']); ?>
                        </p>
                    </div>
                    <a href="<?php echo getFileUrl($po['attachment']); ?>" target="_blank" class="btn btn-primary">
                        📥 Download Invoice
                    </a>
                </div>
            </div>
        </div>
    <?php endif; ?>
    
    <?php if ($po['rejection_reason']): ?>
        <div class="alert alert-danger" style="margin-bottom: 1.5rem;">
            <strong>Alasan Penolakan:</strong><br>
            <?php echo nl2br(htmlspecialchars($po['rejection_reason'])); ?>
        </div>
    <?php endif; ?>
    
    <?php if ($po['completion_proof']): ?>
        <div style="margin-bottom: 1.5rem;">
            <h3 style="margin-bottom: 0.75rem; color: var(--text-primary);">✅ Bukti Selesai</h3>
            <div style="background: var(--success-light); padding: 1rem; border-radius: 8px; border-left: 4px solid var(--success-color);">
                <div style="display: flex; align-items: center; justify-content: space-between;">
                    <div>
                        <strong style="color: var(--success-color);">File Bukti Selesai</strong>
                        <p style="margin: 0.25rem 0 0 0; color: var(--text-secondary); font-size: 0.875rem;">
                            <?php echo basename($po['completion_proof']); ?>
                        </p>
                    </div>
                    <a href="<?php echo getFileUrl($po['completion_proof']); ?>" target="_blank" class="btn btn-success">
                        📥 Download Bukti
                    </a>
                </div>
            </div>
        </div>
    <?php endif; ?>
    
    <?php if (!empty($revisions)): ?>
        <h3 style="margin-top: 2rem; margin-bottom: 0.75rem; color: var(--text-primary);">Riwayat Revisi</h3>
        <div style="display: flex; flex-direction: column; gap: 1rem;">
            <?php foreach ($revisions as $revision): ?>
                <div style="padding: 1rem; background-color: var(--bg-color); border-radius: 6px; border-left: 4px solid var(--primary-color);">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                        <strong>Revisi #<?php echo $revision['revision_number']; ?></strong>
                        <span style="font-size: 0.875rem; color: var(--text-secondary);">
                            <?php echo htmlspecialchars($revision['full_name']); ?> - <?php echo date('d/m/Y H:i', strtotime($revision['created_at'])); ?>
                        </span>
                    </div>
                    <?php if ($revision['changes_summary']): ?>
                        <p style="color: var(--text-secondary); margin-bottom: 0.5rem;"><?php echo nl2br(htmlspecialchars($revision['changes_summary'])); ?></p>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>


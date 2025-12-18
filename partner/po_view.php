<?php
require_once '../config/config.php';
require_once '../config/file_upload.php';
requirePartner();

$pdo = getDBConnection();
$page_title = 'Detail PO';

$po_id = intval($_GET['id'] ?? 0);
$partner_id = $_SESSION['user_id'];

if (!$po_id) {
    header('Location: po_list.php');
    exit;
}

$stmt = $pdo->prepare("
    SELECT po.*, creator.full_name as creator_name, creator.email as creator_email
    FROM purchase_orders po
    JOIN users creator ON po.created_by = creator.id
    WHERE po.id = ? AND po.partner_id = ?
");
$stmt->execute([$po_id, $partner_id]);
$po = $stmt->fetch();

if (!$po) {
    header('Location: po_list.php');
    exit;
}

$success = isset($_GET['success']) ? 'PO berhasil disetujui!' : '';
$error = isset($_GET['error']) ? $_GET['error'] : '';

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

// Mark notification as read
$stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND po_id = ?");
$stmt->execute([$partner_id, $po_id]);

include '../includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h2 class="card-title">Detail Purchase Order</h2>
        <div class="action-buttons">
            <?php 
            // Tombol approve/reject muncul jika status: pending_review, sent, atau draft (jika sudah dikirim)
            $can_approve = in_array($po['status'], ['pending_review', 'sent', 'draft']) && $po['sent_at'];
            if ($can_approve): 
            ?>
                <div style="display: flex; gap: 1rem; align-items: center;">
                    <a href="po_accept.php?id=<?php echo $po_id; ?>" class="btn btn-success" onclick="return confirm('Setujui PO ini? Menekan tombol ini berarti Anda setuju tanpa perlu tanda tangan.');">
                        ✅ Setujui PO
                    </a>
                    <a href="po_reject.php?id=<?php echo $po_id; ?>" class="btn btn-danger">
                        ❌ Tolak PO
                    </a>
                </div>
            <?php elseif ($po['status'] === 'draft' && !$po['sent_at']): ?>
                <div style="background: var(--info-light); padding: 1rem; border-radius: 8px; border-left: 4px solid var(--info-color); margin-bottom: 1rem;">
                    <p style="margin: 0; color: var(--text-primary);">
                        ⏳ PO masih dalam status DRAFT. Admin akan mengirimkan PO ini kepada Anda untuk review.
                    </p>
                </div>
            <?php elseif ($po['status'] === 'approved'): ?>
                <div style="display: flex; gap: 1rem; align-items: center;">
                    <a href="po_start_progress.php?id=<?php echo $po_id; ?>" class="btn btn-info" onclick="return confirm('Mulai proses pengiriman barang/jasa?');">
                        🚚 Mulai Proses Pengiriman
                    </a>
                </div>
            <?php elseif ($po['status'] === 'in_progress'): ?>
                <!-- No action buttons for in_progress status -->
            <?php elseif ($po['status'] === 'completed'): ?>
                <div style="background: var(--success-light); padding: 1rem; border-radius: 8px; border-left: 4px solid var(--success-color); margin-bottom: 1rem;">
                    <p style="margin: 0; color: var(--success-color); font-weight: 600;">
                        ✅ PO telah selesai diproses. Menunggu admin untuk menutup PO.
                    </p>
                </div>
            <?php elseif ($po['status'] === 'rejected'): ?>
                <div style="background: var(--danger-light); padding: 1rem; border-radius: 8px; border-left: 4px solid var(--danger-color); margin-bottom: 1rem;">
                    <p style="margin: 0; color: var(--danger-color); font-weight: 600;">
                        ❌ PO telah ditolak. Admin akan melakukan revisi dan mengirim ulang.
                    </p>
                </div>
            <?php endif; ?>
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
                        // Custom label for partner view
                        if ($po['status'] === 'sent') {
                            $label = '📥 Dikirim ke Saya';
                        }
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
                        <td style="padding: 0.5rem 0; color: var(--text-secondary);">Disetujui pada:</td>
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
                <?php if ($po['company_name_partner']): ?>
                    <tr>
                        <td style="padding: 0.5rem 0; color: var(--text-secondary); width: 40%;">Perusahaan Partner:</td>
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
                    <td style="padding: 0.5rem 0; color: var(--text-secondary);">Kontak Admin:</td>
                    <td style="padding: 0.5rem 0;"><?php echo htmlspecialchars($po['creator_name']); ?></td>
                </tr>
                <tr>
                    <td style="padding: 0.5rem 0; color: var(--text-secondary);">Email Admin:</td>
                    <td style="padding: 0.5rem 0;"><?php echo htmlspecialchars($po['creator_email']); ?></td>
                </tr>
            </table>
        </div>
    </div>
    
    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    
    <?php if ($po['status'] === 'in_progress'): ?>
        <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 1.5rem; border-radius: 12px; margin-bottom: 1.5rem; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);">
            <div style="display: flex; align-items: center; gap: 1rem; color: white;">
                <div style="font-size: 2.5rem; line-height: 1;">⚙️</div>
                <div style="flex: 1;">
                    <h3 style="margin: 0 0 0.5rem 0; color: white; font-size: 1.125rem; font-weight: 600;">
                        PO Sedang Dalam Proses Pengiriman
                    </h3>
                    <p style="margin: 0; color: rgba(255, 255, 255, 0.95); font-size: 0.9375rem; line-height: 1.5;">
                        Pengiriman barang/jasa sedang berlangsung. Admin akan melakukan verifikasi dan menandai PO sebagai selesai setelah proses pengiriman selesai.
                    </p>
                    <?php if ($po['last_updated_at']): ?>
                        <p style="margin: 0.75rem 0 0 0; color: rgba(255, 255, 255, 0.85); font-size: 0.875rem;">
                            <strong>Dimulai pada:</strong> <?php echo date('d/m/Y H:i', strtotime($po['last_updated_at'])); ?>
                        </p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
    
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
            <div style="background: var(--info-light); padding: 1.5rem; border-radius: 8px; border-left: 4px solid var(--info-color);">
                <p style="margin: 0 0 1rem 0; color: var(--text-primary); font-weight: 600;">
                    ⚠️ Silakan periksa dokumen invoice PO di bawah ini sebelum mengambil keputusan:
                </p>
                <div style="display: flex; align-items: center; justify-content: space-between; background: white; padding: 1rem; border-radius: 6px;">
                    <div>
                        <strong style="color: var(--info-color);">File Invoice PO</strong>
                        <p style="margin: 0.25rem 0 0 0; color: var(--text-secondary); font-size: 0.875rem;">
                            <?php echo basename($po['attachment']); ?>
                        </p>
                    </div>
                    <a href="<?php echo getFileUrl($po['attachment']); ?>" target="_blank" class="btn btn-primary">
                        📥 Download & Review Invoice
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


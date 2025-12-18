<?php
require_once '../config/config.php';
require_once '../config/file_upload.php';
requireAdmin();

$pdo = getDBConnection();
$page_title = 'Tandai PO Selesai';

$po_id = intval($_GET['id'] ?? 0);

if (!$po_id) {
    header('Location: po_list.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM purchase_orders WHERE id = ?");
$stmt->execute([$po_id]);
$po = $stmt->fetch();

if (!$po || $po['status'] !== 'in_progress') {
    header('Location: po_list.php?error=' . urlencode('PO hanya bisa ditandai selesai jika status in_progress'));
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $completion_notes = sanitizeInput($_POST['completion_notes'] ?? '');
    
    try {
        $pdo->beginTransaction();
        
        $completion_proof = $po['completion_proof']; // Keep existing if no new upload
        
        // Handle file upload - bukti selesai (foto/dokumen)
        if (isset($_FILES['completion_proof']) && $_FILES['completion_proof']['error'] === UPLOAD_ERR_OK) {
            try {
                $completion_proof = uploadFile($_FILES['completion_proof'], 'completion', ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png']);
            } catch (Exception $upload_error) {
                // Try to fix permission and retry
                $base_path = dirname(dirname(__FILE__));
                $completion_dir = $base_path . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'completion' . DIRECTORY_SEPARATOR;
                if (is_dir($completion_dir)) {
                    @chmod($completion_dir, 0777);
                }
                // Retry upload
                $completion_proof = uploadFile($_FILES['completion_proof'], 'completion', ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png']);
            }
        }
        
        // Update PO status to completed
        $stmt = $pdo->prepare("UPDATE purchase_orders SET status = 'completed', completion_proof = ?, last_updated_by = ?, last_updated_at = NOW() WHERE id = ?");
        $stmt->execute([$completion_proof, $_SESSION['user_id'], $po_id]);
        
        // Insert to po_completion table
        $stmt = $pdo->prepare("INSERT INTO po_completion (po_id, completed_by, completion_date, proof_file, notes) VALUES (?, ?, NOW(), ?, ?)");
        $stmt->execute([$po_id, $_SESSION['user_id'], $completion_proof, $completion_notes]);
        
        // Create notification for partner
        createNotification($pdo, $po['partner_id'], $po_id, 'po_completed', 'PO Selesai', "PO {$po['po_number']} telah selesai diproses.");
        
        // Log activity
        logActivity($pdo, $_SESSION['user_id'], $po_id, 'complete_po', "PO {$po['po_number']} ditandai selesai");
        
        $pdo->commit();
        
        header('Location: po_view.php?id=' . $po_id . '&success=1');
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = 'Error: ' . $e->getMessage();
    }
}

include '../includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h2 class="card-title">Tandai PO Selesai</h2>
        <a href="po_view.php?id=<?php echo $po_id; ?>" class="btn btn-outline">Kembali</a>
    </div>
    
    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    
    <div style="background: var(--info-light); padding: 1rem; border-radius: 8px; border-left: 4px solid var(--info-color); margin-bottom: 1.5rem;">
        <p style="margin: 0; color: var(--text-primary); font-weight: 600;">
            ℹ️ PO: <strong><?php echo htmlspecialchars($po['po_number']); ?></strong> - <?php echo htmlspecialchars($po['title']); ?>
        </p>
    </div>
    
    <form method="POST" action="" enctype="multipart/form-data">
        <div class="form-group">
            <label class="form-label" for="completion_proof">
                📎 Bukti Selesai (Foto/Dokumen) *
            </label>
            <?php if ($po['completion_proof']): ?>
                <div style="background: var(--bg-color); padding: 0.75rem; border-radius: 6px; margin-bottom: 0.5rem;">
                    <strong>Bukti Saat Ini:</strong> 
                    <a href="<?php echo getFileUrl($po['completion_proof']); ?>" target="_blank"><?php echo basename($po['completion_proof']); ?></a>
                </div>
            <?php endif; ?>
            <input type="file" class="form-control" id="completion_proof" name="completion_proof" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" required>
            <small style="color: var(--text-secondary); font-size: 0.8125rem;">
                Format: PDF, DOC, DOCX, JPG, PNG (Max 10MB). Upload bukti bahwa pengiriman barang/jasa sudah selesai.
            </small>
        </div>
        
        <div class="form-group">
            <label class="form-label" for="completion_notes">Catatan Penyelesaian (Opsional)</label>
            <textarea class="form-control" id="completion_notes" name="completion_notes" rows="3" placeholder="Tambahkan catatan tentang penyelesaian PO ini..."></textarea>
        </div>
        
        <div style="margin-top: 2rem; padding-top: 1.5rem; border-top: 2px solid var(--border-color);">
            <div style="display: flex; gap: 1rem;">
                <button type="submit" class="btn btn-primary">✅ Tandai PO Selesai</button>
                <a href="po_view.php?id=<?php echo $po_id; ?>" class="btn btn-outline">❌ Batal</a>
            </div>
        </div>
    </form>
</div>

<script>
// File upload preview
document.getElementById('completion_proof').addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (file) {
        const fileSize = (file.size / 1024 / 1024).toFixed(2);
        const fileName = file.name;
        const fileExt = fileName.split('.').pop().toLowerCase();
        
        const allowedTypes = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'];
        if (!allowedTypes.includes(fileExt)) {
            alert('Format file tidak valid! Hanya PDF, DOC, DOCX, JPG, PNG yang diizinkan.');
            this.value = '';
            return;
        }
        
        if (file.size > 10000000) {
            alert('Ukuran file terlalu besar! Maksimal 10MB.');
            this.value = '';
            return;
        }
        
        // Show file info
        const fileInfo = document.createElement('div');
        fileInfo.style.cssText = 'margin-top: 0.5rem; padding: 0.75rem; background: var(--success-light); border-radius: 6px; color: var(--success-color);';
        fileInfo.innerHTML = `✓ File: ${fileName} (${fileSize} MB)`;
        
        // Remove previous info
        const prevInfo = this.parentElement.querySelector('.file-info');
        if (prevInfo) prevInfo.remove();
        
        fileInfo.className = 'file-info';
        this.parentElement.appendChild(fileInfo);
    }
});
</script>

<?php include '../includes/footer.php'; ?>


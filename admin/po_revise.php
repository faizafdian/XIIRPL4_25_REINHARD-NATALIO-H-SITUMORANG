<?php
require_once '../config/config.php';
require_once '../config/file_upload.php';
requireAdmin();

$pdo = getDBConnection();
$page_title = 'Revisi PO';

$po_id = intval($_GET['id'] ?? 0);

if (!$po_id) {
    header('Location: po_list.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM purchase_orders WHERE id = ?");
$stmt->execute([$po_id]);
$po = $stmt->fetch();

if (!$po || $po['status'] !== 'rejected') {
    header('Location: po_list.php?error=' . urlencode('PO hanya bisa direvisi jika status rejected'));
    exit;
}

// Get partners
$stmt = $pdo->query("SELECT id, full_name, company_name FROM users WHERE role = 'partner' AND status = 'active' ORDER BY full_name");
$partners = $stmt->fetchAll();

// Get revision count
$stmt = $pdo->prepare("SELECT COALESCE(MAX(revision_number), 0) as max_rev FROM po_revisions WHERE po_id = ?");
$stmt->execute([$po_id]);
$next_revision = $stmt->fetch()['max_rev'] + 1;

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = sanitizeInput($_POST['title'] ?? '');
    $description = sanitizeInput($_POST['description'] ?? '');
    $project_name = sanitizeInput($_POST['project_name'] ?? '');
    $company_name_partner = sanitizeInput($_POST['company_name_partner'] ?? '');
    $supplier_name = sanitizeInput($_POST['supplier_name'] ?? '');
    $total_amount = floatval($_POST['total_amount'] ?? 0);
    $delivery_date = $_POST['delivery_date'] ?? null;
    $changes_summary = sanitizeInput($_POST['changes_summary'] ?? '');
    
    // Validate required fields
    if (empty($title)) {
        $error = 'Judul PO harus diisi!';
    } elseif (empty($supplier_name)) {
        $error = 'Nama Supplier harus diisi!';
    } elseif (empty($total_amount) || $total_amount <= 0) {
        $error = 'Nilai Total PO harus diisi dan lebih dari 0!';
    } else {
        try {
            $pdo->beginTransaction();
            
            // Save old data for revision tracking
            $old_data = json_encode([
                'title' => $po['title'],
                'description' => $po['description'],
                'project_name' => $po['project_name'],
                'company_name_partner' => $po['company_name_partner'],
                'supplier_name' => $po['supplier_name'],
                'total_amount' => $po['total_amount'],
                'delivery_date' => $po['delivery_date'],
                'attachment' => $po['attachment']
            ]);
            
            // Handle file upload - dokumen baru dengan kode revisi
            $new_attachment = $po['attachment']; // Keep old attachment by default
            
            if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
                // Backup old file dengan rename
                if ($po['attachment']) {
                    $base_path = dirname(dirname(__FILE__));
                    $old_file_path = $base_path . DIRECTORY_SEPARATOR . $po['attachment'];
                    if (file_exists($old_file_path)) {
                        $old_file_info = pathinfo($old_file_path);
                        $old_file_dir = dirname($old_file_path);
                        $backup_filename = $po['po_number'] . '_ORIGINAL.' . $old_file_info['extension'];
                        $backup_path = $old_file_dir . DIRECTORY_SEPARATOR . $backup_filename;
                        
                        // Copy old file to backup (keep original)
                        if (!copy($old_file_path, $backup_path)) {
                            throw new Exception('Gagal menyimpan backup dokumen lama');
                        }
                        chmod($backup_path, 0644);
                    }
                }
                
                // Upload new file dengan format: PO-202511-492B8E-REV1.pdf
                $file_ext = strtolower(pathinfo($_FILES['attachment']['name'], PATHINFO_EXTENSION));
                $revision_filename = $po['po_number'] . '-REV' . $next_revision . '.' . $file_ext;
                
                // Create custom upload
                $base_path = dirname(dirname(__FILE__));
                $upload_dir = $base_path . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'invoices' . DIRECTORY_SEPARATOR;
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                $new_file_path = $upload_dir . $revision_filename;
                
                if (!move_uploaded_file($_FILES['attachment']['tmp_name'], $new_file_path)) {
                    throw new Exception('Gagal upload dokumen revisi');
                }
                
                chmod($new_file_path, 0644);
                $new_attachment = 'uploads/invoices/' . $revision_filename;
            }
            
            // Update PO - nomor PO TIDAK BERUBAH
            $stmt = $pdo->prepare("UPDATE purchase_orders SET 
                title = ?, 
                description = ?, 
                project_name = ?, 
                company_name_partner = ?, 
                supplier_name = ?, 
                total_amount = ?, 
                delivery_date = ?, 
                attachment = ?,
                status = 'draft',
                rejection_reason = NULL,
                rejected_at = NULL,
                last_updated_by = ?,
                last_updated_at = NOW()
                WHERE id = ?");
            $stmt->execute([
                $title,
                $description,
                $project_name,
                $company_name_partner,
                $supplier_name,
                $total_amount,
                $delivery_date ?: null,
                $new_attachment,
                $_SESSION['user_id'],
                $po_id
            ]);
            
            // Save new data
            $new_data = json_encode([
                'title' => $title,
                'description' => $description,
                'project_name' => $project_name,
                'company_name_partner' => $company_name_partner,
                'supplier_name' => $supplier_name,
                'total_amount' => $total_amount,
                'delivery_date' => $delivery_date,
                'attachment' => $new_attachment
            ]);
            
            // Create revision record
            $stmt = $pdo->prepare("INSERT INTO po_revisions (po_id, revision_number, revised_by, changes_summary, old_data, new_data) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $po_id,
                $next_revision,
                $_SESSION['user_id'],
                $changes_summary ?: "PO direvisi setelah ditolak partner",
                $old_data,
                $new_data
            ]);
            
            // Create notification for partner
            createNotification($pdo, $po['partner_id'], $po_id, 'po_revised', 'PO Direvisi', "PO {$po['po_number']} telah direvisi oleh admin. Silakan review kembali.");
            
            // Log activity
            logActivity($pdo, $_SESSION['user_id'], $po_id, 'revise_po', "PO {$po['po_number']} direvisi (REV{$next_revision})");
            
            $pdo->commit();
            
            header('Location: po_view.php?id=' . $po_id . '&success=1');
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Error: ' . $e->getMessage();
        }
    }
}

include '../includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h2 class="card-title">Revisi Purchase Order</h2>
        <a href="po_view.php?id=<?php echo $po_id; ?>" class="btn btn-outline">Kembali</a>
    </div>
    
    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    
    <?php if ($po['rejection_reason']): ?>
        <div class="alert alert-danger" style="margin-bottom: 1.5rem;">
            <strong>Alasan Penolakan Partner:</strong><br>
            <?php echo nl2br(htmlspecialchars($po['rejection_reason'])); ?>
        </div>
    <?php endif; ?>
    
    <div style="background: var(--info-light); padding: 1rem; border-radius: 8px; border-left: 4px solid var(--info-color); margin-bottom: 1.5rem;">
        <p style="margin: 0; color: var(--text-primary); font-weight: 600;">
            ℹ️ <strong>Catatan Revisi:</strong>
        </p>
        <ul style="margin: 0.5rem 0 0 1.5rem; color: var(--text-primary);">
            <li>Nomor PO <strong>tidak akan berubah</strong>: <?php echo htmlspecialchars($po['po_number']); ?></li>
            <li>Dokumen lama akan disimpan dengan format: <code><?php echo htmlspecialchars($po['po_number']); ?>_ORIGINAL.pdf</code></li>
            <li>Dokumen baru akan disimpan dengan format: <code><?php echo htmlspecialchars($po['po_number']); ?>-REV<?php echo $next_revision; ?>.pdf</code></li>
        </ul>
    </div>
    
    <form method="POST" action="" enctype="multipart/form-data">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem;">
            <div class="form-group">
                <label class="form-label" for="title">Judul PO *</label>
                <input type="text" class="form-control" id="title" name="title" value="<?php echo htmlspecialchars($po['title']); ?>" required>
            </div>
            
            <div class="form-group">
                <label class="form-label" for="supplier_name">Nama Supplier *</label>
                <input type="text" class="form-control" id="supplier_name" name="supplier_name" value="<?php echo htmlspecialchars($po['supplier_name'] ?? ''); ?>" required>
            </div>
        </div>
        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem;">
            <div class="form-group">
                <label class="form-label" for="company_name_partner">Nama Perusahaan Partner *</label>
                <input type="text" class="form-control" id="company_name_partner" name="company_name_partner" value="<?php echo htmlspecialchars($po['company_name_partner'] ?? ''); ?>" required>
            </div>
            
            <div class="form-group">
                <label class="form-label" for="project_name">Nama Proyek / Departemen</label>
                <input type="text" class="form-control" id="project_name" name="project_name" value="<?php echo htmlspecialchars($po['project_name'] ?? ''); ?>">
            </div>
        </div>
        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem;">
            <div class="form-group">
                <label class="form-label" for="total_amount">Nilai Total PO (Proyeksi) *</label>
                <input type="number" class="form-control" id="total_amount" name="total_amount" step="0.01" min="0.01" value="<?php echo $po['total_amount']; ?>" required>
            </div>
            
            <div class="form-group">
                <label class="form-label" for="delivery_date">Tanggal Pengiriman / Target Selesai</label>
                <input type="date" class="form-control" id="delivery_date" name="delivery_date" value="<?php echo $po['delivery_date'] ? date('Y-m-d', strtotime($po['delivery_date'])) : ''; ?>">
            </div>
        </div>
        
        <div class="form-group">
            <label class="form-label" for="description">Deskripsi Barang/Jasa (Ringkasan) *</label>
            <textarea class="form-control" id="description" name="description" rows="3" required><?php echo htmlspecialchars($po['description'] ?? ''); ?></textarea>
        </div>
        
        <div class="form-group">
            <label class="form-label" for="attachment">
                📎 Lampiran Dokumen PO (Invoice) - Revisi
                <span style="color: var(--text-secondary); font-weight: normal; font-size: 0.875rem;">(Opsional - jika tidak diubah, dokumen lama tetap digunakan)</span>
            </label>
            <?php if ($po['attachment']): ?>
                <div style="background: var(--bg-color); padding: 0.75rem; border-radius: 6px; margin-bottom: 0.5rem;">
                    <strong>Dokumen Saat Ini:</strong> 
                    <a href="<?php echo getFileUrl($po['attachment']); ?>" target="_blank"><?php echo basename($po['attachment']); ?></a>
                    <br>
                    <small style="color: var(--text-secondary);">Dokumen ini akan disimpan sebagai backup dengan nama: <code><?php echo htmlspecialchars($po['po_number']); ?>_ORIGINAL.pdf</code></small>
                </div>
            <?php endif; ?>
            <input type="file" class="form-control" id="attachment" name="attachment" accept=".pdf,.doc,.docx">
            <small style="color: var(--text-secondary); font-size: 0.8125rem;">
                Jika diupload, dokumen baru akan disimpan dengan format: <code><?php echo htmlspecialchars($po['po_number']); ?>-REV<?php echo $next_revision; ?>.pdf</code>
            </small>
        </div>
        
        <div class="form-group">
            <label class="form-label" for="changes_summary">Ringkasan Perubahan (Opsional)</label>
            <textarea class="form-control" id="changes_summary" name="changes_summary" rows="3" placeholder="Jelaskan perubahan yang dilakukan pada PO ini..."><?php echo htmlspecialchars($po['rejection_reason'] ?? ''); ?></textarea>
        </div>
        
        <div style="margin-top: 2rem; padding-top: 1.5rem; border-top: 2px solid var(--border-color);">
            <div style="display: flex; gap: 1rem;">
                <button type="submit" class="btn btn-primary">💾 Simpan Revisi PO</button>
                <a href="po_view.php?id=<?php echo $po_id; ?>" class="btn btn-outline">❌ Batal</a>
            </div>
        </div>
    </form>
</div>

<script>
// Format currency input
document.getElementById('total_amount').addEventListener('input', function(e) {
    this.value = this.value.replace(/[^0-9.]/g, '');
});

// File upload preview
document.getElementById('attachment').addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (file) {
        const fileSize = (file.size / 1024 / 1024).toFixed(2);
        const fileName = file.name;
        const fileExt = fileName.split('.').pop().toLowerCase();
        
        if (!['pdf', 'doc', 'docx'].includes(fileExt)) {
            alert('Format file tidak valid! Hanya PDF, DOC, atau DOCX yang diizinkan.');
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
        fileInfo.innerHTML = `✓ File baru: ${fileName} (${fileSize} MB) - Akan disimpan sebagai REV<?php echo $next_revision; ?>`;
        
        // Remove previous info
        const prevInfo = this.parentElement.querySelector('.file-info');
        if (prevInfo) prevInfo.remove();
        
        fileInfo.className = 'file-info';
        this.parentElement.appendChild(fileInfo);
    }
});
</script>

<?php include '../includes/footer.php'; ?>


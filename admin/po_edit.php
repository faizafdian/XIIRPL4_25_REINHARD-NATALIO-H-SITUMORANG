<?php
require_once '../config/config.php';
require_once '../config/file_upload.php';
requireAdmin();

$pdo = getDBConnection();
$page_title = 'Edit PO';

$po_id = intval($_GET['id'] ?? 0);

if (!$po_id) {
    header('Location: po_list.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM purchase_orders WHERE id = ?");
$stmt->execute([$po_id]);
$po = $stmt->fetch();

if (!$po || $po['status'] !== 'draft') {
    header('Location: po_list.php');
    exit;
}

// Get partners
$stmt = $pdo->query("SELECT id, full_name, company_name FROM users WHERE role = 'partner' AND status = 'active' ORDER BY full_name");
$partners = $stmt->fetchAll();

// Create partners data for JavaScript
$partners_json = [];
foreach ($partners as $partner) {
    $partners_json[$partner['id']] = [
        'company_name' => $partner['company_name'] ?? '',
        'full_name' => $partner['full_name']
    ];
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $partner_id = intval($_POST['partner_id'] ?? 0);
    $title = sanitizeInput($_POST['title'] ?? '');
    $description = sanitizeInput($_POST['description'] ?? '');
    $project_name = sanitizeInput($_POST['project_name'] ?? '');
    $company_name_partner = sanitizeInput($_POST['company_name_partner'] ?? '');
    $supplier_name = sanitizeInput($_POST['supplier_name'] ?? '');
    $total_amount = floatval($_POST['total_amount'] ?? 0);
    $delivery_date = $_POST['delivery_date'] ?? null;
    
    // Validate required fields
    if (empty($partner_id)) {
        $error = 'Partner harus dipilih!';
    } elseif (empty($title)) {
        $error = 'Judul PO harus diisi!';
    } elseif (empty($supplier_name)) {
        $error = 'Nama Supplier harus diisi!';
    } elseif (empty($total_amount) || $total_amount <= 0) {
        $error = 'Nilai Total PO harus diisi dan lebih dari 0!';
    } else {
        try {
            $pdo->beginTransaction();
            
            // Save old data for revision
            $old_data = json_encode([
                'title' => $po['title'],
                'description' => $po['description'],
                'project_name' => $po['project_name'],
                'company_name_partner' => $po['company_name_partner'],
                'supplier_name' => $po['supplier_name'],
                'total_amount' => $po['total_amount'],
                'delivery_date' => $po['delivery_date']
            ]);
            
            // Handle file upload - jika ada file baru
            $attachment_path = $po['attachment']; // Keep existing attachment by default
            
            if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
                // Delete old file if exists
                if ($po['attachment']) {
                    deleteFile($po['attachment']);
                }
                // Upload new file
                $attachment_path = uploadFile($_FILES['attachment'], 'invoices', ['pdf', 'doc', 'docx']);
            }
            
            // Update PO
            $stmt = $pdo->prepare("UPDATE purchase_orders SET 
                partner_id = ?, 
                title = ?, 
                description = ?, 
                project_name = ?, 
                company_name_partner = ?, 
                supplier_name = ?, 
                total_amount = ?, 
                delivery_date = ?, 
                attachment = ?,
                last_updated_by = ?,
                last_updated_at = NOW()
                WHERE id = ?");
            $stmt->execute([
                $partner_id,
                $title,
                $description,
                $project_name,
                $company_name_partner,
                $supplier_name,
                $total_amount,
                $delivery_date ?: null,
                $attachment_path,
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
                'attachment' => $attachment_path
            ]);
            
            // Create revision record
            $stmt = $pdo->prepare("SELECT COALESCE(MAX(revision_number), 0) + 1 as next_rev FROM po_revisions WHERE po_id = ?");
            $stmt->execute([$po_id]);
            $revision_number = $stmt->fetch()['next_rev'];
            
            $stmt = $pdo->prepare("INSERT INTO po_revisions (po_id, revision_number, revised_by, changes_summary, old_data, new_data) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $po_id,
                $revision_number,
                $_SESSION['user_id'],
                "PO diupdate oleh admin",
                $old_data,
                $new_data
            ]);
            
            // Log activity
            logActivity($pdo, $_SESSION['user_id'], $po_id, 'update_po', "PO {$po['po_number']} diupdate");
            
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
        <h2 class="card-title">Edit Purchase Order</h2>
        <a href="po_view.php?id=<?php echo $po_id; ?>" class="btn btn-outline">Kembali</a>
    </div>
    
    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    
    <form method="POST" action="" enctype="multipart/form-data">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem;">
            <div class="form-group">
                <label class="form-label" for="partner_id">Partner *</label>
                <select class="form-control" id="partner_id" name="partner_id" required>
                    <option value="">Pilih Partner</option>
                    <?php foreach ($partners as $partner): ?>
                        <option value="<?php echo $partner['id']; ?>" <?php echo $po['partner_id'] == $partner['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($partner['full_name']); ?>
                            <?php if ($partner['company_name']): ?>
                                - <?php echo htmlspecialchars($partner['company_name']); ?>
                            <?php endif; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label class="form-label" for="title">Judul PO *</label>
                <input type="text" class="form-control" id="title" name="title" value="<?php echo htmlspecialchars($po['title']); ?>" required>
            </div>
        </div>
        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem;">
            <div class="form-group">
                <label class="form-label" for="company_name_partner">
                    Nama Perusahaan Partner * 
                    <span style="font-size: 0.75rem; color: var(--text-secondary); font-weight: normal;">(Terisi otomatis dari data partner)</span>
                </label>
                <input type="text" class="form-control" id="company_name_partner" name="company_name_partner" value="<?php echo htmlspecialchars($po['company_name_partner'] ?? ''); ?>" required>
                <small style="color: var(--text-secondary); font-size: 0.8125rem;">Form tetap bisa diedit manual jika perlu</small>
            </div>
            
            <div class="form-group">
                <label class="form-label" for="supplier_name">
                    Nama Supplier / Vendor * 
                    <span style="font-size: 0.75rem; color: var(--text-secondary); font-weight: normal;">(Terisi otomatis dari data partner)</span>
                </label>
                <input type="text" class="form-control" id="supplier_name" name="supplier_name" value="<?php echo htmlspecialchars($po['supplier_name'] ?? ''); ?>" required>
                <small style="color: var(--text-secondary); font-size: 0.8125rem;">Form tetap bisa diedit manual jika perlu</small>
            </div>
        </div>
        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem;">
            <div class="form-group">
                <label class="form-label" for="project_name">Nama Proyek / Departemen</label>
                <input type="text" class="form-control" id="project_name" name="project_name" value="<?php echo htmlspecialchars($po['project_name'] ?? ''); ?>">
            </div>
            
            <div class="form-group">
                <label class="form-label" for="delivery_date">Tanggal Pengiriman / Target Selesai</label>
                <input type="date" class="form-control" id="delivery_date" name="delivery_date" value="<?php echo $po['delivery_date'] ? date('Y-m-d', strtotime($po['delivery_date'])) : ''; ?>">
            </div>
        </div>
        
        <div class="form-group">
            <label class="form-label" for="description">Deskripsi Barang/Jasa (Ringkasan) *</label>
            <textarea class="form-control" id="description" name="description" rows="3" required><?php echo htmlspecialchars($po['description'] ?? ''); ?></textarea>
            <small style="color: var(--text-secondary); font-size: 0.8125rem;">Ringkasan dari isi invoice PO</small>
        </div>
        
        <div class="form-group">
            <label class="form-label" for="total_amount">Nilai Total PO (Proyeksi) *</label>
            <input type="number" class="form-control" id="total_amount" name="total_amount" step="0.01" min="0.01" value="<?php echo $po['total_amount']; ?>" required>
            <small style="color: var(--text-secondary); font-size: 0.8125rem;">Nilai total berdasarkan invoice PO</small>
        </div>
        
        <div class="form-group">
            <label class="form-label" for="attachment">
                📎 Lampiran Dokumen PO (Invoice)
                <span style="color: var(--text-secondary); font-weight: normal; font-size: 0.875rem;">(Opsional - jika tidak diubah, dokumen lama tetap digunakan)</span>
            </label>
            <?php if ($po['attachment']): ?>
                <div style="background: var(--bg-color); padding: 0.75rem; border-radius: 6px; margin-bottom: 0.5rem;">
                    <strong>Dokumen Saat Ini:</strong> 
                    <a href="<?php echo getFileUrl($po['attachment']); ?>" target="_blank"><?php echo basename($po['attachment']); ?></a>
                </div>
            <?php endif; ?>
            <input type="file" class="form-control" id="attachment" name="attachment" accept=".pdf,.doc,.docx">
            <small style="color: var(--text-secondary); font-size: 0.8125rem;">
                Format: PDF, DOC, DOCX (Max 10MB). Upload file baru untuk mengganti dokumen lama.
            </small>
        </div>
        
        <div style="margin-top: 2rem; padding-top: 1.5rem; border-top: 2px solid var(--border-color);">
            <div style="display: flex; gap: 1rem;">
                <button type="submit" class="btn btn-primary">💾 Update PO</button>
                <a href="po_view.php?id=<?php echo $po_id; ?>" class="btn btn-outline">❌ Batal</a>
            </div>
        </div>
    </form>
</div>

<script>
// Partners data from PHP
const partnersData = <?php echo json_encode($partners_json); ?>;

// Auto-fill company name and supplier name when partner is selected
document.getElementById('partner_id').addEventListener('change', function() {
    const partnerId = this.value;
    const partnerData = partnersData[partnerId];
    
    if (partnerData) {
        // Auto-fill company name partner
        const companyNameField = document.getElementById('company_name_partner');
        if (partnerData.company_name) {
            companyNameField.value = partnerData.company_name;
            companyNameField.style.background = 'rgba(16, 185, 129, 0.1)';
            setTimeout(() => {
                companyNameField.style.background = '';
            }, 2000);
        }
        
        // Auto-fill supplier name (using full_name as supplier)
        const supplierNameField = document.getElementById('supplier_name');
        if (partnerData.full_name) {
            supplierNameField.value = partnerData.full_name;
            supplierNameField.style.background = 'rgba(16, 185, 129, 0.1)';
            setTimeout(() => {
                supplierNameField.style.background = '';
            }, 2000);
        }
    }
});

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
        fileInfo.innerHTML = `✓ File baru: ${fileName} (${fileSize} MB)`;
        
        // Remove previous info
        const prevInfo = this.parentElement.querySelector('.file-info');
        if (prevInfo) prevInfo.remove();
        
        fileInfo.className = 'file-info';
        this.parentElement.appendChild(fileInfo);
    }
});
</script>

<?php include '../includes/footer.php'; ?>


<?php
require_once '../config/config.php';
require_once '../config/file_upload.php';
requireAdmin();

$pdo = getDBConnection();
$page_title = 'Buat PO Baru';

$error = '';
$success = '';

// Get partners with company_name
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $partner_id = intval($_POST['partner_id'] ?? 0);
    $title = sanitizeInput($_POST['title'] ?? '');  //merupakan fungsi sanitive input
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
    } elseif (!isset($_FILES['attachment']) || $_FILES['attachment']['error'] !== UPLOAD_ERR_OK) {
        $error = 'File Invoice PO wajib diupload!';
    } else {
        try {
            $pdo->beginTransaction();
            
            // Upload invoice file (mandatory)
            $attachment_path = uploadFile($_FILES['attachment'], 'invoices', ['pdf', 'doc', 'docx']);
            
            // Generate PO number
            $po_number = generatePONumber();   //po otomatis generate per po
            
            // Create PO
            $stmt = $pdo->prepare("INSERT INTO purchase_orders (po_number, partner_id, created_by, title, description, project_name, company_name_partner, supplier_name, total_amount, delivery_date, attachment, status, last_updated_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'draft', ?)");
            $stmt->execute([
                $po_number, 
                $partner_id, 
                $_SESSION['user_id'], 
                $title, 
                $description,
                $project_name,
                $company_name_partner,
                $supplier_name,
                $total_amount,
                $delivery_date ?: null,
                $attachment_path,
                $_SESSION['user_id']
            ]);
            $po_id = $pdo->lastInsertId();
            
            // Log activity
            logActivity($pdo, $_SESSION['user_id'], $po_id, 'create_po', "PO $po_number dibuat dengan file invoice");
            
            $pdo->commit();
            
            header('Location: po_view.php?id=' . $po_id . '&success=1');
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            if (isset($attachment_path) && file_exists(BASE_PATH . $attachment_path)) {
                deleteFile($attachment_path);
            }
            $error = 'Error: ' . $e->getMessage();
        }
    }
}

include '../includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h2 class="card-title">Buat Purchase Order Baru</h2>
        <a href="po_list.php" class="btn btn-outline">Kembali</a>
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
                        <option value="<?php echo $partner['id']; ?>">
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
                <input type="text" class="form-control" id="title" name="title" placeholder="Contoh: PO Pembelian Material" required>
            </div>
        </div>
        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem;">
            <div class="form-group">
                <label class="form-label" for="company_name_partner">
                    Nama Perusahaan Partner * 
                    <span style="font-size: 0.75rem; color: var(--text-secondary); font-weight: normal;">(Terisi otomatis dari data partner)</span>
                </label>
                <input type="text" class="form-control" id="company_name_partner" name="company_name_partner" placeholder="Akan terisi otomatis saat memilih partner" required>
                <small style="color: var(--text-secondary); font-size: 0.8125rem;">Form tetap bisa diedit manual jika perlu</small>
            </div>
            
            <div class="form-group">
                <label class="form-label" for="supplier_name">
                    Nama Supplier / Vendor * 
                    <span style="font-size: 0.75rem; color: var(--text-secondary); font-weight: normal;">(Terisi otomatis dari data partner)</span>
                </label>
                <input type="text" class="form-control" id="supplier_name" name="supplier_name" placeholder="Akan terisi otomatis saat memilih partner" required>
                <small style="color: var(--text-secondary); font-size: 0.8125rem;">Form tetap bisa diedit manual jika perlu</small>
            </div>
        </div>
        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem;">
            <div class="form-group">
                <label class="form-label" for="project_name">Nama Proyek / Departemen</label>
                <input type="text" class="form-control" id="project_name" name="project_name" placeholder="Nama proyek atau departemen">
            </div>
            
            <div class="form-group">
                <label class="form-label" for="delivery_date">Tanggal Pengiriman / Target Selesai</label>
                <input type="date" class="form-control" id="delivery_date" name="delivery_date">
            </div>
        </div>
        
        <div class="form-group">
            <label class="form-label" for="description">Deskripsi Barang/Jasa (Ringkasan) *</label>
            <textarea class="form-control" id="description" name="description" rows="3" placeholder="Ringkasan isi PO (contoh: Pembelian material baja WF 300)" required></textarea>
            <small style="color: var(--text-secondary); font-size: 0.8125rem;">Ringkasan dari isi invoice PO</small>
        </div>
        
        <div class="form-group">
            <label class="form-label" for="total_amount">Nilai Total PO (Proyeksi) *</label>
            <input type="number" class="form-control" id="total_amount" name="total_amount" step="0.01" min="0.01" placeholder="0" required>
            <small style="color: var(--text-secondary); font-size: 0.8125rem;">Nilai total berdasarkan invoice PO</small>
        </div>
        
        <div class="form-group">
            <label class="form-label" for="attachment">
                📎 Lampiran Dokumen PO (Invoice) * 
                <span style="color: var(--danger-color); font-weight: 600;">WAJIB</span>
            </label>
            <input type="file" class="form-control" id="attachment" name="attachment" accept=".pdf,.doc,.docx" required>
            <small style="color: var(--text-secondary); font-size: 0.8125rem;">
                Format: PDF, DOC, DOCX (Max 10MB). File invoice PO yang akan dikirim ke partner.
            </small>
        </div>
        
        <div style="margin-top: 2rem; padding-top: 1.5rem; border-top: 2px solid var(--border-color);">
            <div style="background: var(--info-light); padding: 1rem; border-radius: 8px; border-left: 4px solid var(--info-color); margin-bottom: 1.5rem;">
                <p style="margin: 0; color: var(--text-primary); font-weight: 600;">
                    ℹ️ Catatan: File Invoice PO adalah dokumen utama. Data yang diinput di atas adalah proyeksi/ringkasan dari invoice tersebut.
                </p>
            </div>
            
            <div style="display: flex; gap: 1rem;">
                <button type="submit" class="btn btn-primary">💾 Simpan PO</button>
                <a href="po_list.php" class="btn btn-outline">❌ Batal</a>
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
        } else {
            companyNameField.value = '';
        }
        
        // Auto-fill supplier name (using full_name as supplier)
        const supplierNameField = document.getElementById('supplier_name');
        if (partnerData.full_name) {
            supplierNameField.value = partnerData.full_name;
            supplierNameField.style.background = 'rgba(16, 185, 129, 0.1)';
            setTimeout(() => {
                supplierNameField.style.background = '';
            }, 2000);
        } else {
            supplierNameField.value = '';
        }
    } else {
        // Clear fields if no partner selected
        document.getElementById('company_name_partner').value = '';
        document.getElementById('supplier_name').value = '';
    }
});

// Format currency input
document.getElementById('total_amount').addEventListener('input', function(e) {
    // Allow only numbers and decimal
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


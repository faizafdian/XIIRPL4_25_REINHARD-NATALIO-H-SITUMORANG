<?php
require_once '../config/config.php';
requireAdmin();

$pdo = getDBConnection();

$po_id = intval($_GET['id'] ?? 0);

if (!$po_id) {
    die('PO tidak ditemukan');
}

$stmt = $pdo->prepare("
    SELECT po.*, 
           u.full_name as partner_name, u.company_name, u.email as partner_email, u.phone as partner_phone, u.address as partner_address,
           creator.full_name as creator_name
    FROM purchase_orders po
    LEFT JOIN users u ON po.partner_id = u.id
    LEFT JOIN users creator ON po.created_by = creator.id
    WHERE po.id = ?
");
$stmt->execute([$po_id]);
$po = $stmt->fetch();

if (!$po) {
    die('PO tidak ditemukan');
}

// Items tidak diperlukan lagi karena data hanya ringkasan dari invoice

// Generate PDF using simple HTML to PDF (you can use TCPDF or FPDF library)
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan PO - <?php echo htmlspecialchars($po['po_number']); ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; padding: 2rem; color: #1e293b; }
        .header { text-align: center; margin-bottom: 2rem; border-bottom: 2px solid #2563eb; padding-bottom: 1rem; }
        .header h1 { color: #2563eb; margin-bottom: 0.5rem; }
        .info-section { margin-bottom: 2rem; }
        .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-bottom: 2rem; }
        .info-box { background: #f8fafc; padding: 1rem; border-radius: 6px; }
        .info-box h3 { margin-bottom: 0.75rem; color: #2563eb; font-size: 1rem; }
        .info-row { display: flex; justify-content: space-between; padding: 0.25rem 0; border-bottom: 1px solid #e2e8f0; }
        .info-label { font-weight: 600; color: #64748b; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 2rem; }
        th, td { padding: 0.75rem; text-align: left; border: 1px solid #e2e8f0; }
        th { background: #2563eb; color: white; font-weight: 600; }
        .text-right { text-align: right; }
        .total-row { background: #f8fafc; font-weight: 700; font-size: 1.125rem; }
        .status-badge { display: inline-block; padding: 0.25rem 0.75rem; border-radius: 12px; font-size: 0.75rem; font-weight: 600; }
        .status-pending { background: #fef3c7; color: #92400e; }
        .status-approved { background: #d1fae5; color: #065f46; }
        .status-rejected { background: #fee2e2; color: #991b1b; }
        .footer { margin-top: 3rem; text-align: center; color: #64748b; font-size: 0.875rem; border-top: 1px solid #e2e8f0; padding-top: 1rem; }
        @media print {
            body { padding: 1rem; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>PURCHASE ORDER</h1>
        <p style="color: #64748b;">Sistem Manajemen PO</p>
    </div>
    
    <div class="info-section">
        <div class="info-grid">
            <div class="info-box">
                <h3>Informasi PO</h3>
                <div class="info-row">
                    <span class="info-label">No. PO:</span>
                    <span><strong><?php echo htmlspecialchars($po['po_number']); ?></strong></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Judul:</span>
                    <span><?php echo htmlspecialchars($po['title']); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Status:</span>
                    <span class="status-badge status-<?php echo $po['status']; ?>"><?php echo strtoupper($po['status']); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Tanggal:</span>
                    <span><?php echo date('d F Y', strtotime($po['created_at'])); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Dibuat oleh:</span>
                    <span><?php echo htmlspecialchars($po['creator_name']); ?></span>
                </div>
                <?php if ($po['project_name']): ?>
                <div class="info-row">
                    <span class="info-label">Nama Proyek:</span>
                    <span><?php echo htmlspecialchars($po['project_name']); ?></span>
                </div>
                <?php endif; ?>
                <?php if ($po['delivery_date']): ?>
                <div class="info-row">
                    <span class="info-label">Tanggal Pengiriman:</span>
                    <span><?php echo date('d F Y', strtotime($po['delivery_date'])); ?></span>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="info-box">
                <h3>Informasi Partner & Supplier</h3>
                <div class="info-row">
                    <span class="info-label">Nama Partner:</span>
                    <span><?php echo htmlspecialchars($po['partner_name']); ?></span>
                </div>
                <?php if ($po['company_name_partner']): ?>
                <div class="info-row">
                    <span class="info-label">Perusahaan Partner:</span>
                    <span><strong><?php echo htmlspecialchars($po['company_name_partner']); ?></strong></span>
                </div>
                <?php endif; ?>
                <?php if ($po['supplier_name']): ?>
                <div class="info-row">
                    <span class="info-label">Nama Supplier:</span>
                    <span><strong><?php echo htmlspecialchars($po['supplier_name']); ?></strong></span>
                </div>
                <?php endif; ?>
                <div class="info-row">
                    <span class="info-label">Email:</span>
                    <span><?php echo htmlspecialchars($po['partner_email']); ?></span>
                </div>
                <?php if ($po['partner_phone']): ?>
                <div class="info-row">
                    <span class="info-label">Telepon:</span>
                    <span><?php echo htmlspecialchars($po['partner_phone']); ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="info-box" style="background: #eff6ff; border-left: 4px solid #2563eb; margin-bottom: 2rem;">
        <h3 style="color: #2563eb;">Ringkasan PO</h3>
        <div class="info-row">
            <span class="info-label">Deskripsi Barang/Jasa:</span>
            <span><?php echo nl2br(htmlspecialchars($po['description'] ?? 'Tidak ada deskripsi')); ?></span>
        </div>
        <?php if ($po['project_name']): ?>
        <div class="info-row">
            <span class="info-label">Nama Proyek:</span>
            <span><?php echo htmlspecialchars($po['project_name']); ?></span>
        </div>
        <?php endif; ?>
        <?php if ($po['supplier_name']): ?>
        <div class="info-row">
            <span class="info-label">Nama Supplier:</span>
            <span><strong><?php echo htmlspecialchars($po['supplier_name']); ?></strong></span>
        </div>
        <?php endif; ?>
        <?php if ($po['company_name_partner']): ?>
        <div class="info-row">
            <span class="info-label">Perusahaan Partner:</span>
            <span><strong><?php echo htmlspecialchars($po['company_name_partner']); ?></strong></span>
        </div>
        <?php endif; ?>
        <?php if ($po['delivery_date']): ?>
        <div class="info-row">
            <span class="info-label">Tanggal Pengiriman:</span>
            <span><?php echo date('d F Y', strtotime($po['delivery_date'])); ?></span>
        </div>
        <?php endif; ?>
        <div class="info-row" style="border-top: 2px solid #2563eb; margin-top: 0.5rem; padding-top: 0.5rem;">
            <span class="info-label" style="font-size: 1.125rem;"><strong>TOTAL PO:</strong></span>
            <span style="font-size: 1.25rem; color: #2563eb; font-weight: 700;">Rp <?php echo number_format($po['total_amount'], 0, ',', '.'); ?></span>
        </div>
    </div>
    
    <?php if ($po['attachment']): ?>
    <div class="info-box" style="background: #f0fdf4; border-left: 4px solid #10b981;">
        <h3 style="color: #10b981;">📎 Lampiran Dokumen PO (Invoice)</h3>
        <p style="margin: 0.5rem 0; color: #64748b;">
            File invoice PO tersedia di sistem. Detail lengkap dapat dilihat pada file invoice tersebut.
        </p>
        <p style="margin: 0.5rem 0;">
            <strong>File:</strong> <?php echo basename($po['attachment']); ?>
        </p>
    </div>
    <?php endif; ?>
    
    <?php if ($po['rejection_reason']): ?>
    <div class="info-box" style="background: #fee2e2; border-left: 4px solid #ef4444;">
        <h3 style="color: #991b1b;">Alasan Penolakan</h3>
        <p><?php echo nl2br(htmlspecialchars($po['rejection_reason'])); ?></p>
    </div>
    <?php endif; ?>
    
    <div class="footer">
        <p>Dicetak pada: <?php echo date('d F Y H:i:s'); ?></p>
        <p class="no-print">Gunakan Ctrl+P untuk mencetak atau simpan sebagai PDF</p>
    </div>
    
    <script>
        window.onload = function() {
            // Auto print if requested
            if (window.location.search.includes('print=1')) {
                window.print();
            }
        };
    </script>
</body>
</html>


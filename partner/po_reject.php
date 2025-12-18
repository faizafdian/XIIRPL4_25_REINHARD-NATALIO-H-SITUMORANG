<?php
require_once '../config/config.php';
requirePartner();

$pdo = getDBConnection();
$page_title = 'Tolak PO';

$po_id = intval($_GET['id'] ?? 0);
$partner_id = $_SESSION['user_id'];

if (!$po_id) {
    header('Location: po_list.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM purchase_orders WHERE id = ? AND partner_id = ?");
$stmt->execute([$po_id, $partner_id]);
$po = $stmt->fetch();

// Bisa reject jika status pending_review, sent, atau draft yang sudah dikirim (ada sent_at)
if (!$po || (!in_array($po['status'], ['pending_review', 'sent', 'draft']) || !$po['sent_at'])) {
    header('Location: po_list.php?error=' . urlencode('PO tidak dapat ditolak. Status: ' . $po['status']));
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rejection_reason = sanitizeInput($_POST['rejection_reason'] ?? '');
    
    if (empty($rejection_reason)) {
        $error = 'Alasan penolakan harus diisi!';
    } else {
        try {
            $pdo->beginTransaction();
            
            // Update PO status
            $stmt = $pdo->prepare("UPDATE purchase_orders SET status = 'rejected', rejected_at = NOW(), rejection_reason = ? WHERE id = ?");
            $stmt->execute([$rejection_reason, $po_id]);
            
            // Create notification for admin
            $stmt = $pdo->prepare("SELECT created_by FROM purchase_orders WHERE id = ?");
            $stmt->execute([$po_id]);
            $admin_id = $stmt->fetch()['created_by'];
            
            createNotification($pdo, $admin_id, $po_id, 'po_rejected', 'PO Ditolak', "PO {$po['po_number']} telah ditolak oleh partner. Alasan: " . substr($rejection_reason, 0, 100));
            
            // Log activity
            logActivity($pdo, $partner_id, $po_id, 'reject_po', "PO {$po['po_number']} ditolak");
            
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
        <h2 class="card-title">Tolak Purchase Order</h2>
        <a href="po_view.php?id=<?php echo $po_id; ?>" class="btn btn-outline">Kembali</a>
    </div>
    
    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    
    <div style="margin-bottom: 1.5rem;">
        <h3 style="margin-bottom: 0.5rem;">PO: <?php echo htmlspecialchars($po['po_number']); ?></h3>
        <p style="color: var(--text-secondary);"><?php echo htmlspecialchars($po['title']); ?></p>
    </div>
    
    <form method="POST" action="">
        <div class="form-group">
            <label class="form-label" for="rejection_reason">Alasan Penolakan *</label>
            <textarea class="form-control" id="rejection_reason" name="rejection_reason" rows="5" required placeholder="Masukkan alasan mengapa PO ini ditolak..."></textarea>
        </div>
        
        <div style="margin-top: 1.5rem;">
            <button type="submit" class="btn btn-danger">Tolak PO</button>
            <a href="po_view.php?id=<?php echo $po_id; ?>" class="btn btn-outline">Batal</a>
        </div>
    </form>
</div>

<?php include '../includes/footer.php'; ?>


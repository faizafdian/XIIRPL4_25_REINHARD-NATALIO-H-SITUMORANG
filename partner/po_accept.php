<?php
require_once '../config/config.php';
requirePartner();

$pdo = getDBConnection();

$po_id = intval($_GET['id'] ?? 0);
$partner_id = $_SESSION['user_id'];

if (!$po_id) {
    header('Location: po_list.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM purchase_orders WHERE id = ? AND partner_id = ?");
$stmt->execute([$po_id, $partner_id]);
$po = $stmt->fetch();

// Bisa approve jika status pending_review, sent, atau draft yang sudah dikirim (ada sent_at)
if (!$po || (!in_array($po['status'], ['pending_review', 'sent', 'draft']) || !$po['sent_at'])) {
    header('Location: po_list.php?error=' . urlencode('PO tidak dapat disetujui. Status: ' . $po['status']));
    exit;
}

try {
    $pdo->beginTransaction();
    
    // Update PO status - sesuai alur: setelah approve masuk ke tahap processing
    $stmt = $pdo->prepare("UPDATE purchase_orders SET status = 'approved', approved_at = NOW(), approved_by_partner = ?, approved_at_partner = NOW() WHERE id = ?");
    $stmt->execute([$partner_id, $po_id]);
    
    // Create notification for admin
    $stmt = $pdo->prepare("SELECT created_by FROM purchase_orders WHERE id = ?");
    $stmt->execute([$po_id]);
    $admin_id = $stmt->fetch()['created_by'];
    
    createNotification($pdo, $admin_id, $po_id, 'po_approved', 'PO Disetujui Partner', "PO {$po['po_number']} telah disetujui oleh partner. Siap untuk proses pengiriman.");
    
    // Log activity
    logActivity($pdo, $partner_id, $po_id, 'approve_po', "PO {$po['po_number']} disetujui oleh partner (tanpa tanda tangan)");
    
    $pdo->commit();
    
    header('Location: po_view.php?id=' . $po_id . '&success=1');
    exit;
} catch (Exception $e) {
    $pdo->rollBack();
    header('Location: po_view.php?id=' . $po_id . '&error=' . urlencode($e->getMessage()));
    exit;
}
?>


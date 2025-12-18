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

if (!$po || $po['status'] !== 'approved') {
    header('Location: po_list.php?error=' . urlencode('PO hanya bisa mulai proses jika status approved'));
    exit;
}

try {
    $pdo->beginTransaction();
    
    // Update PO status to in_progress
    $stmt = $pdo->prepare("UPDATE purchase_orders SET status = 'in_progress', last_updated_by = ?, last_updated_at = NOW() WHERE id = ?");
    $stmt->execute([$partner_id, $po_id]);
    
    // Create notification for admin
    $stmt = $pdo->prepare("SELECT created_by FROM purchase_orders WHERE id = ?");
    $stmt->execute([$po_id]);
    $admin_id = $stmt->fetch()['created_by'];
    
    createNotification($pdo, $admin_id, $po_id, 'po_in_progress', 'PO Sedang Diproses', "PO {$po['po_number']} sedang dalam proses pengiriman barang/jasa oleh partner.");
    
    // Log activity
    logActivity($pdo, $partner_id, $po_id, 'start_progress', "PO {$po['po_number']} mulai proses pengiriman oleh partner");
    
    $pdo->commit();
    
    header('Location: po_view.php?id=' . $po_id . '&success=1');
    exit;
} catch (Exception $e) {
    $pdo->rollBack();
    header('Location: po_view.php?id=' . $po_id . '&error=' . urlencode($e->getMessage()));
    exit;
}
?>


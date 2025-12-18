<?php
require_once '../config/config.php';
requireAdmin();

$pdo = getDBConnection();

$po_id = intval($_GET['id'] ?? 0);

if (!$po_id) {
    header('Location: po_list.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM purchase_orders WHERE id = ?");
$stmt->execute([$po_id]);
$po = $stmt->fetch();

if (!$po || $po['status'] !== 'completed') {
    header('Location: po_list.php?error=' . urlencode('PO hanya bisa ditutup jika status completed'));
    exit;
}

try {
    $pdo->beginTransaction();
    
    // Update PO status to closed
    $stmt = $pdo->prepare("UPDATE purchase_orders SET status = 'closed', last_updated_by = ?, last_updated_at = NOW() WHERE id = ?");
    $stmt->execute([$_SESSION['user_id'], $po_id]);
    
    // Create notification for partner
    createNotification($pdo, $po['partner_id'], $po_id, 'po_closed', 'PO Ditutup', "PO {$po['po_number']} telah ditutup. Semua proses sudah selesai.");
    
    // Log activity
    logActivity($pdo, $_SESSION['user_id'], $po_id, 'close_po', "PO {$po['po_number']} ditutup");
    
    $pdo->commit();
    
    header('Location: po_view.php?id=' . $po_id . '&success=1');
    exit;
} catch (Exception $e) {
    $pdo->rollBack();
    header('Location: po_view.php?id=' . $po_id . '&error=' . urlencode($e->getMessage()));
    exit;
}
?>


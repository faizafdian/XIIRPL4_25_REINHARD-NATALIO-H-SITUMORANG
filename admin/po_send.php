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

if (!$po || ($po['status'] !== 'draft' && $po['status'] !== 'revised')) {
    header('Location: po_list.php');
    exit;
}

try {
    $pdo->beginTransaction();
    
    // Update PO status to pending_review (sent to partner untuk review)
    $stmt = $pdo->prepare("UPDATE purchase_orders SET status = 'pending_review', sent_at = NOW() WHERE id = ?");
    $stmt->execute([$po_id]);
    
    // Create notification for partner
    createNotification($pdo, $po['partner_id'], $po_id, 'po_sent', 'PO Baru Diterima', "PO {$po['po_number']} telah dikirim kepada Anda. Silakan periksa dokumen invoice dan berikan respons (setujui/tolak).");
    
    // Log activity
    logActivity($pdo, $_SESSION['user_id'], $po_id, 'send_po', "PO {$po['po_number']} dikirim ke partner untuk review");
    
    $pdo->commit();
    
    header('Location: po_view.php?id=' . $po_id . '&success=1');
    exit;
} catch (Exception $e) {
    $pdo->rollBack();
    header('Location: po_view.php?id=' . $po_id . '&error=' . urlencode($e->getMessage()));
    exit;
}
?>


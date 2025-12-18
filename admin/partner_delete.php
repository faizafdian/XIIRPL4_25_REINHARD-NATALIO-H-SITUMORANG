<?php
require_once '../config/config.php';
requireAdmin();

$pdo = getDBConnection();

$partner_id = intval($_GET['id'] ?? 0);

if (!$partner_id) {
    header('Location: partners.php');
    exit;
}

// Check if partner has PO
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM purchase_orders WHERE partner_id = ?");
$stmt->execute([$partner_id]);
$po_count = $stmt->fetch()['count'];

if ($po_count > 0) {
    header('Location: partners.php?error=' . urlencode('Tidak dapat menghapus partner yang memiliki riwayat PO!'));
    exit;
}

$stmt = $pdo->prepare("SELECT username FROM users WHERE id = ? AND role = 'partner'");
$stmt->execute([$partner_id]);
$partner = $stmt->fetch();

if ($partner) {
    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND role = 'partner'");
    $stmt->execute([$partner_id]);
    
    logActivity($pdo, $_SESSION['user_id'], null, 'delete_partner', "Partner dihapus: {$partner['username']}");
}

header('Location: partners.php?success=1');
exit;
?>


<?php
require_once '../config/config.php';
requireAdmin();

$pdo = getDBConnection();
$page_title = 'Edit Partner';

$partner_id = intval($_GET['id'] ?? 0);

if (!$partner_id) {
    header('Location: partners.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role = 'partner'");
$stmt->execute([$partner_id]);
$partner = $stmt->fetch();

if (!$partner) {
    header('Location: partners.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitizeInput($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $full_name = sanitizeInput($_POST['full_name'] ?? '');
    $email = sanitizeInput($_POST['email'] ?? '');
    $company_name = sanitizeInput($_POST['company_name'] ?? '');
    $phone = sanitizeInput($_POST['phone'] ?? '');
    $address = sanitizeInput($_POST['address'] ?? '');
    $status = sanitizeInput($_POST['status'] ?? 'active');
    
    if (empty($username) || empty($full_name) || empty($email)) {
        $error = 'Username, nama lengkap, dan email harus diisi!';
    } else {
        // Check if username exists (except current)
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
        $stmt->execute([$username, $partner_id]);
        if ($stmt->fetch()) {
            $error = 'Username sudah digunakan!';
        } else {
            // Check if email exists (except current)
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $stmt->execute([$email, $partner_id]);
            if ($stmt->fetch()) {
                $error = 'Email sudah digunakan!';
            } else {
                if (!empty($password)) {
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE users SET username = ?, password = ?, full_name = ?, email = ?, company_name = ?, phone = ?, address = ?, status = ? WHERE id = ?");
                    $stmt->execute([$username, $hashed_password, $full_name, $email, $company_name, $phone, $address, $status, $partner_id]);
                } else {
                    $stmt = $pdo->prepare("UPDATE users SET username = ?, full_name = ?, email = ?, company_name = ?, phone = ?, address = ?, status = ? WHERE id = ?");
                    $stmt->execute([$username, $full_name, $email, $company_name, $phone, $address, $status, $partner_id]);
                }
                
                logActivity($pdo, $_SESSION['user_id'], null, 'update_partner', "Partner diupdate: $username");
                
                header('Location: partner_view.php?id=' . $partner_id . '&success=1');
                exit;
            }
        }
    }
}

include '../includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h2 class="card-title">Edit Partner</h2>
        <a href="partner_view.php?id=<?php echo $partner_id; ?>" class="btn btn-outline">Kembali</a>
    </div>
    
    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    
    <form method="POST" action="">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1rem;">
            <div class="form-group">
                <label class="form-label" for="username">Username *</label>
                <input type="text" class="form-control" id="username" name="username" value="<?php echo htmlspecialchars($partner['username']); ?>" required>
            </div>
            
            <div class="form-group">
                <label class="form-label" for="password">Password (kosongkan jika tidak diubah)</label>
                <input type="password" class="form-control" id="password" name="password">
            </div>
            
            <div class="form-group">
                <label class="form-label" for="full_name">Nama Lengkap *</label>
                <input type="text" class="form-control" id="full_name" name="full_name" value="<?php echo htmlspecialchars($partner['full_name']); ?>" required>
            </div>
            
            <div class="form-group">
                <label class="form-label" for="email">Email *</label>
                <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($partner['email']); ?>" required>
            </div>
            
            <div class="form-group">
                <label class="form-label" for="company_name">Nama Perusahaan</label>
                <input type="text" class="form-control" id="company_name" name="company_name" value="<?php echo htmlspecialchars($partner['company_name'] ?? ''); ?>">
            </div>
            
            <div class="form-group">
                <label class="form-label" for="phone">Telepon</label>
                <input type="text" class="form-control" id="phone" name="phone" value="<?php echo htmlspecialchars($partner['phone'] ?? ''); ?>">
            </div>
            
            <div class="form-group" style="grid-column: 1 / -1;">
                <label class="form-label" for="address">Alamat</label>
                <textarea class="form-control" id="address" name="address" rows="3"><?php echo htmlspecialchars($partner['address'] ?? ''); ?></textarea>
            </div>
            
            <div class="form-group">
                <label class="form-label" for="status">Status</label>
                <select class="form-control" id="status" name="status">
                    <option value="active" <?php echo $partner['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                    <option value="inactive" <?php echo $partner['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                </select>
            </div>
        </div>
        
        <div style="margin-top: 1.5rem;">
            <button type="submit" class="btn btn-primary">Update Partner</button>
            <a href="partner_view.php?id=<?php echo $partner_id; ?>" class="btn btn-outline">Batal</a>
        </div>
    </form>
</div>

<?php include '../includes/footer.php'; ?>


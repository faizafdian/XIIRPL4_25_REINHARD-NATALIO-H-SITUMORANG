<?php
require_once '../config/config.php';
requireAdmin();

$pdo = getDBConnection();
$page_title = 'Tambah Partner';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitizeInput($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $full_name = sanitizeInput($_POST['full_name'] ?? '');
    $email = sanitizeInput($_POST['email'] ?? '');
    $company_name = sanitizeInput($_POST['company_name'] ?? '');
    $phone = sanitizeInput($_POST['phone'] ?? '');
    $address = sanitizeInput($_POST['address'] ?? '');
    $status = sanitizeInput($_POST['status'] ?? 'active');
    
    if (empty($username) || empty($password) || empty($full_name) || empty($email)) {
        $error = 'Username, password, nama lengkap, dan email harus diisi!';
    } else {
        // Check if username exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            $error = 'Username sudah digunakan!';
        } else {
            // Check if email exists
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $error = 'Email sudah digunakan!';
            } else {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                $stmt = $pdo->prepare("INSERT INTO users (username, password, full_name, email, role, company_name, phone, address, status) VALUES (?, ?, ?, ?, 'partner', ?, ?, ?, ?)");
                $stmt->execute([$username, $hashed_password, $full_name, $email, $company_name, $phone, $address, $status]);
                
                logActivity($pdo, $_SESSION['user_id'], null, 'create_partner', "Partner baru dibuat: $username");
                
                header('Location: partners.php?success=1');
                exit;
            }
        }
    }
}

include '../includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h2 class="card-title">Tambah Partner Baru</h2>
        <a href="partners.php" class="btn btn-outline">Kembali</a>
    </div>
    
    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    
    <form method="POST" action="">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1rem;">
            <div class="form-group">
                <label class="form-label" for="username">Username *</label>
                <input type="text" class="form-control" id="username" name="username" required>
            </div>
            
            <div class="form-group">
                <label class="form-label" for="password">Password *</label>
                <input type="password" class="form-control" id="password" name="password" required>
            </div>
            
            <div class="form-group">
                <label class="form-label" for="full_name">Nama Lengkap *</label>
                <input type="text" class="form-control" id="full_name" name="full_name" required>
            </div>
            
            <div class="form-group">
                <label class="form-label" for="email">Email *</label>
                <input type="email" class="form-control" id="email" name="email" required>
            </div>
            
            <div class="form-group">
                <label class="form-label" for="company_name">Nama Perusahaan</label>
                <input type="text" class="form-control" id="company_name" name="company_name">
            </div>
            
            <div class="form-group">
                <label class="form-label" for="phone">Telepon</label>
                <input type="text" class="form-control" id="phone" name="phone">
            </div>
            
            <div class="form-group" style="grid-column: 1 / -1;">
                <label class="form-label" for="address">Alamat</label>
                <textarea class="form-control" id="address" name="address" rows="3"></textarea>
            </div>
            
            <div class="form-group">
                <label class="form-label" for="status">Status</label>
                <select class="form-control" id="status" name="status">
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
            </div>
        </div>
        
        <div style="margin-top: 1.5rem;">
            <button type="submit" class="btn btn-primary">Simpan Partner</button>
            <a href="partners.php" class="btn btn-outline">Batal</a>
        </div>
    </form>
</div>

<?php include '../includes/footer.php'; ?>


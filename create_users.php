<?php
/**
 * Create Default Users Script
 * Script ini akan membuat user admin dan partner dengan password yang mudah
 */

require_once 'config/database.php';

$message = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo = getDBConnection();
        
        // Create/Update Admin
        $admin_username = 'admin';
        $admin_password = password_hash('admin', PASSWORD_DEFAULT); // Password: admin
        $admin_password_hash = '$2y$10$.UfuNQHJsFCpYOegCOgjre/p14Zqc5VdLvNyAag.oCJaBv.Zv7F4y'; // Pre-generated hash
        $admin_name = 'Administrator';
        $admin_email = 'admin@po-system.com';
        
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$admin_username]);
        $admin_exists = $stmt->fetch();
        
        if ($admin_exists) {
            $stmt = $pdo->prepare("UPDATE users SET password = ?, full_name = ?, email = ?, role = 'admin', status = 'active' WHERE username = ?");
            $stmt->execute([$admin_password, $admin_name, $admin_email, $admin_username]);
            $admin_msg = "✓ Admin user diupdate!";
        } else {
            $stmt = $pdo->prepare("INSERT INTO users (username, password, full_name, email, role, status) VALUES (?, ?, ?, ?, 'admin', 'active')");
            $stmt->execute([$admin_username, $admin_password, $admin_name, $admin_email]);
            $admin_msg = "✓ Admin user dibuat!";
        }
        
        // Create/Update Partner
        $partner_username = 'partner';
        $partner_password = password_hash('partner', PASSWORD_DEFAULT); // Password: partner
        $partner_password_hash = '$2y$10$wc0QAoOp2qosOicffMXNae3ERX2Gzbkf84YeVEP4CKvFhkg/CJpQC'; // Pre-generated hash
        $partner_name = 'Partner Supplier';
        $partner_email = 'partner@example.com';
        $partner_company = 'PT Supplier Jaya';
        
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$partner_username]);
        $partner_exists = $stmt->fetch();
        
        if ($partner_exists) {
            $stmt = $pdo->prepare("UPDATE users SET password = ?, full_name = ?, email = ?, company_name = ?, role = 'partner', status = 'active' WHERE username = ?");
            $stmt->execute([$partner_password, $partner_name, $partner_email, $partner_company, $partner_username]);
            $partner_msg = "✓ Partner user diupdate!";
        } else {
            $stmt = $pdo->prepare("INSERT INTO users (username, password, full_name, email, company_name, role, status) VALUES (?, ?, ?, ?, ?, 'partner', 'active')");
            $stmt->execute([$partner_username, $partner_password, $partner_name, $partner_email, $partner_company]);
            $partner_msg = "✓ Partner user dibuat!";
        }
        
        $message = $admin_msg . "<br>" . $partner_msg;
        $success = true;
        
    } catch (Exception $e) {
        $message = "✗ Error: " . $e->getMessage();
    }
}

// Check existing users
$existing_users = [];
try {
    $pdo = getDBConnection();
    $stmt = $pdo->query("SELECT username, full_name, role, status FROM users ORDER BY role, username");
    $existing_users = $stmt->fetchAll();
} catch (Exception $e) {
    // Ignore
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Users - PO Management</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }
        .card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
            padding: 2.5rem;
            max-width: 600px;
            width: 100%;
        }
        h1 { color: #2563eb; margin-bottom: 0.5rem; }
        .subtitle { color: #64748b; margin-bottom: 1.5rem; }
        .alert {
            padding: 1rem;
            border-radius: 6px;
            margin-bottom: 1.5rem;
        }
        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border-left: 4px solid #10b981;
        }
        .alert-danger {
            background: #fee2e2;
            color: #991b1b;
            border-left: 4px solid #ef4444;
        }
        .btn {
            display: inline-block;
            padding: 0.75rem 1.5rem;
            background: #2563eb;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 1rem;
            width: 100%;
            margin-top: 1rem;
        }
        .btn:hover { background: #1e40af; }
        .info-box {
            background: #f8fafc;
            padding: 1.5rem;
            border-radius: 6px;
            margin: 1.5rem 0;
        }
        .info-box h3 {
            margin-bottom: 1rem;
            color: #2563eb;
        }
        .user-list {
            list-style: none;
            padding: 0;
        }
        .user-list li {
            padding: 0.5rem;
            border-bottom: 1px solid #e2e8f0;
        }
        .user-list li:last-child {
            border-bottom: none;
        }
        .credentials {
            background: #dbeafe;
            padding: 1rem;
            border-radius: 6px;
            margin-top: 1rem;
            border-left: 4px solid #2563eb;
        }
        .credentials strong {
            color: #1e40af;
        }
    </style>
</head>
<body>
    <div class="card">
        <h1>Create Default Users</h1>
        <p class="subtitle">Buat atau update user admin dan partner</p>
        
        <?php if ($message): ?>
            <div class="alert <?php echo $success ? 'alert-success' : 'alert-danger'; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="credentials">
                <h3 style="margin-bottom: 0.75rem; color: #1e40af;">Login Credentials:</h3>
                <p><strong>Admin:</strong> username = <code>admin</code> | password = <code>admin</code></p>
                <p><strong>Partner:</strong> username = <code>partner</code> | password = <code>partner</code></p>
                <p style="margin-top: 1rem; font-size: 0.875rem; color: #64748b;">
                    ⚠️ Setelah login, segera ganti password untuk keamanan!
                </p>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($existing_users)): ?>
            <div class="info-box">
                <h3>Users yang Ada di Database:</h3>
                <ul class="user-list">
                    <?php foreach ($existing_users as $user): ?>
                        <li>
                            <strong><?php echo htmlspecialchars($user['username']); ?></strong> 
                            (<?php echo htmlspecialchars($user['full_name']); ?>) 
                            - Role: <?php echo htmlspecialchars($user['role']); ?> 
                            - Status: <span style="color: <?php echo $user['status'] === 'active' ? 'green' : 'red'; ?>;">
                                <?php echo htmlspecialchars($user['status']); ?>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <form method="POST">
            <p style="margin-bottom: 1rem; color: #64748b;">
                Script ini akan membuat atau mengupdate user default dengan password yang lebih sederhana:
            </p>
            <ul style="margin-left: 1.5rem; margin-bottom: 1.5rem; color: #64748b;">
                <li>Admin: username = <strong>admin</strong>, password = <strong>admin</strong></li>
                <li>Partner: username = <strong>partner</strong>, password = <strong>partner</strong></li>
            </ul>
            
            <button type="submit" class="btn">Buat/Update Users</button>
        </form>
        
        <div style="margin-top: 1.5rem; padding-top: 1.5rem; border-top: 1px solid #e2e8f0;">
            <p style="font-size: 0.875rem; color: #64748b;">
                <strong>Catatan:</strong> Setelah user dibuat dan bisa login, hapus file ini untuk keamanan.
            </p>
            <p style="font-size: 0.875rem; color: #64748b; margin-top: 0.5rem;">
                <a href="index.php" style="color: #2563eb;">→ Kembali ke Login</a>
            </p>
        </div>
    </div>
</body>
</html>


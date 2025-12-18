<?php
/**
 * Database Check Script
 * Akses file ini untuk mengecek status database
 */

require_once 'config/database.php';

echo "<h2>Database Check</h2>";

try {
    $pdo = getDBConnection();
    echo "<p style='color: green;'>✓ Koneksi database berhasil!</p>";
    
    // Check if database exists
    $stmt = $pdo->query("SELECT DATABASE()");
    $db_name = $stmt->fetchColumn();
    echo "<p>Database: <strong>$db_name</strong></p>";
    
    // Check if tables exist
    $tables = ['users', 'purchase_orders', 'po_revisions', 'activity_logs', 'notifications', 'po_completion'];
    echo "<h3>Status Tabel:</h3><ul>";
    foreach ($tables as $table) {
        try {
            $stmt = $pdo->query("SELECT COUNT(*) FROM $table");
            $count = $stmt->fetchColumn();
            echo "<li>$table: <strong style='color: green;'>Ada ($count records)</strong></li>";
        } catch (Exception $e) {
            echo "<li>$table: <strong style='color: red;'>TIDAK ADA</strong></li>";
        }
    }
    echo "</ul>";
    
    // Check users
    echo "<h3>Users di Database:</h3>";
    try {
        $stmt = $pdo->query("SELECT id, username, full_name, role, status FROM users");
        $users = $stmt->fetchAll();
        if (empty($users)) {
            echo "<p style='color: red;'>⚠ TIDAK ADA USER! Database perlu diimport.</p>";
        } else {
            echo "<table border='1' cellpadding='10' style='border-collapse: collapse;'>";
            echo "<tr><th>ID</th><th>Username</th><th>Nama</th><th>Role</th><th>Status</th></tr>";
            foreach ($users as $user) {
                echo "<tr>";
                echo "<td>{$user['id']}</td>";
                echo "<td>{$user['username']}</td>";
                echo "<td>{$user['full_name']}</td>";
                echo "<td>{$user['role']}</td>";
                echo "<td>{$user['status']}</td>";
                echo "</tr>";
            }
            echo "</table>";
        }
    } catch (Exception $e) {
        echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
    }
    
    // Test password
    echo "<h3>Test Password:</h3>";
    $test_password = 'admin123';
    $stmt = $pdo->prepare("SELECT username, password FROM users WHERE username = 'admin'");
    $stmt->execute();
    $admin = $stmt->fetch();
    if ($admin) {
        $verify = password_verify($test_password, $admin['password']);
        echo "<p>Password 'admin123' untuk user 'admin': ";
        echo $verify ? "<strong style='color: green;'>✓ BENAR</strong>" : "<strong style='color: red;'>✗ SALAH</strong>";
        echo "</p>";
        echo "<p>Hash di database: " . substr($admin['password'], 0, 30) . "...</p>";
    } else {
        echo "<p style='color: red;'>User 'admin' tidak ditemukan!</p>";
    }
    
    echo "<hr>";
    echo "<h3>Solusi:</h3>";
    echo "<ol>";
    echo "<li>Jika tabel tidak ada, import file <code>database/schema.sql</code> melalui phpMyAdmin</li>";
    echo "<li>Atau akses <a href='install.php'>install.php</a> untuk instalasi otomatis</li>";
    echo "<li>Jika user tidak ada, jalankan ulang import schema.sql</li>";
    echo "</ol>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Error: " . $e->getMessage() . "</p>";
    echo "<p>Pastikan:</p>";
    echo "<ul>";
    echo "<li>MySQL di XAMPP sudah running</li>";
    echo "<li>Database 'PO_DEV' sudah dibuat</li>";
    echo "<li>Konfigurasi di config/database.php sudah benar</li>";
    echo "</ul>";
}
?>


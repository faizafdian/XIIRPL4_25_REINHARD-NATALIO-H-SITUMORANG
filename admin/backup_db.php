<?php
/**
 * Database Backup Script
 * Akses file ini untuk backup database
 */

require_once '../config/config.php';
requireAdmin();

$pdo = getDBConnection();

// Get all tables
$tables = [];
$stmt = $pdo->query("SHOW TABLES");
while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
    $tables[] = $row[0];
}

// Generate SQL backup
$backup = "-- Database Backup\n";
$backup .= "-- Generated: " . date('Y-m-d H:i:s') . "\n\n";
$backup .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

foreach ($tables as $table) {
    // Drop table
    $backup .= "DROP TABLE IF EXISTS `$table`;\n";
    
    // Create table
    $stmt = $pdo->query("SHOW CREATE TABLE `$table`");
    $create = $stmt->fetch(PDO::FETCH_ASSOC);
    $backup .= $create['Create Table'] . ";\n\n";
    
    // Insert data
    $stmt = $pdo->query("SELECT * FROM `$table`");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($rows)) {
        $backup .= "INSERT INTO `$table` VALUES\n";
        $values = [];
        foreach ($rows as $row) {
            $row_values = [];
            foreach ($row as $value) {
                if ($value === null) {
                    $row_values[] = 'NULL';
                } else {
                    $row_values[] = "'" . addslashes($value) . "'";
                }
            }
            $values[] = "(" . implode(", ", $row_values) . ")";
        }
        $backup .= implode(",\n", $values) . ";\n\n";
    }
}

$backup .= "SET FOREIGN_KEY_CHECKS=1;\n";

// Download file
$filename = 'po_management_backup_' . date('Y-m-d_His') . '.sql';
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . strlen($backup));
echo $backup;
exit;
?>


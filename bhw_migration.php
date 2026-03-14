<?php
/**
 * bhw_migration.php
 * Run this once to prepare the database for BHW features.
 */
require_once 'config/database.php';

try {
    // 1. Add 'barangay' column to users (for BHW staff assignment)
    try {
        $pdo->exec("ALTER TABLE users ADD COLUMN barangay VARCHAR(100) DEFAULT NULL AFTER role");
        echo "Added 'barangay' column to users table.\n";
    } catch (PDOException $e) { echo "Users table already has barangay column or error: " . $e->getMessage() . "\n"; }

    // 2. Add 'barangay' and 'is_verified' columns to healers
    try {
        $pdo->exec("ALTER TABLE healers ADD COLUMN barangay VARCHAR(100) DEFAULT NULL");
        $pdo->exec("ALTER TABLE healers ADD COLUMN is_verified BOOLEAN DEFAULT FALSE");
        echo "Added columns to healers table.\n";
    } catch (PDOException $e) { echo "Healers table update error: " . $e->getMessage() . "\n"; }

    // 3. Add 'barangay' to plants (to track where they were reported)
    try {
        $pdo->exec("ALTER TABLE plants ADD COLUMN barangay VARCHAR(100) DEFAULT NULL");
        echo "Added 'barangay' column to plants table.\n";
    } catch (PDOException $e) { echo "Plants table update error: " . $e->getMessage() . "\n"; }

    // 4. Create barangay_alerts table
    $pdo->exec("CREATE TABLE IF NOT EXISTS barangay_alerts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        bhw_id INT NOT NULL,
        barangay VARCHAR(100) NOT NULL,
        title VARCHAR(255) NOT NULL,
        content TEXT NOT NULL,
        alert_type ENUM('info', 'warning', 'event') DEFAULT 'info',
        expires_at DATETIME DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (bhw_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    echo "Created barangay_alerts table.\n";

    // 5. Back-fill existing BHW assignments from username (if format is bhw_brgy_...)
    $stmt = $pdo->query("SELECT id, username FROM users WHERE role = 'bhw' AND barangay IS NULL");
    $bhws = $stmt->fetchAll();
    foreach ($bhws as $bhw) {
        if (preg_match('/^bhw_([^_]+)_/', $bhw['username'], $matches)) {
            $brgy = ucfirst($matches[1]);
            $upd = $pdo->prepare("UPDATE users SET barangay = ? WHERE id = ?");
            $upd->execute([$brgy, $bhw['id']]);
            echo "Backfilled BHW {$bhw['username']} to $brgy.\n";
        }
    }

} catch (PDOException $e) {
    die("Global Migration Error: " . $e->getmessage());
}
?>

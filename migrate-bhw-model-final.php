<?php
require_once 'config/database.php';

try {
    echo "--- APPLYING BHW MODEL MIGRATION ---\n";

    // 1. Update healers table
    $stmt = $pdo->query("DESCRIBE healers");
    $cols = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (!in_array('managed_by_bhw_id', $cols)) {
        $pdo->exec("ALTER TABLE healers ADD COLUMN managed_by_bhw_id INT DEFAULT NULL AFTER id");
        echo "Added 'managed_by_bhw_id' to healers.\n";
    }

    if (!in_array('is_available', $cols)) {
        $pdo->exec("ALTER TABLE healers ADD COLUMN is_available BOOLEAN DEFAULT TRUE");
        echo "Added 'is_available' to healers.\n";
    }

    // Optional: Add foreign key for managed_by_bhw_id
    try {
        $pdo->exec("ALTER TABLE healers ADD CONSTRAINT fk_managed_by_bhw FOREIGN KEY (managed_by_bhw_id) REFERENCES users(id) ON DELETE SET NULL");
        echo "Added foreign key constraint to healers.\n";
    } catch (PDOException $e) {
        echo "Foreign key constraint already exists or could not be added: " . $e->getMessage() . "\n";
    }

    // 2. Ensure other tables are ready (redundancy check)
    $stmt = $pdo->query("DESCRIBE users");
    $userCols = $stmt->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('barangay', $userCols)) {
        $pdo->exec("ALTER TABLE users ADD COLUMN barangay VARCHAR(100) DEFAULT NULL AFTER role");
        echo "Added 'barangay' to users.\n";
    }

    echo "\n[SUCCESS] BHW Model Migration complete.\n";

} catch (PDOException $e) {
    echo "\n[ERROR] Migration failed: " . $e->getMessage() . "\n";
}

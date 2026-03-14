<?php
require_once 'config/database.php';

try {
    // Drop the unique index on the username column
    // In MySQL, unique constraints are often named the same as the column
    // We'll use a multi-step approach to ensure we target correctly
    
    // First, let's identify the index name if it's not simply 'username'
    $stmt = $pdo->query("SHOW INDEX FROM users WHERE Column_name = 'username' AND Non_unique = 0");
    $index = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($index) {
        $indexName = $index['Key_name'];
        echo "Found unique index: $indexName. Dropping it...\n";
        $pdo->exec("ALTER TABLE users DROP INDEX `$indexName` ");
        echo "Successfully removed UNIQUE constraint from username.\n";
    } else {
        echo "No unique index found on 'username' column.\n";
    }

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>

<?php
require_once 'config/database.php';

try {
    // 1. Create the barangay table
    $pdo->exec("CREATE TABLE IF NOT EXISTS barangays (
        id INT AUTO_INCREMENT PRIMARY KEY,
        barangay_name VARCHAR(100) NOT NULL UNIQUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // 2. Check if it's empty, and seed with default values if so
    $check = $pdo->query("SELECT COUNT(*) FROM barangays");
    if ($check->fetchColumn() == 0) {
        $defaults = [
            'Brgy. Cogon', 'Brgy. Nadongholan', 'Brgy. Poblacion', 'Brgy. San Jose',
            'Brgy. Mahayahay', 'Brgy. Rizal', 'Brgy. Punta Princesa', 'Brgy. Labangon',
            'Brgy. Guadalupe', 'Brgy. Talisay', 'Brgy. Basak Pardo', 'Brgy. Inayawan'
        ];
        $stmt = $pdo->prepare("INSERT INTO barangays (barangay_name) VALUES (?)");
        foreach ($defaults as $name) {
            $stmt->execute([$name]);
        }
        echo "Successfully created and seeded 'barangays' table.";
    } else {
        echo "'barangays' table already exists and has data.";
    }

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>

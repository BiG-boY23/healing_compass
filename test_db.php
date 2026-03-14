<?php
header('Content-Type: application/json');
try {
    require_once 'config/database.php';
    echo json_encode(['status' => 'OK', 'drivers' => PDO::getAvailableDrivers()]);
} catch (Exception $e) {
    echo json_encode(['status' => 'ERROR', 'message' => $e->getMessage()]);
}
?>

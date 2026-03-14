<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    die("Not authorized");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_SESSION['user_id'];
    $healer_id = $_POST['healer_id'] ?? null;
    $date = $_POST['date'] ?? null;
    $time = $_POST['appointment_time'] ?? null;

    if (!$healer_id || !$date || !$time) {
        header("Location: ../booking.php?healer_id=$healer_id&error=" . urlencode("All fields are required."));
        exit();
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO appointments (user_id, healer_id, appointment_date, appointment_time, status) VALUES (?, ?, ?, ?, 'pending')");
        $stmt->execute([$user_id, $healer_id, $date, $time]);
        
        header("Location: ../booking.php?healer_id=$healer_id&success=1");
        exit();
    } catch (PDOException $e) {
        header("Location: ../booking.php?healer_id=$healer_id&error=" . urlencode("Booking failed: " . $e->getMessage()));
        exit();
    }
}
?>

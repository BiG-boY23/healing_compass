<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die("Unauthorized");
}

// Ensure uploads directory exists
if (!is_dir('../uploads')) {
    mkdir('../uploads', 0777, true);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $name = $_POST['plant_name'];
        $scientific = $_POST['scientific_name'];
        $desc = $_POST['description'];
        $illness = $_POST['illness_treated'];
        $prep = $_POST['preparation_method'];
        $barangay = $_POST['barangay'] ?? '';

        $target_file = "";
        if (isset($_FILES['plant_image']) && $_FILES['plant_image']['error'] == 0) {
            $file_ext = pathinfo($_FILES["plant_image"]["name"], PATHINFO_EXTENSION);
            $filename = time() . "_" . uniqid() . "." . $file_ext;
            $target_path = "../uploads/" . $filename;
            if (move_uploaded_file($_FILES["plant_image"]["tmp_name"], $target_path)) {
                $target_file = "uploads/" . $filename;
            }
        }

        try {
            $stmt = $pdo->prepare("INSERT INTO plants (plant_name, scientific_name, description, illness_treated, preparation_method, plant_image, is_approved, barangay) VALUES (?, ?, ?, ?, ?, ?, 1, ?)");
            $stmt->execute([$name, $scientific, $desc, $illness, $prep, $target_file, $barangay]);
            header("Location: ../admin-plants.php?success=added");
        } catch (PDOException $e) {
            die("Error: " . $e->getMessage());
        }
    }

    if ($action === 'edit') {
        $id = $_POST['id'];
        $name = $_POST['plant_name'];
        $scientific = $_POST['scientific_name'];
        $desc = $_POST['description'];
        $illness = $_POST['illness_treated'];
        $prep = $_POST['preparation_method'];
        $barangay = $_POST['barangay'] ?? '';
        $is_approved = isset($_POST['is_approved']) ? 1 : 0;

        // Handle File Upload if new image provided
        $image_sql = "";
        $params = [$name, $scientific, $desc, $illness, $prep, $is_approved, $barangay];
        
        if (isset($_FILES['plant_image']) && $_FILES['plant_image']['error'] == 0) {
            $file_ext = pathinfo($_FILES["plant_image"]["name"], PATHINFO_EXTENSION);
            $filename = time() . "_" . uniqid() . "." . $file_ext;
            $target_path = "../uploads/" . $filename;
            if (move_uploaded_file($_FILES["plant_image"]["tmp_name"], $target_path)) {
                $image_sql = ", plant_image = ?";
                $params[] = "uploads/" . $filename;
            }
        }
        
        $params[] = $id;

        try {
            $stmt = $pdo->prepare("UPDATE plants SET plant_name = ?, scientific_name = ?, description = ?, illness_treated = ?, preparation_method = ?, is_approved = ?, barangay = ? $image_sql WHERE id = ?");
            $stmt->execute($params);
            header("Location: ../admin-plants.php?success=updated");
        } catch (PDOException $e) {
            die("Error: " . $e->getMessage());
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'delete') {
    $id = $_GET['id'];
    try {
        $stmt = $pdo->prepare("DELETE FROM plants WHERE id = ?");
        $stmt->execute([$id]);
        header("Location: ../admin-plants.php?success=deleted");
    } catch (PDOException $e) {
        die("Error: " . $e->getMessage());
    }
}
?>

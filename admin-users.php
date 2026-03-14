<?php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$role = $_SESSION['role'];
$username = $_SESSION['username'];

// Fetch users
try {
    $stmt = $pdo->query("SELECT id, username, email, role, created_at FROM users ORDER BY created_at DESC");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $users = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management | Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="dashboard-app">

    <div class="container-fluid p-0">
        <div class="row g-0">
            <!-- Sidebar -->
            <nav class="col-md-3 col-lg-2 d-md-block sidebar collapse p-4">
                <div class="d-flex flex-column align-items-center mb-5 text-center">
                    <img src="assets/img/logo.png" alt="Logo" style="height: 80px;" class="mb-3">
                    <h5 class="fw-bold text-success mb-0">HEALING<br>COMPASS</h5>
                </div>
                
                <a href="admin-dashboard.php" class="sidebar-link">
                    <i class="bi bi-speedometer2 me-3"></i> Admin Panel
                </a>

                <a href="dashboard.php" class="sidebar-link">
                    <i class="bi bi-house-door me-3"></i> User View
                </a>

                <a href="admin-plants.php" class="sidebar-link">
                    <i class="bi bi-leaf me-3"></i> Manage Plants
                </a>

                <a href="admin-healers.php" class="sidebar-link">
                    <i class="bi bi-people me-3"></i> Manage Healers
                </a>

                <a href="admin-users.php" class="sidebar-link active">
                    <i class="bi bi-person-badge me-3"></i> User Management
                </a>

                <a href="admin-logs.php" class="sidebar-link">
                    <i class="bi bi-journal-text me-3"></i> Activity Logs
                </a>

                <div class="mt-auto pt-5">
                    <a href="controllers/AuthController.php?action=logout" class="sidebar-link text-danger">
                        <i class="bi bi-box-arrow-right me-3"></i> Logout
                    </a>
                </div>
            </nav>

            <!-- Main Content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-5 py-4">
                <div class="d-flex justify-content-between align-items-center mb-5">
                    <div>
                        <h2 class="fw-bold text-success mb-1">User Management</h2>
                        <p class="text-muted mb-0">Control access levels and verify traditional healers</p>
                    </div>
                </div>

                <div class="dash-tile p-4 overflow-hidden shadow-sm border bg-white">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="bg-light">
                                <tr>
                                    <th class="border-0 px-3">Username</th>
                                    <th class="border-0 px-3">Email Address</th>
                                    <th class="border-0 px-3">Role</th>
                                    <th class="border-0 px-3">Joined Date</th>
                                    <th class="border-0 px-3 text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $u): ?>
                                <tr>
                                    <td class="px-3">
                                        <div class="d-flex align-items-center">
                                            <div class="bg-success bg-opacity-10 text-success rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px;">
                                                <i class="bi bi-person-fill"></i>
                                            </div>
                                            <span class="fw-bold"><?= htmlspecialchars($u['username']) ?></span>
                                        </div>
                                    </td>
                                    <td class="px-3 text-muted"><?= htmlspecialchars($u['email']) ?></td>
                                    <td class="px-3">
                                        <span class="badge rounded-pill px-3 py-2 <?= $u['role'] === 'admin' ? 'bg-danger bg-opacity-10 text-danger' : ($u['role'] === 'healer' ? 'bg-success bg-opacity-10 text-success' : 'bg-primary bg-opacity-10 text-primary') ?>">
                                            <?= ucfirst($u['role']) ?>
                                        </span>
                                    </td>
                                    <td class="px-3 small text-muted"><?= date('M d, Y', strtotime($u['created_at'])) ?></td>
                                    <td class="px-3 text-end">
                                        <button class="btn btn-sm btn-light rounded-circle" title="Edit Permissions"><i class="bi bi-shield-lock text-success"></i></button>
                                        <button class="btn btn-sm btn-light rounded-circle" title="Deactivate Account"><i class="bi bi-slash-circle text-danger"></i></button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

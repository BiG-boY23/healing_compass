<?php
session_start();
require_once 'config/database.php';

// Session Security: BHW Role Check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'bhw') {
    header("Location: login.php");
    exit();
}

$user_id   = $_SESSION['user_id'];
$username  = $_SESSION['username'];
$barangay  = $_SESSION['barangay'] ?? '';

if (empty($barangay)) {
    $stmt = $pdo->prepare("SELECT barangay FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $uData = $stmt->fetch(PDO::FETCH_ASSOC);
    $barangay = $uData['barangay'] ?? 'General Area';
    $_SESSION['barangay'] = $barangay;
}

$success = $_GET['success'] ?? '';
$error   = $_GET['error'] ?? '';

// Handle Actions (Approve/Edit)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'verify_plant') {
        $plant_id = (int)$_POST['plant_id'];
        try {
            $stmt = $pdo->prepare("UPDATE plants SET is_approved = 1 WHERE id = ? AND barangay = ?");
            $stmt->execute([$plant_id, $barangay]);
            $success = "Plant entry verified and approved!";
        } catch (PDOException $e) { $error = $e->getMessage(); }
    }
}

// Fetch local plants
try {
    $stmt = $pdo->prepare("SELECT * FROM plants WHERE barangay = ? ORDER BY is_approved ASC, created_at DESC");
    $stmt->execute([$barangay]);
    $plants = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $plants = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Botanical Audit | BHW Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Lexend:wght@300;400;500;600;700;800&family=Outfit:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        :root {
            --bhw-primary: #1565C0;
            --bhw-accent: #E3F2FD;
            --bhw-text: #0D47A1;
        }
        .sidebar { background: white; border-right: 1px solid #e3e8ef; height: 100vh; position: fixed; z-index: 100; }
        .sidebar-link { color: #5c6b5c; font-weight: 500; padding: 14px 20px; display: flex; align-items: center; text-decoration: none; border-radius: 16px; margin: 4px 0; transition: 0.3s; }
        .sidebar-link:hover { background: #f0f4f9; color: var(--bhw-primary); }
        .sidebar-link.active { background: var(--bhw-primary) !important; color: white !important; }
        .plant-card-bhw { background: white; border-radius: 20px; border: 1px solid #eee; overflow: hidden; transition: 0.3s; height: 100%; }
        .plant-card-bhw:hover { transform: translateY(-5px); box-shadow: 0 10px 25px rgba(0,0,0,0.05); }
    </style>
</head>
<body class="dashboard-app" style="background-color: #f0f4f9;">

<div class="container-fluid p-0">
    <div class="row g-0">
        <!-- Sidebar -->
        <nav class="col-md-3 col-lg-2 sidebar p-4">
            <div class="d-flex align-items-center mb-5 ps-2">
                <img src="assets/img/logo.png" alt="Logo" style="height: 40px;" class="me-3">
                <h6 class="fw-bold mb-0" style="color: var(--bhw-primary); line-height: 1.1;">BHW<br>DASHBOARD</h6>
            </div>
            <div class="nav-menu">
                <a href="bhw-dashboard.php" class="sidebar-link">
                    <i data-lucide="layout-dashboard"></i> Barangay Home
                </a>
                <a href="bhw-healers.php" class="sidebar-link">
                    <i data-lucide="shield-check"></i> Healer Verification
                </a>
                <a href="bhw-plants.php" class="sidebar-link active">
                    <i data-lucide="leaf"></i> local Plants
                </a>
                <a href="bhw-alerts.php" class="sidebar-link">
                    <i data-lucide="megaphone"></i> Health Alerts
                </a>
                <div class="mt-5 mb-2 small text-muted text-uppercase fw-bold ps-3" style="font-size: 0.6rem;">Platform Access</div>
                <a href="dashboard.php" class="sidebar-link">
                    <i data-lucide="home"></i> User View
                </a>
            </div>
            <div style="position: absolute; bottom: 30px; left: 20px; right: 20px;">
                <a href="controllers/AuthController.php?action=logout" class="sidebar-link text-danger">
                    <i data-lucide="log-out"></i> Logout
                </a>
            </div>
        </nav>

        <!-- Main -->
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-5 py-5 min-vh-100">
            <div class="d-flex justify-content-between align-items-center mb-5">
                <div>
                    <h1 class="fw-bold mb-1" style="font-size: 2.2rem; letter-spacing: -1px; color: var(--bhw-text);">Botanical Inventory</h1>
                    <p class="text-muted fw-medium mb-0">Local Medicinal Plants in <strong><?= htmlspecialchars($barangay) ?></strong></p>
                </div>
            </div>

            <div class="row g-4">
                <?php if (empty($plants)): ?>
                    <div class="col-12">
                        <div class="card border-0 rounded-4 shadow-sm p-5 text-center">
                            <i data-lucide="sprout" class="text-muted mb-3 mx-auto" style="width: 48px; height: 48px;"></i>
                            <h5 class="text-muted">No botanical entries found for this barangay.</h5>
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach ($plants as $p): ?>
                        <div class="col-md-4">
                            <div class="plant-card-bhw shadow-sm">
                                <div style="height: 180px; background: url('<?= htmlspecialchars($p['plant_image'] ?? 'assets/img/hero.png') ?>') center/cover;"></div>
                                <div class="p-4">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <h5 class="fw-bold mb-0 text-dark"><?= htmlspecialchars($p['plant_name']) ?></h5>
                                        <?php if ($p['is_approved']): ?>
                                            <span class="badge bg-success bg-opacity-10 text-success rounded-pill px-2 py-1 small">Verified</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning bg-opacity-10 text-warning rounded-pill px-2 py-1 small">Pending</span>
                                        <?php endif; ?>
                                    </div>
                                    <p class="text-muted small italic mb-3"><?= htmlspecialchars($p['scientific_name']) ?></p>
                                    <div class="mb-3">
                                        <label class="small fw-bold text-muted text-uppercase" style="font-size: 0.65rem;">Treats</label>
                                        <p class="small text-dark mb-0"><?= htmlspecialchars($p['illness_treated']) ?></p>
                                    </div>
                                    
                                    <?php if (!$p['is_approved']): ?>
                                        <form method="POST">
                                            <input type="hidden" name="action" value="verify_plant">
                                            <input type="hidden" name="plant_id" value="<?= $p['id'] ?>">
                                            <button class="btn btn-primary btn-sm w-100 rounded-pill fw-bold">Audit & Approve</button>
                                        </form>
                                    <?php else: ?>
                                        <button class="btn btn-outline-secondary btn-sm w-100 rounded-pill fw-bold" disabled>Audit Complete</button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </main>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    lucide.createIcons();
    <?php if ($success): ?>Swal.fire({ icon: 'success', title: 'Verified', text: '<?= $success ?>' });<?php endif; ?>
</script>
</body>
</html>

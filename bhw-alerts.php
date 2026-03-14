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

// Handle Actions (Post/Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'post_alert') {
        $title   = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $type    = $_POST['alert_type'] ?? 'info';
        $expiry  = $_POST['expires_at'] ?: null;
        
        if ($title && $content) {
            try {
                $stmt = $pdo->prepare("INSERT INTO barangay_alerts (bhw_id, barangay, title, content, alert_type, expires_at) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$user_id, $barangay, $title, $content, $type, $expiry]);
                $success = "Health alert posted to your barangay!";
            } catch (PDOException $e) { $error = $e->getMessage(); }
        } else {
            $error = "Title and content are required.";
        }
    } elseif ($action === 'delete_alert') {
        $alert_id = (int)$_POST['alert_id'];
        try {
            $stmt = $pdo->prepare("DELETE FROM barangay_alerts WHERE id = ? AND bhw_id = ?");
            $stmt->execute([$alert_id, $user_id]);
            $success = "Alert removed.";
        } catch (PDOException $e) { $error = $e->getMessage(); }
    }
}

// Fetch alerts for this barangay
try {
    $stmt = $pdo->prepare("SELECT * FROM barangay_alerts WHERE barangay = ? ORDER BY created_at DESC");
    $stmt->execute([$barangay]);
    $alerts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $alerts = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Health Alerts | BHW Dashboard</title>
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
        .alert-card { border-left: 6px solid var(--bhw-primary); background: white; border-radius: 15px; }
        .alert-warning { border-left-color: #ff9800; }
        .alert-event { border-left-color: #4caf50; }
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
                <a href="bhw-plants.php" class="sidebar-link">
                    <i data-lucide="leaf"></i> local Plants
                </a>
                <a href="bhw-alerts.php" class="sidebar-link active">
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
                    <h1 class="fw-bold mb-1" style="font-size: 2.2rem; letter-spacing: -1px; color: var(--bhw-text);">Health Alerts</h1>
                    <p class="text-muted fw-medium mb-0">Broadcast announcements to <strong><?= htmlspecialchars($barangay) ?></strong></p>
                </div>
                <button class="btn btn-primary rounded-pill px-4 shadow-sm" data-bs-toggle="modal" data-bs-target="#postAlertModal">
                    <i data-lucide="plus" class="me-2" style="width: 18px;"></i> Create New Broadcast
                </button>
            </div>

            <div class="row g-4">
                <div class="col-lg-8">
                    <h5 class="fw-bold mb-4">Active Broadcasts</h5>
                    <?php if (empty($alerts)): ?>
                        <div class="card border-0 rounded-4 shadow-sm p-5 text-center">
                            <i data-lucide="bell-off" class="text-muted mb-3 mx-auto" style="width: 48px; height: 48px;"></i>
                            <h5 class="text-muted">No alerts posted yet.</h5>
                        </div>
                    <?php else: ?>
                        <?php foreach ($alerts as $a): ?>
                            <div class="card border-0 shadow-sm mb-3 alert-card alert-<?= $a['alert_type'] ?> p-4">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <div class="d-flex align-items-center gap-2 mb-2">
                                            <span class="badge rounded-pill bg-<?= $a['alert_type'] == 'warning' ? 'warning' : ($a['alert_type'] == 'event' ? 'success' : 'primary') ?> px-3 py-1 fw-bold small text-uppercase" style="font-size: 0.6rem;">
                                                <?= $a['alert_type'] ?>
                                            </span>
                                            <small class="text-muted"><?= date('M d, Y @ H:i', strtotime($a['created_at'])) ?></small>
                                        </div>
                                        <h5 class="fw-bold text-dark mb-2"><?= htmlspecialchars($a['title']) ?></h5>
                                        <p class="text-muted mb-0"><?= htmlspecialchars($a['content']) ?></p>
                                        <?php if ($a['expires_at']): ?>
                                            <div class="mt-3 small text-danger fw-bold">
                                                <i data-lucide="calendar-x" class="d-inline" style="width: 14px;"></i> Expires: <?= date('M d, Y', strtotime($a['expires_at'])) ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <form method="POST" onsubmit="return confirm('Remove this alert from public view?')">
                                        <input type="hidden" name="action" value="delete_alert">
                                        <input type="hidden" name="alert_id" value="<?= $a['id'] ?>">
                                        <button class="btn btn-sm btn-light rounded-circle"><i data-lucide="trash-2" class="text-danger" style="width: 16px;"></i></button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <div class="col-lg-4">
                    <div class="card border-0 rounded-4 shadow-sm p-4 bg-white">
                        <h6 class="fw-bold text-dark mb-3">Broadcast Guidelines</h6>
                        <ul class="small text-muted ps-3">
                            <li class="mb-2">Use <strong>Warning</strong> for urgent health advisories.</li>
                            <li class="mb-2">Use <strong>Event</strong> for distributions or clinics.</li>
                            <li class="mb-2">Use <strong>Information</strong> for general wellness tips.</li>
                            <li>Alerts are visible on the public map for your barangay.</li>
                        </ul>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Post Alert Modal -->
<div class="modal fade" id="postAlertModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header border-0 pb-0">
                <h5 class="fw-bold text-primary">Post Barangay Alert</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body p-4">
                    <input type="hidden" name="action" value="post_alert">
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">Alert Title</label>
                        <input type="text" name="title" class="form-control rounded-3" placeholder="e.g. Free Herbal Tea Distribution" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">Content</label>
                        <textarea name="content" class="form-control rounded-3" rows="4" placeholder="Describe the details..." required></textarea>
                    </div>
                    <div class="row">
                        <div class="col-6 mb-3">
                            <label class="form-label small fw-bold text-muted">Alert Type</label>
                            <select name="alert_type" class="form-select rounded-3">
                                <option value="info">Information</option>
                                <option value="warning">Warning</option>
                                <option value="event">Event</option>
                            </select>
                        </div>
                        <div class="col-6 mb-3">
                            <label class="form-label small fw-bold text-muted">Expires At</label>
                            <input type="date" name="expires_at" class="form-control rounded-3">
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-5 fw-bold">Broadcast Now</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    lucide.createIcons();
    <?php if ($success): ?>Swal.fire({ icon: 'success', title: 'Action Complete', text: '<?= $success ?>' });<?php endif; ?>
</script>
</body>
</html>

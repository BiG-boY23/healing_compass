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
$barangay  = $_SESSION['barangay'] ?? ''; // Reverted session key

// If barangay is missing in session, try to fetch it from the database
if (empty($barangay)) {
    $stmt = $pdo->prepare("SELECT barangay FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $uData = $stmt->fetch(PDO::FETCH_ASSOC);
    $barangay = $uData['barangay'] ?? 'General Area';
    $_SESSION['barangay'] = $barangay;
}

$success = $_GET['success'] ?? '';
$error   = $_GET['error'] ?? '';

// ── MODULE A: HEALER VERIFICATION LOGIC ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'verify_healer') {
        $healer_id = (int)$_POST['healer_id'];
        try {
            $stmt = $pdo->prepare("UPDATE healers SET is_verified = 1 WHERE id = ? AND barangay = ?");
            $stmt->execute([$healer_id, $barangay]);
            $success = "Healer verified successfully.";
        } catch (PDOException $e) { $error = $e->getMessage(); }
    }
    if ($_POST['action'] === 'confirm_booking') {
        $booking_id = (int)$_POST['booking_id'];
        try {
            // Only confirm if the healer is managed by this BHW
            $stmt = $pdo->prepare("UPDATE appointments SET status = 'accepted' WHERE id = ? AND healer_id IN (SELECT id FROM healers WHERE managed_by_bhw_id = ?)");
            $stmt->execute([$booking_id, $user_id]);
            $success = "Booking confirmed! The patient will be notified.";
        } catch (PDOException $e) { $error = $e->getMessage(); }
    }
    if ($_POST['action'] === 'cancel_booking') {
        $booking_id = (int)$_POST['booking_id'];
        try {
            $stmt = $pdo->prepare("UPDATE appointments SET status = 'rejected' WHERE id = ? AND healer_id IN (SELECT id FROM healers WHERE managed_by_bhw_id = ?)");
            $stmt->execute([$booking_id, $user_id]);
            $success = "Booking rejected.";
        } catch (PDOException $e) { $error = $e->getMessage(); }
    }
    if ($_POST['action'] === 'toggle_availability') {
        $healer_id = (int)$_POST['healer_id'];
        $status = (int)$_POST['status'];
        try {
            $stmt = $pdo->prepare("UPDATE healers SET is_available = ? WHERE id = ? AND managed_by_bhw_id = ?");
            $stmt->execute([$status, $healer_id, $user_id]);
            $success = "Healer availability updated.";
        } catch (PDOException $e) { $error = $e->getMessage(); }
    }
    if ($_POST['action'] === 'post_alert') {
        $title   = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $type    = $_POST['alert_type'] ?? 'info';
        $expiry  = $_POST['expires_at'] ?: null;
        
        if ($title && $content) {
            try {
                $stmt = $pdo->prepare("INSERT INTO barangay_alerts (bhw_id, barangay, title, content, alert_type, expires_at) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$user_id, $barangay, $title, $content, $type, $expiry]);
                $success = "Alert posted successfully!";
            } catch (PDOException $e) { $error = $e->getMessage(); }
        } else {
            $error = "Title and content are required.";
        }
    }
}

// ── CORE DATA FETCHING (LOCAL ONLY) ──────────────────────────────────────────
try {
    // Local Statistics (Filtered by BHW ID as requested)
    $totalLocalHealers = $pdo->prepare("SELECT COUNT(*) FROM healers WHERE managed_by_bhw_id = ?");
    $totalLocalHealers->execute([$user_id]);
    $localHealersCount = $totalLocalHealers->fetchColumn();

    $pendingHealers = $pdo->prepare("SELECT COUNT(*) FROM healers WHERE barangay = ? AND is_verified = 0");
    $pendingHealers->execute([$barangay]);
    $pendingHealersCount = $pendingHealers->fetchColumn();

    $activeAlerts = $pdo->prepare("SELECT COUNT(*) FROM barangay_alerts WHERE barangay = ? AND (expires_at IS NULL OR expires_at > NOW())");
    $activeAlerts->execute([$barangay]);
    $activeAlertsCount = $activeAlerts->fetchColumn();

    // Module A Table: Healers in THIS BHW's barangay (Relaxed from managed_by if it was showing nothing)
    $managedHealers = $pdo->prepare("SELECT * FROM healers WHERE barangay = ? ORDER BY full_name ASC");
    $managedHealers->execute([$barangay]);
    $managedHealerList = $managedHealers->fetchAll(PDO::FETCH_ASSOC);

    // Module D: Appointment Coordination (Pending bookings for managed healers)
    $stmtBookings = $pdo->prepare("
        SELECT a.*, h.full_name as healer_name, u.username as patient_name 
        FROM appointments a 
        JOIN healers h ON a.healer_id = h.id 
        JOIN users u ON a.user_id = u.id 
        WHERE h.managed_by_bhw_id = ? AND a.status = 'pending'
        ORDER BY a.appointment_date ASC, a.appointment_time ASC
    ");
    $stmtBookings->execute([$user_id]);
    $pendingBookings = $stmtBookings->fetchAll(PDO::FETCH_ASSOC);

    // Module B: Local Botanical Inventory
    $localPlants = $pdo->prepare("SELECT * FROM plants WHERE barangay = ? ORDER BY created_at DESC LIMIT 5");
    $localPlants->execute([$barangay]);
    $localPlantList = $localPlants->fetchAll(PDO::FETCH_ASSOC);

    // Module C: Local Health Alerts
    $localAlerts = $pdo->prepare("SELECT * FROM barangay_alerts WHERE barangay = ? ORDER BY created_at DESC LIMIT 5");
    $localAlerts->execute([$barangay]);
    $localAlertList = $localAlerts->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Error loading BHW data: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BHW Dashboard | Healing Compass</title>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Lexend:wght@300;400;500;600;700;800&family=Outfit:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        :root {
            --bhw-primary: #1565C0;
            --bhw-accent: #E3F2FD;
            --bhw-text: #0D47A1;
        }
        .sidebar { height: 100vh; position: fixed; z-index: 1000; }
        .sidebar-link { transition: all 0.2s ease; border-radius: 12px; margin: 4px 15px; }
        .sidebar-link:hover { background-color: var(--bhw-accent) !important; color: var(--bhw-primary) !important; }
        .sidebar-link.active { background-color: var(--bhw-primary) !important; color: white !important; box-shadow: 0 4px 12px rgba(21, 101, 192, 0.2); }
        .stat-card {
            background: white; border-radius: 1.5rem; padding: 1.5rem;
            border: 1px solid rgba(0,0,0,0.03); box-shadow: 0 4px 15px rgba(21,101,192,0.04);
            transition: transform 0.3s ease;
        }
        .stat-card:hover { transform: translateY(-5px); }
        .bhw-badge-pending { background: rgba(255,152,0,0.1); color: #F57C00; font-weight: 700; }
        .bhw-badge-verified { background: rgba(76,175,80,0.1); color: #388E3C; font-weight: 700; }
        .alert-item { border-left: 4px solid var(--bhw-primary); background: #fbfcfe; }
    </style>
</head>
<body class="dashboard-app" style="background-color: #f0f4f9;">

<div class="container-fluid p-0">
    <div class="row g-0">
        <!-- Sidebar -->
        <nav class="col-md-3 col-lg-2 sidebar p-4" style="background: white; border-right: 1px solid #e3e8ef; height: 100vh; position: fixed; z-index: 100;">
            <div class="d-flex align-items-center mb-5 ps-2">
                <img src="assets/img/logo.png" alt="Logo" style="height: 40px;" class="me-3">
                <h6 class="fw-bold mb-0" style="color: var(--bhw-primary); line-height: 1.1;">BHW<br>DASHBOARD</h6>
            </div>
            <div class="nav-menu">
                <a href="bhw-dashboard.php" class="sidebar-link active">
                    <i data-lucide="layout-dashboard"></i> Barangay Home
                </a>
                <a href="bhw-healers.php" class="sidebar-link">
                    <i data-lucide="shield-check"></i> Healer Verification
                </a>
                <a href="bhw-plants.php" class="sidebar-link">
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
            <!-- Debugging Banner (Temporary) -->
            <div class="alert alert-info py-2 px-3 mb-4 rounded-4 shadow-sm border-0 d-flex align-items-center">
                <i data-lucide="bug" class="me-2" style="width: 16px;"></i>
                <small class="fw-bold">
                    Debug Mode: Logged in BHW ID: <?= $user_id ?> | Barangay: <?= htmlspecialchars($barangay) ?> | Total Healers in DB: <?= $localHealersCount ?>
                </small>
            </div>

            <!-- Header -->
            <div class="d-flex justify-content-between align-items-center mb-5">
                <div>
                    <h1 class="fw-bold mb-1" style="font-size: 2.2rem; letter-spacing: -1px; color: var(--bhw-text);">Barangay Authority</h1>
                    <p class="text-muted fw-medium mb-0">Health Management for <strong><?= htmlspecialchars($barangay) ?></strong></p>
                </div>
                <div class="text-end">
                    <span class="badge rounded-pill px-3 py-2" style="background: var(--bhw-accent); color: var(--bhw-primary); font-weight: 700;">
                        Logged in as: <?= htmlspecialchars($username) ?>
                    </span>
                </div>
            </div>

            <!-- Stats Row -->
            <div class="row g-4 mb-5">
                <div class="col-md-4">
                    <div class="stat-card d-flex align-items-center gap-3 border-start border-primary border-4">
                        <div class="p-3 rounded-4" style="background: var(--bhw-accent);">
                            <i data-lucide="users" style="color: var(--bhw-primary);"></i>
                        </div>
                        <div>
                            <h3 class="fw-bold mb-0"><?= $localHealersCount ?></h3>
                            <small class="text-muted">Total Local Healers</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-card d-flex align-items-center gap-3 border-start border-warning border-4">
                        <div class="p-3 rounded-4" style="background: #FFF8E1;">
                            <i data-lucide="clock" style="color: #F57C00;"></i>
                        </div>
                        <div>
                            <h3 class="fw-bold mb-0"><?= $pendingHealersCount ?></h3>
                            <small class="text-muted">Pending Approvals</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-card d-flex align-items-center gap-3 border-start border-info border-4">
                        <div class="p-3 rounded-4" style="background: #E0F7FA;">
                            <i data-lucide="bell" style="color: #0097A7;"></i>
                        </div>
                        <div>
                            <h3 class="fw-bold mb-0"><?= $activeAlertsCount ?></h3>
                            <small class="text-muted">Active Alerts</small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-4">
                <!-- Module D: Appointment Coordination -->
                <div class="col-lg-12 mb-2">
                    <div class="card border-0 rounded-4 shadow-sm p-4" style="background: #ffffff; border: 1px solid #e3e8ef !important;">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <div>
                                <h5 class="fw-bold text-dark mb-1"><i data-lucide="calendar-clock" class="me-2 text-primary" style="width: 20px;"></i> Appointment Coordination</h5>
                                <p class="small text-muted mb-0">Manage incoming requests for your assigned healers.</p>
                            </div>
                            <span class="badge bg-primary rounded-pill"><?= count($pendingBookings) ?> New Requests</span>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead class="table-light">
                                    <tr style="font-size: 0.7rem; text-transform: uppercase; color: #6c757d; letter-spacing: 0.5px;">
                                        <th>Patient</th>
                                        <th>Assigned Healer</th>
                                        <th>Date & Time</th>
                                        <th class="text-end">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($pendingBookings)): ?>
                                        <tr>
                                            <td colspan="4" class="text-center py-4 text-muted small">No pending booking requests to coordinate.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($pendingBookings as $b): ?>
                                            <tr>
                                                <td>
                                                    <div class="fw-bold text-dark"><?= htmlspecialchars($b['patient_name']) ?></div>
                                                </td>
                                                <td>
                                                    <span class="badge bg-info bg-opacity-10 text-info fw-bold rounded-pill"><?= htmlspecialchars($b['healer_name']) ?></span>
                                                </td>
                                                <td class="small fw-medium">
                                                    <?= date('M d, Y', strtotime($b['appointment_date'])) ?> at <?= date('h:i A', strtotime($b['appointment_time'])) ?>
                                                </td>
                                                <td class="text-end">
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="action" value="confirm_booking">
                                                        <input type="hidden" name="booking_id" value="<?= $b['id'] ?>">
                                                        <button class="btn btn-sm btn-primary rounded-pill px-3 shadow-sm me-1">Confirm</button>
                                                    </form>
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="action" value="cancel_booking">
                                                        <input type="hidden" name="booking_id" value="<?= $b['id'] ?>">
                                                        <button class="btn btn-sm btn-outline-danger rounded-pill px-3">Decline</button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Module A: Managed Healers -->
                <div class="col-lg-8">
                    <div class="card border-0 rounded-4 shadow-sm p-4">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h5 class="fw-bold text-dark mb-0">My Managed Traditional Healers</h5>
                            <button class="btn btn-outline-primary btn-sm rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#registerHealerModal">Register New</button>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead class="table-light">
                                    <tr style="font-size: 0.75rem; text-transform: uppercase; color: #6c757d;">
                                        <th>Healer Name</th>
                                        <th>Specialization</th>
                                        <th>Availability</th>
                                        <th class="text-center">Quick Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($managedHealerList)): ?>
                                        <tr>
                                            <td colspan="4" class="text-center py-5 text-muted">
                                                <div class="mb-2"><i data-lucide="users-2" style="width: 38px; opacity: 0.2;"></i></div>
                                                <h6 class="fw-bold mb-1">No healers assigned to you yet.</h6>
                                                <p class="small mb-0">Use the button above to register or wait for Admin assignment.</p>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($managedHealerList as $lh): ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <img src="<?= htmlspecialchars($lh['profile_picture'] ?: 'assets/img/avatar.png') ?>" class="rounded-circle me-2" style="width: 32px; height: 32px; object-fit: cover;">
                                                    <div>
                                                        <div class="fw-bold"><?= htmlspecialchars($lh['full_name']) ?></div>
                                                        <div class="small text-muted" style="font-size: 0.65rem;"><?= htmlspecialchars($lh['contact_info']) ?></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="small"><?= htmlspecialchars($lh['specialization']) ?></td>
                                            <td>
                                                <?php if ($lh['is_available']): ?>
                                                    <span class="badge rounded-pill bg-success-subtle text-success px-3 py-2 border border-success-subtle">Online</span>
                                                <?php else: ?>
                                                    <span class="badge rounded-pill bg-danger-subtle text-danger px-3 py-2 border border-danger-subtle">Unavailable</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center">
                                                <form method="POST">
                                                    <input type="hidden" name="action" value="toggle_availability">
                                                    <input type="hidden" name="healer_id" value="<?= $lh['id'] ?>">
                                                    <input type="hidden" name="status" value="<?= $lh['is_available'] ? 0 : 1 ?>">
                                                    <button class="btn btn-sm <?= $lh['is_available'] ? 'btn-outline-danger' : 'btn-outline-success' ?> rounded-pill px-3">
                                                        <?= $lh['is_available'] ? 'Set Away' : 'Set Active' ?>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Action Center & Sidebar Modules -->
                <div class="col-lg-4">
                    <!-- Action Center -->
                    <div class="card border-0 rounded-4 shadow-sm p-4 mb-4" style="background: var(--bhw-text); color: white;">
                        <h6 class="fw-bold mb-3 d-flex align-items-center gap-2">
                            <i data-lucide="zap" style="width: 18px;"></i> Action Center
                        </h6>
                        <div class="d-grid gap-2">
                            <button class="btn btn-light rounded-pill text-start py-2 px-3 fw-bold small d-flex align-items-center justify-content-between" data-bs-toggle="modal" data-bs-target="#registerHealerModal">
                                Register New Local Healer <i data-lucide="user-plus" style="width: 14px;"></i>
                            </button>
                            <button class="btn btn-light rounded-pill text-start py-2 px-3 fw-bold small d-flex align-items-center justify-content-between" data-bs-toggle="modal" data-bs-target="#postAlertModal">
                                Post Barangay Alert <i data-lucide="megaphone" style="width: 14px;"></i>
                            </button>
                            <button class="btn btn-light rounded-pill text-start py-2 px-3 fw-bold small d-flex align-items-center justify-content-between" onclick="window.location.href='bhw-plants.php'">
                                Audit Local Plants <i data-lucide="clipboard-list" style="width: 14px;"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Module C: Local Health Alerts -->
                    <div class="card border-0 rounded-4 shadow-sm p-4">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h6 class="fw-bold text-dark mb-0">Module C: Barangay Health Alerts</h6>
                            <a href="bhw-alerts.php" class="text-primary small fw-bold text-decoration-none">Manage</a>
                        </div>
                        <?php if (empty($localAlertList)): ?>
                            <div class="text-center py-3 text-muted small">No active alerts posted.</div>
                        <?php else: ?>
                            <?php foreach ($localAlertList as $alert): ?>
                            <div class="p-3 mb-2 rounded-3 alert-item shadow-sm">
                                <div class="fw-bold small text-dark"><?= htmlspecialchars($alert['title']) ?></div>
                                <div class="text-muted" style="font-size: 0.75rem;"><?= htmlspecialchars($alert['content']) ?></div>
                                <div class="text-end mt-1" style="font-size: 0.65rem; color: #999;">
                                    <?= date('M d, H:i', strtotime($alert['created_at'])) ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        <button class="btn btn-link text-primary fw-bold small mt-2 p-0 text-decoration-none" data-bs-toggle="modal" data-bs-target="#postAlertModal">+ Create New Alert</button>
                    </div>
                </div>

                <!-- Module B: Botanical Inventory -->
                <div class="col-12 mt-2">
                    <div class="card border-0 rounded-4 shadow-sm p-4">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h6 class="fw-bold text-dark mb-0">Module B: Local Botanical Inventory</h6>
                            <a href="bhw-plants.php" class="text-primary small fw-bold text-decoration-none">View Full Inventory</a>
                        </div>
                        <div class="row row-cols-1 row-cols-md-5 g-3">
                            <?php if (empty($localPlantList)): ?>
                                <div class="col-12 text-center py-4 text-muted">No local plants reported yet.</div>
                            <?php else: ?>
                                <?php foreach ($localPlantList as $p): ?>
                                <div class="col">
                                    <div class="text-center p-3 rounded-4" style="background: #f8f9fa; border: 1px solid #eee;">
                                        <div class="mb-2 mx-auto" style="width: 50px; height: 50px; background: white; border-radius: 12px; display: flex; align-items: center; justify-content: center; overflow: hidden;">
                                            <?php if ($p['plant_image']): ?>
                                                <img src="<?= htmlspecialchars($p['plant_image']) ?>" style="width: 100%; height: 100%; object-fit: cover;">
                                            <?php else: ?>
                                                <i data-lucide="leaf" class="text-success" style="width: 20px;"></i>
                                            <?php endif; ?>
                                        </div>
                                        <div class="fw-bold text-truncate" style="font-size: 0.8rem;"><?= htmlspecialchars($p['plant_name']) ?></div>
                                        <small class="text-muted text-truncate d-block" style="font-size: 0.7rem;"><?= htmlspecialchars($p['illness_treated']) ?></small>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Healer Registration Modal (Adapted from Admin) -->
<div class="modal fade" id="registerHealerModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content rounded-5 border-0 shadow-lg p-3">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold text-primary display-6 ms-3">Register Professional Healer</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <form action="bhw-healers.php" method="POST" id="healerRegForm" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="add">
                    <input type="hidden" name="captured_image" id="capturedImage">
                    <div class="row g-4">
                        <div class="col-md-5">
                            <div class="mb-3">
                                <label class="form-label small fw-bold text-muted text-uppercase">Identification</label>
                                <input type="text" name="full_name" class="form-control" placeholder="Full Name" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-bold text-muted text-uppercase">Primary Specialization</label>
                                <input type="text" name="specialization" class="form-control" placeholder="e.g. Traditional Pulse Diagnosis" required>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label small fw-bold text-muted text-uppercase">Years of Treating</label>
                                    <input type="number" name="years_of_experience" class="form-control" placeholder="0" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label small fw-bold text-muted text-uppercase">Contact Number</label>
                                    <input type="text" name="contact_info" class="form-control" placeholder="+XX-XXX-XXX" required>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-bold text-muted text-uppercase">Treatment Methods</label>
                                <textarea name="treatment_methods" class="form-control" rows="2" placeholder="Therapeutic techniques..."></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-bold text-muted text-uppercase">Herbs / Plants Utilized</label>
                                <textarea name="herbs_used" class="form-control" rows="2" placeholder="Key botanical components..."></textarea>
                            </div>
                        </div>

                        <div class="col-md-7">
                            <div class="mb-3">
                                <label class="form-label small fw-bold text-muted text-uppercase">Practice Location Name</label>
                                <div class="input-group">
                                    <input type="text" name="location_name" id="locName" class="form-control" placeholder="Clinic or house address" required>
                                    <button class="btn btn-outline-primary" type="button" id="searchLocBtnDashboard">Locate</button>
                                </div>
                            </div>
                            <div id="healerMapDashboard" style="height: 300px; border-radius: 15px; border: 2px solid var(--bhw-accent); margin-bottom: 20px;"></div>
                            <div class="row">
                                <div class="col-6">
                                    <input type="text" name="latitude" id="latDash" class="form-control form-control-sm bg-light" placeholder="Latitude" readonly required>
                                </div>
                                <div class="col-6">
                                    <input type="text" name="longitude" id="lngDash" class="form-control form-control-sm bg-light" placeholder="Longitude" readonly required>
                                </div>
                            </div>
                            <div class="mt-4">
                                <label class="form-label small fw-bold text-muted text-uppercase">Biography / Background</label>
                                <textarea name="description" class="form-control" rows="3" placeholder="Brief history..."></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="text-end mt-4">
                        <button type="button" class="btn btn-light rounded-pill px-4 me-2" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary rounded-pill px-5 shadow-sm fw-bold">Save & Profile Healer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
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
                        <input type="text" name="title" class="form-control rounded-3" placeholder="e.g. Free Herbal Clinic" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">Content</label>
                        <textarea name="content" class="form-control rounded-3" rows="3" placeholder="Provide details about the announcement..." required></textarea>
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
                            <input type="datetime-local" name="expires_at" class="form-control rounded-3">
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-5 fw-bold">Post to Map</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    lucide.createIcons();

    // Map logic for Dashboard Registration Modal
    let dashMap = L.map('healerMapDashboard').setView([11.2407, 124.9961], 13);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(dashMap);
    let dashMarker;

    dashMap.on('click', function(e) {
        if (dashMarker) dashMap.removeLayer(dashMarker);
        dashMarker = L.marker(e.latlng).addTo(dashMap);
        document.getElementById('latDash').value = e.latlng.lat;
        document.getElementById('lngDash').value = e.latlng.lng;
    });

    document.getElementById('registerHealerModal').addEventListener('shown.bs.modal', function () {
        dashMap.invalidateSize();
    });

    async function searchLocationDash() {
        const query = document.getElementById('locName').value;
        if (!query) return;
        try {
            const res = await fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(query + " " + "<?= $barangay ?>")}`);
            const data = await res.json();
            if (data.length > 0) {
                const { lat, lon } = data[0];
                dashMap.setView([lat, lon], 16);
                if (dashMarker) dashMap.removeLayer(dashMarker);
                dashMarker = L.marker([lat, lon]).addTo(dashMap);
                document.getElementById('latDash').value = lat;
                document.getElementById('lngDash').value = lon;
            }
        } catch (e) {}
    }
    document.getElementById('searchLocBtnDashboard').addEventListener('click', searchLocationDash);

    <?php if ($success): ?>
        Swal.fire({ icon: 'success', title: 'Action Complete', text: '<?= addslashes($success) ?>', confirmButtonColor: '#1565C0' });
    <?php endif; ?>
    <?php if ($error): ?>
        Swal.fire({ icon: 'error', title: 'Error', text: '<?= addslashes($error) ?>', confirmButtonColor: '#1565C0' });
    <?php endif; ?>
</script>
</body>
</html>

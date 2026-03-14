<?php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$role     = $_SESSION['role'];
$username = $_SESSION['username'];
$user_id  = $_SESSION['user_id'];

// ── Ensure the audit_logs table exists ───────────────────────────────────────
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS audit_logs (
            id            INT AUTO_INCREMENT PRIMARY KEY,
            user_id       INT          DEFAULT NULL,
            username      VARCHAR(100) DEFAULT 'System',
            user_role     VARCHAR(50)  DEFAULT 'unknown',
            action        VARCHAR(255) NOT NULL,
            detail        TEXT         DEFAULT NULL,
            ip_address    VARCHAR(64)  DEFAULT NULL,
            user_agent    TEXT         DEFAULT NULL,
            status        ENUM('success','warning','failed') DEFAULT 'success',
            created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_created (created_at),
            INDEX idx_user   (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
} catch (PDOException $e) { /* Already exists */ }

// ── Super-Admin decrypt password check ──────────────────────────────────────
// In production, use a real env variable. This is a demo constant.
define('DECRYPT_PASS', 'HealingRoot@2026');

$decryptMode = false;
if (isset($_POST['decrypt_pass'])) {
    $decryptMode = ($_POST['decrypt_pass'] === DECRYPT_PASS);
    if (!$decryptMode) {
        $decryptError = 'Incorrect decryption password.';
    }
}

// ── Filter Logic ─────────────────────────────────────────────────────────────
$filterRole   = $_GET['role']   ?? '';
$filterStatus = $_GET['status'] ?? '';
$filterSearch = $_GET['search'] ?? '';
$page         = max(1, (int)($_GET['page'] ?? 1));
$perPage      = 20;
$offset       = ($page - 1) * $perPage;

$conditions = [];
$params     = [];

if ($filterRole) {
    $conditions[] = "user_role = ?";
    $params[]     = $filterRole;
}
if ($filterStatus) {
    $conditions[] = "status = ?";
    $params[]     = $filterStatus;
}
if ($filterSearch) {
    $conditions[] = "(username LIKE ? OR action LIKE ? OR ip_address LIKE ?)";
    $params = array_merge($params, ["%$filterSearch%", "%$filterSearch%", "%$filterSearch%"]);
}

$where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

// ── Fetch total + paginated logs ──────────────────────────────────────────────
try {
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM audit_logs $where");
    $countStmt->execute($params);
    $totalLogs = $countStmt->fetchColumn();

    $logsStmt = $pdo->prepare("SELECT * FROM audit_logs $where ORDER BY created_at DESC LIMIT $perPage OFFSET $offset");
    $logsStmt->execute($params);
    $logs = $logsStmt->fetchAll(PDO::FETCH_ASSOC);

    // Stats for header bar
    $statsStmt = $pdo->query("
        SELECT
            COUNT(*) AS total,
            SUM(status = 'success') AS successes,
            SUM(status = 'warning') AS warnings,
            SUM(status = 'failed')  AS failures,
            COUNT(DISTINCT user_id) AS unique_users
        FROM audit_logs
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    ");
    $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $logs = [];
    $totalLogs = 0;
    $stats = ['total' => 0, 'successes' => 0, 'warnings' => 0, 'failures' => 0, 'unique_users' => 0];
}

$totalPages = max(1, ceil($totalLogs / $perPage));

// ── Helpers ──────────────────────────────────────────────────────────────────
function roleBadge(string $role): string {
    return match($role) {
        'admin'  => '<span class="badge rounded-pill fw-bold" style="background:rgba(45,79,50,0.10);color:#2D4F32;font-size:0.65rem;padding:4px 10px;">Admin</span>',
        'bhw'    => '<span class="badge rounded-pill fw-bold" style="background:rgba(21,101,192,0.10);color:#1565C0;font-size:0.65rem;padding:4px 10px;">BHW</span>',
        'healer' => '<span class="badge rounded-pill fw-bold" style="background:rgba(5,150,105,0.10);color:#059669;font-size:0.65rem;padding:4px 10px;">Healer</span>',
        'user'   => '<span class="badge rounded-pill fw-bold" style="background:rgba(107,114,128,0.10);color:#6B7280;font-size:0.65rem;padding:4px 10px;">User</span>',
        default  => '<span class="badge rounded-pill fw-bold" style="background:rgba(124,58,237,0.10);color:#7C3AED;font-size:0.65rem;padding:4px 10px;">'.htmlspecialchars($role).'</span>',
    };
}

function statusBadge(string $status): string {
    return match($status) {
        'success' => '<span class="d-flex align-items-center gap-1" style="color:#059669;font-size:0.78rem;font-weight:700;"><span style="width:7px;height:7px;border-radius:50%;background:#059669;display:inline-block;"></span> Success</span>',
        'warning' => '<span class="d-flex align-items-center gap-1" style="color:#D97706;font-size:0.78rem;font-weight:700;"><span style="width:7px;height:7px;border-radius:50%;background:#D97706;display:inline-block;"></span> Warning</span>',
        'failed'  => '<span class="d-flex align-items-center gap-1" style="color:#DC2626;font-size:0.78rem;font-weight:700;"><span style="width:7px;height:7px;border-radius:50%;background:#DC2626;display:inline-block;"></span> Failed</span>',
        default   => '<span class="text-muted small">—</span>',
    };
}

function maskDetail(string $detail, bool $decrypt): string {
    if ($decrypt) return '<span class="fw-semibold" style="color:#7C3AED;font-size:0.8rem;">' . htmlspecialchars($detail) . '</span>';
    return '<span class="font-monospace text-muted" style="font-size:0.78rem;letter-spacing:2px;">●●●●●●●●●●</span>
            <span class="badge ms-2" style="background:rgba(124,58,237,0.08);color:#7C3AED;font-size:0.62rem;cursor:pointer;" onclick="showDecryptModal()" title="Decrypt">🔒 Encrypted</span>';
}

function formatAgent(string $ua): string {
    if (str_contains($ua, 'Mobile') || str_contains($ua, 'Android') || str_contains($ua, 'iPhone')) return '📱 Mobile';
    if (str_contains($ua, 'Chrome'))  return '🌐 Chrome';
    if (str_contains($ua, 'Firefox')) return '🦊 Firefox';
    if (str_contains($ua, 'Safari'))  return '🍎 Safari';
    if (str_contains($ua, 'Edge'))    return '🔵 Edge';
    return '💻 Browser';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Security Audit Logs | Healing Compass Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Lexend:wght@300;400;500;600;700;800&family=Outfit:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <script src="https://unpkg.com/lucide@latest"></script>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .section-card {
            background: white;
            border-radius: 1.75rem;
            padding: 1.75rem 2rem;
            border: 1px solid rgba(0,0,0,0.03);
            box-shadow: 0 4px 20px rgba(45,79,50,0.04);
        }

        .stat-pill {
            background: white;
            border-radius: 1.25rem;
            padding: 1rem 1.5rem;
            border: 1px solid rgba(0,0,0,0.04);
            box-shadow: 0 4px 15px rgba(45,79,50,0.04);
        }

        .audit-row td { vertical-align: middle; padding: 0.85rem 1rem; font-size: 0.83rem; }
        .audit-row:hover { background: #FAFCF8; }

        .sage-input {
            background: #F7FAF5 !important;
            border: 2px solid #E8EDE0 !important;
            border-radius: 0.75rem !important;
            font-size: 0.85rem !important;
            color: var(--nature-forest) !important;
            padding: 9px 14px !important;
        }
        .sage-input:focus { box-shadow: none !important; border-color: var(--nature-forest) !important; }

        .filter-select {
            background: white !important;
            border: 2px solid #E8EDE0 !important;
            border-radius: 0.75rem !important;
            font-size: 0.82rem !important;
            color: var(--nature-forest) !important;
            padding: 9px 14px !important;
            min-width: 140px;
        }
        .filter-select:focus { box-shadow: none !important; border-color: var(--nature-forest) !important; }

        .action-icon {
            width: 34px; height: 34px; border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
        }
        .modal-content { border-radius: 2rem; border: none; }

        .page-link { border-radius: 0.75rem !important; border: none !important; color: var(--nature-forest); font-size: 0.82rem; margin: 0 2px; }
        .page-link:hover, .page-item.active .page-link { background: var(--nature-forest); color: white; }
        .page-item.active .page-link { background: var(--nature-forest); }
    </style>
</head>
<body class="dashboard-app" style="background-color: var(--nature-bg);">

<div class="container-fluid p-0">
    <div class="row g-0">

        <!-- Sidebar -->
        <nav class="col-md-3 col-lg-2 sidebar p-4">
            <div class="d-flex align-items-center mb-5 ps-2">
                <img src="assets/img/logo.png" alt="Logo" style="height: 45px;" class="me-3">
                <h6 class="fw-bold text-success mb-0" style="letter-spacing: -0.5px; line-height: 1.1;">ADMIN<br>PANEL</h6>
            </div>
            <div class="nav-menu">
                <a href="admin-dashboard.php" class="sidebar-link"><i data-lucide="bar-chart-3"></i> Dashboard</a>
                <a href="admin-management.php" class="sidebar-link"><i data-lucide="user-cog"></i> User Management</a>
                <a href="admin-healers.php" class="sidebar-link"><i data-lucide="users"></i> Manage Healers</a>
                <a href="admin-plants.php" class="sidebar-link"><i data-lucide="sprout"></i> Manage Plants</a>
                <a href="admin-logs.php" class="sidebar-link active"><i data-lucide="shield"></i> Audit Logs</a>
                <a href="dashboard.php" class="sidebar-link"><i data-lucide="layout-grid"></i> User View</a>
            </div>
            <div style="position: absolute; bottom: 30px; left: 20px; right: 20px;">
                <a href="controllers/AuthController.php?action=logout" class="sidebar-link text-danger">
                    <i data-lucide="log-out"></i> Logout
                </a>
            </div>
        </nav>

        <!-- Main Content -->
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-5 py-5 min-vh-100">

            <!-- Header -->
            <div class="d-flex justify-content-between align-items-start mb-5">
                <div>
                    <h1 class="fw-bold mb-1" style="font-size: 2.4rem; letter-spacing: -1.5px; color: var(--nature-forest);">Security Audit Logs</h1>
                    <p class="text-muted fw-medium mb-0">Role-based event tracking with privacy controls — last 30 days</p>
                </div>
                <div class="d-flex gap-2">
                    <button onclick="showDecryptModal()" class="btn rounded-pill px-4 fw-semibold" style="background: rgba(124,58,237,0.08); color: #7C3AED; border: 2px solid rgba(124,58,237,0.2); font-size: 0.85rem;">
                        <i data-lucide="key-round" class="me-2" style="width: 15px;"></i>
                        <?= $decryptMode ? '🔓 Decrypted View' : '🔒 Decrypt Logs' ?>
                    </button>
                    <a href="admin-logs.php" class="btn rounded-pill px-4 fw-semibold" style="background: #F7FAF5; border: 2px solid #E8EDE0; color: var(--nature-forest); font-size: 0.85rem;">
                        <i data-lucide="refresh-cw" class="me-2" style="width: 15px;"></i>Refresh
                    </a>
                </div>
            </div>

            <!-- Stats Strip -->
            <div class="row g-3 mb-5">
                <?php
                $strips = [
                    ['label' => 'Total Events',    'value' => number_format($stats['total']),        'color'=>'#2D4F32', 'bg'=>'rgba(45,79,50,0.08)',   'icon'=>'activity'],
                    ['label' => 'Successful',      'value' => number_format($stats['successes']),    'color'=>'#059669', 'bg'=>'rgba(5,150,105,0.08)',  'icon'=>'check-circle'],
                    ['label' => 'Warnings',        'value' => number_format($stats['warnings']),     'color'=>'#D97706', 'bg'=>'rgba(217,119,6,0.08)',  'icon'=>'alert-triangle'],
                    ['label' => 'Failed Events',   'value' => number_format($stats['failures']),     'color'=>'#DC2626', 'bg'=>'rgba(220,38,38,0.08)',  'icon'=>'x-circle'],
                    ['label' => 'Active Users',    'value' => $stats['unique_users'],                'color'=>'#7C3AED', 'bg'=>'rgba(124,58,237,0.08)','icon'=>'users'],
                ];
                foreach ($strips as $s): ?>
                <div class="col">
                    <div class="stat-pill d-flex align-items-center gap-3">
                        <div style="width:40px;height:40px;border-radius:12px;background:<?= $s['bg'] ?>;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                            <i data-lucide="<?= $s['icon'] ?>" style="width:18px;color:<?= $s['color'] ?>;"></i>
                        </div>
                        <div>
                            <div class="fw-bold" style="font-size:1.3rem;color:<?= $s['color'] ?>;"><?= $s['value'] ?></div>
                            <small class="text-muted" style="font-size:0.72rem;"><?= $s['label'] ?></small>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Decrypt Banner -->
            <?php if ($decryptMode): ?>
            <div class="alert border-0 rounded-4 mb-4 d-flex align-items-center" style="background:rgba(124,58,237,0.08);color:#7C3AED;">
                <i data-lucide="unlock" class="me-3 flex-shrink-0" style="width:20px;"></i>
                <strong>Decrypted View Active.</strong>&nbsp;Sensitive log details are now visible. This session will remain unlocked until you navigate away.
            </div>
            <?php endif; ?>

            <!-- Table Card -->
            <div class="section-card">

                <!-- Filters -->
                <div class="d-flex flex-wrap gap-3 align-items-center mb-4">
                    <form method="GET" class="d-flex flex-wrap gap-2 align-items-center flex-grow-1" id="filterForm">
                        <!-- Search -->
                        <div class="position-relative flex-grow-1" style="min-width: 200px;">
                            <i data-lucide="search" class="position-absolute text-muted" style="width:15px;top:50%;left:14px;transform:translateY(-50%);pointer-events:none;"></i>
                            <input type="text" name="search" value="<?= htmlspecialchars($filterSearch) ?>"
                                   placeholder="Search user, action, IP…"
                                   class="sage-input form-control ps-5" style="padding-left: 38px !important;">
                        </div>
                        <!-- Role Filter -->
                        <select name="role" class="filter-select form-select" onchange="this.form.submit()">
                            <option value="">All Roles</option>
                            <option value="admin"  <?= $filterRole === 'admin'  ? 'selected' : '' ?>>Admin</option>
                            <option value="bhw"    <?= $filterRole === 'bhw'    ? 'selected' : '' ?>>BHW</option>
                            <option value="healer" <?= $filterRole === 'healer' ? 'selected' : '' ?>>Healer</option>
                            <option value="user"   <?= $filterRole === 'user'   ? 'selected' : '' ?>>User</option>
                        </select>
                        <!-- Status Filter -->
                        <select name="status" class="filter-select form-select" onchange="this.form.submit()">
                            <option value="">All Statuses</option>
                            <option value="success" <?= $filterStatus === 'success' ? 'selected' : '' ?>>Success</option>
                            <option value="warning" <?= $filterStatus === 'warning' ? 'selected' : '' ?>>Warning</option>
                            <option value="failed"  <?= $filterStatus === 'failed'  ? 'selected' : '' ?>>Failed</option>
                        </select>
                        <button type="submit" class="btn rounded-pill fw-semibold px-4" style="background:var(--nature-forest);color:white;border:none;font-size:0.82rem;height:42px;">Filter</button>
                        <a href="admin-logs.php<?= $decryptMode ? '?decrypted=1' : '' ?>" class="btn rounded-pill fw-semibold px-3" style="background:#F7FAF5;border:2px solid #E8EDE0;color:var(--nature-forest);font-size:0.82rem;height:42px;display:flex;align-items:center;">Clear</a>
                    </form>
                    <small class="text-muted flex-shrink-0"><?= number_format($totalLogs) ?> events found</small>
                </div>

                <!-- Table -->
                <div class="table-responsive">
                    <table class="table table-borderless mb-0">
                        <thead>
                            <tr style="border-bottom: 2px solid #F1F3E9;">
                                <th style="font-size:0.68rem;text-transform:uppercase;letter-spacing:1px;color:#9CA3AF;font-weight:700;padding:0 1rem 1rem;">Timestamp</th>
                                <th style="font-size:0.68rem;text-transform:uppercase;letter-spacing:1px;color:#9CA3AF;font-weight:700;">Account</th>
                                <th style="font-size:0.68rem;text-transform:uppercase;letter-spacing:1px;color:#9CA3AF;font-weight:700;">Role</th>
                                <th style="font-size:0.68rem;text-transform:uppercase;letter-spacing:1px;color:#9CA3AF;font-weight:700;">Action Performed</th>
                                <th style="font-size:0.68rem;text-transform:uppercase;letter-spacing:1px;color:#9CA3AF;font-weight:700;">Detail / Data</th>
                                <th style="font-size:0.68rem;text-transform:uppercase;letter-spacing:1px;color:#9CA3AF;font-weight:700;">Device & IP</th>
                                <th style="font-size:0.68rem;text-transform:uppercase;letter-spacing:1px;color:#9CA3AF;font-weight:700;">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($logs)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-5">
                                    <div style="opacity:0.35;">
                                        <i data-lucide="shield-off" style="width:48px;height:48px;color:var(--nature-forest);margin-bottom:12px;"></i>
                                        <p class="fw-semibold text-muted mb-0">No audit events recorded yet.</p>
                                        <small class="text-muted">Events will appear here as admins and BHWs take actions.</small>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($logs as $log): ?>
                            <tr class="audit-row" style="border-bottom: 1px solid rgba(0,0,0,0.035);">
                                <!-- Timestamp -->
                                <td style="color:#6B7280; white-space: nowrap;">
                                    <div style="font-size:0.8rem;"><?= date('M d, Y', strtotime($log['created_at'])) ?></div>
                                    <div style="font-size:0.72rem;color:#9CA3AF;"><?= date('H:i:s', strtotime($log['created_at'])) ?></div>
                                </td>
                                <!-- Account -->
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="rounded-circle d-flex align-items-center justify-content-center fw-bold flex-shrink-0"
                                             style="width:32px;height:32px;background:rgba(45,79,50,0.08);color:var(--nature-forest);font-size:0.75rem;">
                                            <?= strtoupper(substr($log['username'] ?? 'S', 0, 1)) ?>
                                        </div>
                                        <span class="fw-semibold" style="color:var(--nature-forest);font-size:0.82rem;"><?= htmlspecialchars($log['username'] ?? 'System') ?></span>
                                    </div>
                                </td>
                                <!-- Role -->
                                <td><?= roleBadge($log['user_role'] ?? 'unknown') ?></td>
                                <!-- Action -->
                                <td>
                                    <div class="fw-semibold" style="color:var(--nature-forest);font-size:0.83rem;">
                                        <?= htmlspecialchars($log['action']) ?>
                                    </div>
                                </td>
                                <!-- Detail (encrypted or revealed) -->
                                <td style="max-width: 200px;">
                                    <?php if (!empty($log['detail'])): ?>
                                        <?= maskDetail($log['detail'], $decryptMode) ?>
                                    <?php else: ?>
                                        <span class="text-muted" style="font-size:0.78rem;">—</span>
                                    <?php endif; ?>
                                </td>
                                <!-- Device & IP -->
                                <td>
                                    <?php if ($log['ip_address']): ?>
                                        <div class="fw-semibold" style="font-size:0.78rem;color:var(--nature-forest);">
                                            <?= htmlspecialchars($log['ip_address']) ?>
                                        </div>
                                        <div class="text-muted mt-1" style="font-size:0.72rem;">
                                            <?= $log['user_agent'] ? formatAgent($log['user_agent']) : '—' ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-muted" style="font-size:0.78rem;">—</span>
                                    <?php endif; ?>
                                </td>
                                <!-- Status -->
                                <td><?= statusBadge($log['status']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                <div class="d-flex justify-content-between align-items-center mt-4 pt-3" style="border-top: 1px solid #F1F3E9;">
                    <small class="text-muted">Page <?= $page ?> of <?= $totalPages ?></small>
                    <nav>
                        <ul class="pagination mb-0 gap-1">
                            <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                            <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                                <a class="page-link px-3 py-2" href="?page=<?= $p ?>&role=<?= urlencode($filterRole) ?>&status=<?= urlencode($filterStatus) ?>&search=<?= urlencode($filterSearch) ?>">
                                    <?= $p ?>
                                </a>
                            </li>
                            <?php endfor; ?>
                        </ul>
                    </nav>
                </div>
                <?php endif; ?>
            </div>

        </main>
    </div>
</div>

<!-- ── Decrypt Modal ──────────────────────────────────────────────────────── -->
<div class="modal fade" id="decryptModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 2rem;">
            <div class="modal-header border-0 pb-0">
                <div>
                    <h5 class="fw-bold mb-0" style="color:#7C3AED;">🔐 Super-Admin Decrypt</h5>
                    <small class="text-muted">Enter the Root Admin passphrase to reveal encrypted log details.</small>
                </div>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body pb-0">
                <form method="POST" id="decryptForm">
                    <?php if (isset($decryptError)): ?>
                    <div class="alert border-0 rounded-3 mb-3" style="background:rgba(220,38,38,0.07);color:#DC2626;font-size:0.85rem;">
                        <i data-lucide="alert-circle" class="me-2" style="width:15px;"></i> <?= htmlspecialchars($decryptError) ?>
                    </div>
                    <?php endif; ?>
                    <label class="form-label" style="font-size:0.72rem;font-weight:700;text-transform:uppercase;letter-spacing:0.8px;color:var(--nature-forest);opacity:0.65;">Root Passphrase</label>
                    <input type="password" name="decrypt_pass" class="sage-input form-control"
                           placeholder="Enter decryption password…" required autocomplete="off">
                    <p class="text-muted mt-2 mb-0" style="font-size:0.72rem;">
                        This reveals masked event details for this session only. All decryption attempts are logged.
                    </p>
                </form>
            </div>
            <div class="modal-footer border-0 gap-2">
                <button type="button" class="btn rounded-pill px-4 fw-semibold" data-bs-dismiss="modal"
                        style="background:#F7FAF5;border:2px solid #E8EDE0;color:var(--nature-forest);">Cancel</button>
                <button type="submit" form="decryptForm" class="btn rounded-pill px-5 fw-bold"
                        style="background:#7C3AED;color:white;border:none;">
                    <i data-lucide="key-round" class="me-2" style="width:16px;"></i> Unlock View
                </button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    lucide.createIcons();

    function showDecryptModal() {
        new bootstrap.Modal(document.getElementById('decryptModal')).show();
    }

    <?php if (isset($decryptError)): ?>
    // Re-open on failed decrypt
    window.addEventListener('DOMContentLoaded', () => showDecryptModal());
    <?php endif; ?>
</script>
</body>
</html>

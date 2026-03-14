<?php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$role     = $_SESSION['role'];
$username = $_SESSION['username'];

// ── Fetch Core Stats ──────────────────────────────────────────────────────────
try {
    $userCount      = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $plantCount     = $pdo->query("SELECT COUNT(*) FROM plants")->fetchColumn();
    $pendingPlants  = $pdo->query("SELECT COUNT(*) FROM plants WHERE is_approved = 0")->fetchColumn();
    $healerCount    = $pdo->query("SELECT COUNT(*) FROM healers")->fetchColumn();
    $appointCount   = $pdo->query("SELECT COUNT(*) FROM appointments")->fetchColumn();
    $pendingAppointCount = $pdo->query("SELECT COUNT(*) FROM appointments WHERE status = 'pending'")->fetchColumn();
    $aiLogsCount    = $pdo->query("SELECT COUNT(*) FROM ai_logs")->fetchColumn();

    // Role distribution
    $roleDist = $pdo->query("SELECT role, COUNT(*) as cnt FROM users GROUP BY role")->fetchAll(PDO::FETCH_KEY_PAIR);
    $adminCount  = $roleDist['admin']  ?? 0;
    $healerRoleCount = $roleDist['healer'] ?? 0;
    $userRoleCount   = $roleDist['user']   ?? 0;

    // User list for role management table
    $userList = $pdo->query("SELECT id, username, email, role, created_at FROM users ORDER BY created_at DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);

    // Recent AI logs with user role info
    $recentLogs = $pdo->query("
        SELECT al.action_type, al.query, al.timestamp, u.username, u.role
        FROM ai_logs al
        LEFT JOIN users u ON al.user_id = u.id
        ORDER BY al.timestamp DESC LIMIT 6
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Recent appointments
    $recentAppointments = $pdo->query("
        SELECT a.appointment_date, a.status, u.username, h.full_name, h.specialization
        FROM appointments a
        JOIN users u ON a.user_id = u.id
        JOIN healers h ON a.healer_id = h.id
        ORDER BY a.created_at DESC LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $userCount = $plantCount = $pendingPlants = $aiLogsCount = $healerCount = $appointCount = 0;
    $adminCount = $healerRoleCount = $userRoleCount = 0;
    $userList = $recentLogs = $recentAppointments = [];
}

$roleBadge = [
    'admin'  => ['label' => 'Admin',  'color' => '#2D4F32', 'bg' => 'rgba(45,79,50,0.1)'],
    'healer' => ['label' => 'Healer', 'color' => '#1565C0', 'bg' => 'rgba(21,101,192,0.1)'],
    'user'   => ['label' => 'User',   'color' => '#7B3F00', 'bg' => 'rgba(123,63,0,0.1)'],
];

$permissions = [
    'admin'  => ['Approve Plants', 'Manage Healers', 'View Audit Logs', 'System Config', 'Manage Users'],
    'healer' => ['Update Own Profile', 'View Bookings', 'Respond to Consultations'],
    'user'   => ['Book Appointments', 'Submit Plant Tips', 'Use AI Assistant'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | Healing Compass</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Lexend:wght@300;400;500;600;700;800&family=Outfit:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .stat-card {
            background: white;
            border-radius: 1.75rem;
            padding: 1.5rem;
            border: 1px solid rgba(0,0,0,0.03);
            box-shadow: 0 4px 20px rgba(45,79,50,0.04);
            transition: all 0.3s ease;
        }
        .stat-card:hover { transform: translateY(-5px); box-shadow: 0 15px 35px rgba(45,79,50,0.08); }

        .stat-icon {
            width: 48px; height: 48px;
            border-radius: 14px;
            display: flex; align-items: center; justify-content: center;
        }

        .section-card {
            background: white;
            border-radius: 1.75rem;
            padding: 1.75rem;
            border: 1px solid rgba(0,0,0,0.03);
            box-shadow: 0 4px 20px rgba(45,79,50,0.04);
        }

        .permission-pill {
            display: inline-block;
            font-size: 0.7rem;
            font-weight: 600;
            padding: 4px 12px;
            border-radius: 100px;
            margin: 3px 3px 3px 0;
        }

        .role-row td { vertical-align: middle; padding: 0.85rem 1rem; }

        .action-card {
            background: #F7FAF5;
            border: 2px solid #E8EDE0;
            border-radius: 1.5rem;
            padding: 1.25rem;
            text-align: center;
            text-decoration: none;
            display: block;
            transition: all 0.25s ease;
            color: var(--nature-forest);
        }
        .action-card:hover {
            background: var(--nature-forest);
            color: white;
            border-color: var(--nature-forest);
            transform: translateY(-4px);
            box-shadow: 0 10px 25px rgba(45,79,50,0.15);
        }
        .action-card:hover .action-icon { color: white !important; }

        .log-dot {
            width: 8px; height: 8px; border-radius: 50%;
            display: inline-block; margin-right: 8px; flex-shrink: 0;
        }

        .chart-wrap { position: relative; height: 180px; }
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
                <a href="admin-dashboard.php" class="sidebar-link active">
                    <i data-lucide="bar-chart-3"></i> Dashboard
                </a>
                <a href="admin-management.php" class="sidebar-link">
                    <i data-lucide="user-cog"></i> User Management
                </a>
                <a href="admin-healers.php" class="sidebar-link">
                    <i data-lucide="users"></i> Manage Healers
                </a>
                <a href="admin-plants.php" class="sidebar-link">
                    <i data-lucide="sprout"></i> Manage Plants
                </a>
                <a href="admin-logs.php" class="sidebar-link">
                    <i data-lucide="file-text"></i> Audit Logs
                </a>
                <a href="dashboard.php" class="sidebar-link">
                    <i data-lucide="layout-grid"></i> User View
                </a>
            </div>

            <div class="mt-auto" style="position: absolute; bottom: 30px; left: 20px; right: 20px;">
                <a href="controllers/AuthController.php?action=logout" class="sidebar-link text-danger">
                    <i data-lucide="log-out"></i> Logout
                </a>
            </div>
        </nav>

        <!-- Main -->
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-5 py-5 min-vh-100">

            <!-- Header -->
            <div class="d-flex justify-content-between align-items-start mb-5">
                <div>
                    <h1 class="fw-bold mb-1" style="font-size: 2.4rem; letter-spacing: -1.5px; color: var(--nature-forest);">Administrative Control</h1>
                    <p class="text-muted fw-medium mb-0">Welcome back, <strong><?= htmlspecialchars($username) ?></strong>. Here's your platform overview.</p>
                </div>
                <div class="d-flex gap-2 align-items-center">
                    <span class="badge rounded-pill px-3 py-2 fw-bold" style="background: rgba(45,79,50,0.1); color: var(--nature-forest); font-size: 0.75rem;">
                        <i data-lucide="shield-check" style="width: 12px;"></i> Admin Access
                    </span>
                </div>
            </div>

            <!-- ── Section 1: Stats Row ─────────────────────────────────────── -->
            <div class="row g-3 mb-5">
                <?php
                $stats = [
                    ['label' => 'Total Users',       'value' => $userCount,     'icon' => 'users',        'color' => '#2D4F32', 'bg' => 'rgba(45,79,50,0.08)'],
                    ['label' => 'Record Plants',     'value' => $plantCount,    'icon' => 'leaf',         'color' => '#1B6CA8', 'bg' => 'rgba(27,108,168,0.08)'],
                    ['label' => 'Pending Plants',    'value' => $pendingPlants, 'icon' => 'clock',        'color' => '#D97706', 'bg' => 'rgba(217,119,6,0.08)'],
                    ['label' => 'Active Healers',    'value' => $healerCount,   'icon' => 'stethoscope',  'color' => '#059669', 'bg' => 'rgba(5,150,105,0.08)'],
                    ['label' => 'Total Bookings',    'value' => $appointCount,  'icon' => 'calendar',     'color' => '#7C3AED', 'bg' => 'rgba(124,58,237,0.08)'],
                    ['label' => 'Pending Appts',     'value' => $pendingAppointCount, 'icon' => 'calendar-days','color' => '#DC2626', 'bg' => 'rgba(220,53,69,0.08)'],
                ];
                foreach ($stats as $s):
                ?>
                <div class="col-md-2 col-6">
                    <div class="stat-card h-100">
                        <div class="stat-icon mb-3" style="background: <?= $s['bg'] ?>;">
                            <i data-lucide="<?= $s['icon'] ?>" style="width: 22px; color: <?= $s['color'] ?>;"></i>
                        </div>
                        <h3 class="fw-bold mb-0" style="font-size: 1.8rem; color: <?= $s['color'] ?>;"><?= $s['value'] ?></h3>
                        <small class="text-muted fw-medium"><?= $s['label'] ?></small>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- ── Section 2: Quick Actions + Role Chart ────────────────────── -->
            <div class="row g-4 mb-5">
                <!-- Quick Actions -->
                <div class="col-lg-8">
                    <div class="section-card h-100">
                        <h5 class="fw-bold mb-4" style="color: var(--nature-forest);">Quick Actions</h5>
                        <div class="row g-3">
                            <?php
                            $actions = [
                                ['href' => 'admin-plants.php',  'icon' => 'plus-circle',     'label' => 'Add New Plant',       'color' => '#2D4F32'],
                                ['href' => 'admin-healers.php', 'icon' => 'user-plus',        'label' => 'Register Healer',     'color' => '#1B6CA8'],
                                ['href' => 'admin-logs.php',    'icon' => 'file-text',        'label' => 'View Audit Logs',     'color' => '#7C3AED'],
                                ['href' => 'admin-plants.php',  'icon' => 'shield-check',     'label' => 'Approve Plants',      'color' => '#D97706'],
                                ['href' => '#',                 'icon' => 'clipboard-list',   'label' => 'BHW Field Reports',   'color' => '#059669'],
                                ['href' => '#',                 'icon' => 'settings',         'label' => 'System Config',       'color' => '#DB2777'],
                            ];
                            foreach ($actions as $a):
                            ?>
                            <div class="col-4">
                                <a href="<?= $a['href'] ?>" class="action-card">
                                    <div class="action-icon mb-2">
                                        <i data-lucide="<?= $a['icon'] ?>" style="width: 28px; height: 28px; color: <?= $a['color'] ?>;"></i>
                                    </div>
                                    <div class="fw-semibold" style="font-size: 0.82rem;"><?= $a['label'] ?></div>
                                </a>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Role Distribution Chart -->
                <div class="col-lg-4">
                    <div class="section-card h-100 d-flex flex-column">
                        <h5 class="fw-bold mb-1" style="color: var(--nature-forest);">User Distribution</h5>
                        <p class="text-muted small mb-4">Breakdown of platform roles</p>
                        <div class="chart-wrap flex-grow-1">
                            <canvas id="roleChart"></canvas>
                        </div>
                        <div class="d-flex justify-content-around mt-3">
                            <div class="text-center">
                                <div class="fw-bold" style="color: #2D4F32;"><?= $adminCount ?></div>
                                <small class="text-muted">Admins</small>
                            </div>
                            <div class="text-center">
                                <div class="fw-bold" style="color: #1B6CA8;"><?= $healerRoleCount ?></div>
                                <small class="text-muted">Healers</small>
                            </div>
                            <div class="text-center">
                                <div class="fw-bold" style="color: '#D97706';"><?= $userRoleCount ?></div>
                                <small class="text-muted">Users</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── Section 3: Role Management Table ────────────────────────── -->
            <div class="section-card mb-5">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h5 class="fw-bold mb-1" style="color: var(--nature-forest);">User Role Management</h5>
                        <p class="text-muted small mb-0">Recent accounts and their assigned platform access</p>
                    </div>
                    <a href="#" class="btn btn-sm btn-success rounded-pill px-4 fw-semibold" style="background: var(--nature-forest); border: none;">Manage All Users</a>
                </div>
                <div class="table-responsive">
                    <table class="table table-borderless align-middle mb-0" style="font-size: 0.875rem;">
                        <thead>
                            <tr style="border-bottom: 2px solid #F1F3E9;">
                                <th class="fw-bold text-muted text-uppercase" style="font-size: 0.7rem; letter-spacing: 1px; padding-bottom: 1rem;">User</th>
                                <th class="fw-bold text-muted text-uppercase" style="font-size: 0.7rem; letter-spacing: 1px;">Role</th>
                                <th class="fw-bold text-muted text-uppercase" style="font-size: 0.7rem; letter-spacing: 1px;">Permissions</th>
                                <th class="fw-bold text-muted text-uppercase" style="font-size: 0.7rem; letter-spacing: 1px;">Joined</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($userList as $u):
                            $rb = $roleBadge[$u['role']] ?? $roleBadge['user'];
                            $perms = $permissions[$u['role']] ?? [];
                        ?>
                            <tr class="role-row" style="border-bottom: 1px solid rgba(0,0,0,0.03);">
                                <td>
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="rounded-circle d-flex align-items-center justify-content-center fw-bold" style="width:38px;height:38px;background:<?= $rb['bg'] ?>;color:<?= $rb['color'] ?>;font-size:0.9rem;">
                                            <?= strtoupper(substr($u['username'], 0, 1)) ?>
                                        </div>
                                        <div>
                                            <div class="fw-semibold" style="color: var(--nature-forest);"><?= htmlspecialchars($u['username']) ?></div>
                                            <small class="text-muted"><?= htmlspecialchars($u['email']) ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge rounded-pill px-3 py-2 fw-bold" style="background:<?= $rb['bg'] ?>;color:<?= $rb['color'] ?>;font-size:0.7rem;">
                                        <?= ucfirst($rb['label']) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php foreach (array_slice($perms, 0, 2) as $perm): ?>
                                        <span class="permission-pill" style="background:<?= $rb['bg'] ?>;color:<?= $rb['color'] ?>;"><?= $perm ?></span>
                                    <?php endforeach; ?>
                                    <?php if (count($perms) > 2): ?>
                                        <span class="text-muted" style="font-size:0.7rem;">+<?= count($perms) - 2 ?> more</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-muted"><?= date('M d, Y', strtotime($u['created_at'])) ?></td>
                                <td>
                                    <button class="btn btn-sm rounded-pill px-3 fw-semibold" style="background:#F7FAF5;border:1px solid #E8EDE0;color:var(--nature-forest);font-size:0.75rem;">Edit Role</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- ── Section 4: Audit Logs + Appointments ─────────────────────── -->
            <div class="row g-4">
                <!-- Recent Activity Logs -->
                <div class="col-lg-6">
                    <div class="section-card h-100">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h5 class="fw-bold mb-0" style="color: var(--nature-forest);">Recent Activity</h5>
                            <a href="admin-logs.php" class="btn btn-sm btn-link text-success p-0 text-decoration-none fw-semibold small">View All →</a>
                        </div>
                        <?php if (empty($recentLogs)): ?>
                            <p class="text-muted small">No recent AI logs found.</p>
                        <?php else: ?>
                            <?php foreach ($recentLogs as $log):
                                $dotColor = $log['action_type'] === 'recognition' ? '#059669' : '#7C3AED';
                                $roleLabel = ucfirst($log['role'] ?? 'user');
                            ?>
                            <div class="d-flex align-items-start mb-3 pb-3" style="border-bottom: 1px solid rgba(0,0,0,0.04);">
                                <span class="log-dot mt-1" style="background: <?= $dotColor ?>;"></span>
                                <div class="flex-grow-1">
                                    <div class="fw-semibold small" style="color: var(--nature-forest);">
                                        [<?= htmlspecialchars($roleLabel) ?>] <?= htmlspecialchars($log['username'] ?? 'Unknown') ?> 
                                        <span class="fw-normal text-muted">used <?= $log['action_type'] === 'recognition' ? 'Plant Scanner' : 'Aura Chatbot' ?></span>
                                    </div>
                                    <small class="text-muted">
                                        <?= htmlspecialchars(mb_substr($log['query'] ?? '', 0, 55)) ?><?= strlen($log['query'] ?? '') > 55 ? '…' : '' ?>
                                    </small>
                                </div>
                                <small class="text-muted flex-shrink-0 ms-2"><?= date('H:i', strtotime($log['timestamp'])) ?></small>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Recent Appointments -->
                <div class="col-lg-6">
                    <div class="section-card h-100">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h5 class="fw-bold mb-0" style="color: var(--nature-forest);">Recent Bookings</h5>
                            <span class="badge rounded-pill" style="background: rgba(45,79,50,0.08); color: var(--nature-forest);"><?= $appointCount ?> total</span>
                        </div>
                        <?php if (empty($recentAppointments)): ?>
                            <div class="text-center py-4">
                                <i data-lucide="calendar-x" class="text-muted mb-2" style="width: 36px; height: 36px; opacity: 0.3;"></i>
                                <p class="text-muted small mb-0">No appointments yet.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($recentAppointments as $appt):
                                $statusColor = match($appt['status']) {
                                    'accepted' => '#059669',
                                    'rejected' => '#DC2626',
                                    default    => '#D97706'
                                };
                            ?>
                            <div class="d-flex align-items-center mb-3 pb-3" style="border-bottom: 1px solid rgba(0,0,0,0.04);">
                                <div class="rounded-circle d-flex align-items-center justify-content-center me-3 flex-shrink-0" style="width:36px;height:36px;background:rgba(45,79,50,0.08);">
                                    <i data-lucide="calendar" style="width:16px;color:var(--nature-forest);"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <div class="fw-semibold small" style="color: var(--nature-forest);"><?= htmlspecialchars($appt['username']) ?> → <?= htmlspecialchars($appt['full_name']) ?></div>
                                    <small class="text-muted"><?= htmlspecialchars($appt['specialization']) ?> · <?= date('M d', strtotime($appt['appointment_date'])) ?></small>
                                </div>
                                <span class="badge rounded-pill fw-bold" style="background:<?= $statusColor ?>20;color:<?= $statusColor ?>;font-size:0.65rem;padding:4px 10px;">
                                    <?= ucfirst($appt['status']) ?>
                                </span>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

        </main>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    lucide.createIcons();

    // Role Distribution Doughnut Chart
    const ctx = document.getElementById('roleChart').getContext('2d');
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['Admins', 'Healers', 'Users'],
            datasets: [{
                data: [<?= $adminCount ?>, <?= $healerRoleCount ?>, <?= $userRoleCount ?>],
                backgroundColor: ['#2D4F32', '#1B6CA8', '#D97706'],
                borderWidth: 0,
                hoverOffset: 6,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '70%',
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: ctx => ` ${ctx.label}: ${ctx.parsed} users`
                    }
                }
            }
        }
    });
</script>
</body>
</html>

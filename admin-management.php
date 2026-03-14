<?php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$role     = $_SESSION['role'];
$username = $_SESSION['username'];

// ── Ensure tables exist ──────────────────────────────────────────────────────
try {
    // Modify users for BHW role
    $pdo->exec("ALTER TABLE users MODIFY COLUMN role ENUM('admin','healer','user','bhw') DEFAULT 'user'");
    
    // Create barangays table
    $pdo->exec("CREATE TABLE IF NOT EXISTS barangays (
        id INT AUTO_INCREMENT PRIMARY KEY,
        barangay_name VARCHAR(100) NOT NULL UNIQUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
} catch (PDOException $e) { /* Ignore */ }

// TRY TO DROP THE UNIQUE CONSTRAINT ON USERNAME (if needed)
try {
    $stmt = $pdo->query("SHOW INDEX FROM users WHERE Column_name = 'username' AND Non_unique = 0");
    $index = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($index) {
        $keyName = $index['Key_name'];
        $pdo->exec("ALTER TABLE users DROP INDEX `$keyName` ");
    }
} catch (PDOException $e) { /* Index already gone? */ }

// ── Handle Barangay Actions (Add/Delete) ─────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add_barangay') {
        $nb = trim($_POST['new_barangay_name'] ?? '');
        if ($nb) {
            try {
                $stmt = $pdo->prepare("INSERT IGNORE INTO barangays (barangay_name) VALUES (?)");
                $stmt->execute([$nb]);
                $success = "Barangay <strong>" . htmlspecialchars($nb) . "</strong> added.";
            } catch (PDOException $e) { $errors[] = $e->getMessage(); }
        }
    }
    if ($_POST['action'] === 'update_barangay') {
        $bid = (int)$_POST['bid'];
        $nb  = trim($_POST['barangay_name'] ?? '');
        if ($bid && $nb) {
            try {
                $stmt = $pdo->prepare("UPDATE barangays SET barangay_name = ? WHERE id = ?");
                $stmt->execute([$nb, $bid]);
                $success = "Barangay updated to <strong>" . htmlspecialchars($nb) . "</strong>.";
            } catch (PDOException $e) { $errors[] = $e->getMessage(); }
        }
    }
    if ($_POST['action'] === 'delete_barangay') {
        $bid = (int)$_POST['bid'];
        try {
            $pdo->prepare("DELETE FROM barangays WHERE id = ?")->execute([$bid]);
            $success = "Barangay removed.";
        } catch (PDOException $e) { $errors[] = $e->getMessage(); }
    }
}

// Fetch all barangays
try {
    $barangays = $pdo->query("SELECT * FROM barangays ORDER BY barangay_name ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $barangays = []; }

$errors   = [];
$success  = '';

// ── Handle Form Submissions ───────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // -- CREATE USER --
    if ($action === 'create') {
        $fullname   = trim($_POST['fullname'] ?? '');
        $email      = strtolower(trim($_POST['email'] ?? ''));
        $newRoleRaw = $_POST['new_role'] ?? '';
        $newRole    = in_array($newRoleRaw, ['admin', 'bhw']) ? $newRoleRaw : 'user';
        $barangay   = trim($_POST['barangay'] ?? '');
        $tempPass   = $_POST['temp_password'] ?? '';

        // Derive username from email prefix
        $baseUsername = preg_replace('/[^a-z0-9]/', '', explode('@', $email)[0]);
        $userSlug     = $baseUsername ?: strtolower(str_replace(' ', '', $fullname));
        
        // Use a simple slug. We no longer strictly enforce uniqueness through a DB constraint,
        // but we'll use the slug as the base for the username.
        $uniqueUN = $userSlug ?: 'user' . rand(100,999);

        if (!$fullname)   $errors[] = 'Full name is required.';
        if (!$newRoleRaw) $errors[] = 'Assigned role is required.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email required.';
        if (strlen($tempPass) < 6) $errors[] = 'Password must be at least 6 characters.';
        if ($newRole === 'bhw' && !$barangay) $errors[] = 'Assigned barangay is required for BHW.';

        if (empty($errors)) {
            try {
                // Check duplicate email
                $dupCheck = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                $dupCheck->execute([$email]);
                if ($dupCheck->fetch()) {
                    $errors[] = 'This email address is already registered.';
                } else {
                    $hashed = password_hash($tempPass, PASSWORD_BCRYPT);
                    // Store barangay in username as a prefix for BHW (e.g. "bhw_cogon_jdoe")
                    $finalUsername = $newRole === 'bhw'
                        ? 'bhw_' . strtolower(preg_replace('/[^a-z0-9]/i', '', $barangay)) . '_' . $uniqueUN
                        : $uniqueUN;

                    $ins = $pdo->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)");
                    $ins->execute([$finalUsername, $email, $hashed, $newRole]);
                    $success = "Staff account for <strong>" . htmlspecialchars($fullname) . "</strong> created successfully.";
                }
            } catch (PDOException $e) {
                $errors[] = 'Database error: ' . $e->getMessage();
            }
        }
    }

    // -- TOGGLE ACTIVE/INACTIVE (using a simple role swap to 'user' as deactivation) --
    if ($action === 'toggle') {
        $uid       = (int)$_POST['uid'];
        $curRole   = $_POST['cur_role'];
        $newStatus = $_POST['new_status'];
        try {
            $upd = $pdo->prepare("UPDATE users SET role = ? WHERE id = ?");
            $upd->execute([$newStatus, $uid]);
            $success = "User status updated.";
        } catch (PDOException $e) { $errors[] = $e->getMessage(); }
    }

    // -- RESET PASSWORD --
    if ($action === 'reset') {
        $uid      = (int)$_POST['uid'];
        $newPass  = $_POST['new_password'] ?? '';
        if (strlen($newPass) < 6) {
            $errors[] = 'New password must be at least 6 characters.';
        } else {
            try {
                $upd = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                $upd->execute([password_hash($newPass, PASSWORD_BCRYPT), $uid]);
                $success = "Password reset successfully.";
            } catch (PDOException $e) { $errors[] = $e->getMessage(); }
        }
    }

    // -- DELETE USER --
    if ($action === 'delete') {
        $uid = (int)$_POST['uid'];
        try {
            $pdo->prepare("DELETE FROM users WHERE id = ? AND id != ?")->execute([$uid, $_SESSION['user_id']]);
            $success = "User account removed.";
        } catch (PDOException $e) { $errors[] = $e->getMessage(); }
    }
}

// ── Fetch Staff Users (admin + bhw) ──────────────────────────────────────────
try {
    $staffList = $pdo->query(
        "SELECT id, username, email, role, created_at FROM users WHERE role IN ('admin','bhw') ORDER BY role, created_at DESC"
    )->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $staffList = []; }

$roleBadge = [
    'admin' => ['label' => 'Admin',  'color' => '#2D4F32', 'bg' => 'rgba(45,79,50,0.10)'],
    'bhw'   => ['label' => 'BHW',    'color' => '#1565C0', 'bg' => 'rgba(21,101,192,0.10)'],
    'user'  => ['label' => 'Inactive','color' => '#6B7280','bg' => 'rgba(107,114,128,0.10)'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management | Healing Compass Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Lexend:wght@300;400;500;600;700;800&family=Outfit:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .section-card {
            background: white;
            border-radius: 1.75rem;
            padding: 1.75rem 2rem;
            border: 1px solid rgba(0,0,0,0.03);
            box-shadow: 0 4px 20px rgba(45,79,50,0.04);
        }

        .sage-input {
            background: #F7FAF5 !important;
            border: 2px solid #E8EDE0 !important;
            border-radius: 0.9rem !important;
            padding: 12px 16px !important;
            font-family: 'Lexend', sans-serif !important;
            font-size: 0.875rem !important;
            color: var(--nature-forest) !important;
        }
        .sage-input:focus {
            box-shadow: none !important;
            border-color: var(--nature-forest) !important;
        }
        .sage-label {
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            color: var(--nature-forest);
            opacity: 0.65;
            margin-bottom: 8px;
            display: block;
        }
        .staff-row td { vertical-align: middle; padding: 0.9rem 1rem; }
        .staff-row:hover { background: #FAFCF8; }
        .action-icon-btn {
            width: 34px; height: 34px;
            border-radius: 10px;
            display: inline-flex; align-items: center; justify-content: center;
            border: none; cursor: pointer; transition: all 0.2s ease;
            background: #F7FAF5;
            color: var(--nature-forest);
        }
        .action-icon-btn:hover { transform: scale(1.1); }

        .pass-gen-group { position: relative; }
        .pass-gen-group input { padding-right: 95px !important; }
        .pass-gen-btn {
            position: absolute; right: 8px; top: 50%; transform: translateY(-50%);
            background: var(--nature-forest); color: white;
            border: none; border-radius: 0.6rem;
            padding: 5px 12px; font-size: 0.72rem; font-weight: 700; cursor: pointer;
        }

        .modal-content { border-radius: 2rem; border: none; overflow: hidden; }
        .modal-header { background: white; border-bottom: 1px solid #F1F3E9; padding: 1.5rem 2rem 1rem; }
        .modal-body   { background: white; padding: 1.5rem 2rem; }
        .modal-footer { background: white; border-top: 1px solid #F1F3E9; padding: 1rem 2rem 1.5rem; }
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
                <a href="admin-dashboard.php" class="sidebar-link">
                    <i data-lucide="bar-chart-3"></i> Dashboard
                </a>
                <a href="admin-management.php" class="sidebar-link active">
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
            <div style="position: absolute; bottom: 30px; left: 20px; right: 20px;">
                <a href="controllers/AuthController.php?action=logout" class="sidebar-link text-danger">
                    <i data-lucide="log-out"></i> Logout
                </a>
            </div>
        </nav>

        <!-- Main -->
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-5 py-5 min-vh-100">

            <!-- Header Row -->
            <div class="d-flex justify-content-between align-items-start mb-5">
                <div>
                    <h1 class="fw-bold mb-1" style="font-size: 2.4rem; letter-spacing: -1.5px; color: var(--nature-forest);">User Role Management</h1>
                    <p class="text-muted fw-medium mb-0">Create and oversee Admin and BHW platform accounts</p>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn fw-bold rounded-pill px-4 shadow-sm" data-bs-toggle="modal" data-bs-target="#createUserModal"
                        style="background: var(--nature-forest); color: white; border: none; height: 48px; font-size: 0.9rem;">
                        <i data-lucide="user-plus" class="me-2" style="width: 17px;"></i> Add New Staff
                    </button>
                </div>
            </div>

            <!-- Alerts -->
            <?php if ($success): ?>
            <div class="alert border-0 rounded-4 mb-4 d-flex align-items-center" style="background: rgba(45,79,50,0.08); color: var(--nature-forest);">
                <i data-lucide="check-circle" class="me-3 flex-shrink-0" style="width: 20px;"></i>
                <span><?= $success ?></span>
            </div>
            <?php endif; ?>
            <?php foreach ($errors as $err): ?>
            <div class="alert border-0 rounded-4 mb-2 d-flex align-items-center" style="background: rgba(220,53,69,0.08); color: #DC3545;">
                <i data-lucide="alert-circle" class="me-3 flex-shrink-0" style="width: 20px;"></i>
                <span><?= htmlspecialchars($err) ?></span>
            </div>
            <?php endforeach; ?>

            <!-- ── Stats Strip ─────────────────────────────────────────────── -->
            <div class="row g-3 mb-5">
                <?php
                $adminC = count(array_filter($staffList, fn($u) => $u['role'] === 'admin'));
                $bhwC   = count(array_filter($staffList, fn($u) => $u['role'] === 'bhw'));
                $strips = [
                    ['label' => 'Total Staff',   'value' => count($staffList), 'icon' => 'users',     'color' => '#2D4F32', 'bg' => 'rgba(45,79,50,0.08)'],
                    ['label' => 'Admins',         'value' => $adminC,          'icon' => 'shield',    'color' => '#2D4F32', 'bg' => 'rgba(45,79,50,0.08)'],
                    ['label' => 'BHW Accounts',   'value' => $bhwC,            'icon' => 'heart-pulse','color' => '#1565C0', 'bg' => 'rgba(21,101,192,0.08)'],
                ];
                foreach ($strips as $s): ?>
                <div class="col-md-4">
                    <div class="section-card d-flex align-items-center gap-3">
                        <div style="width:48px;height:48px;border-radius:14px;background:<?= $s['bg'] ?>;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                            <i data-lucide="<?= $s['icon'] ?>" style="width:22px;color:<?= $s['color'] ?>;"></i>
                        </div>
                        <div>
                            <h3 class="fw-bold mb-0" style="color:<?= $s['color'] ?>;"><?= $s['value'] ?></h3>
                            <small class="text-muted fw-medium"><?= $s['label'] ?></small>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- ── Staff Accounts Table ───────────────────────────────────── -->
            <div class="section-card">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h5 class="fw-bold mb-0" style="color: var(--nature-forest);">Staff Directory</h5>
                    <input type="text" id="tableSearch" placeholder="Search staff…"
                        class="form-control rounded-pill border-0 shadow-sm" style="max-width: 260px; background: #F7FAF5; font-size: 0.85rem; padding: 8px 18px;">
                </div>

                <div class="table-responsive">
                    <table class="table table-borderless mb-0" id="staffTable">
                        <thead>
                            <tr style="border-bottom: 2px solid #F1F3E9;">
                                <th class="sage-label pb-3">Account</th>
                                <th class="sage-label pb-3">Role</th>
                                <th class="sage-label pb-3">Barangay / Scope</th>
                                <th class="sage-label pb-3">Status</th>
                                <th class="sage-label pb-3">Joined</th>
                                <th class="sage-label pb-3">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($staffList)): ?>
                            <tr><td colspan="6" class="text-center py-5 text-muted">No staff accounts yet. Click <strong>Add New Staff</strong> to get started.</td></tr>
                        <?php else: ?>
                        <?php foreach ($staffList as $u):
                            $rb = $roleBadge[$u['role']] ?? $roleBadge['user'];
                            $isActive = in_array($u['role'], ['admin', 'bhw']);

                            // Extract barangay from BHW username (format: bhw_brgyname_username)
                            $brgyDisplay = '—';
                            if ($u['role'] === 'bhw' && str_starts_with($u['username'], 'bhw_')) {
                                $parts = explode('_', $u['username'], 3);
                                $brgyDisplay = ucfirst($parts[1] ?? '—');
                            }
                        ?>
                        <tr class="staff-row" style="border-bottom: 1px solid rgba(0,0,0,0.04);">
                            <td>
                                <div class="d-flex align-items-center gap-3">
                                    <div class="rounded-circle d-flex align-items-center justify-content-center fw-bold flex-shrink-0"
                                         style="width:40px;height:40px;background:<?= $rb['bg'] ?>;color:<?= $rb['color'] ?>;font-size:0.9rem;">
                                        <?= strtoupper(substr($u['username'],0,1)) ?>
                                    </div>
                                    <div>
                                        <div class="fw-semibold" style="color:var(--nature-forest);font-size:0.875rem;"><?= htmlspecialchars($u['username']) ?></div>
                                        <small class="text-muted"><?= htmlspecialchars($u['email']) ?></small>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="badge rounded-pill px-3 py-2 fw-bold"
                                      style="background:<?= $rb['bg'] ?>;color:<?= $rb['color'] ?>;font-size:0.68rem;">
                                    <?= $rb['label'] ?>
                                </span>
                            </td>
                            <td class="text-muted" style="font-size:0.85rem;"><?= htmlspecialchars($brgyDisplay) ?></td>
                            <td>
                                <?php if ($isActive): ?>
                                    <span class="d-flex align-items-center gap-1" style="font-size:0.8rem;color:#059669;font-weight:600;">
                                        <span style="width:7px;height:7px;border-radius:50%;background:#059669;display:inline-block;"></span> Active
                                    </span>
                                <?php else: ?>
                                    <span class="d-flex align-items-center gap-1" style="font-size:0.8rem;color:#6B7280;font-weight:600;">
                                        <span style="width:7px;height:7px;border-radius:50%;background:#6B7280;display:inline-block;"></span> Inactive
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="text-muted" style="font-size:0.82rem;"><?= date('M d, Y', strtotime($u['created_at'])) ?></td>
                            <td>
                                <div class="d-flex gap-2">
                                    <!-- Reset Password -->
                                    <button class="action-icon-btn" title="Reset Password"
                                            onclick="showResetModal(<?= $u['id'] ?>, '<?= htmlspecialchars($u['username']) ?>')"
                                            style="background:rgba(124,58,237,0.08);color:#7C3AED;">
                                        <i data-lucide="key-round" style="width:15px;"></i>
                                    </button>

                                    <!-- Toggle Active / Deactivate -->
                                    <?php if ($u['id'] !== (int)$_SESSION['user_id']): ?>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="action"     value="toggle">
                                        <input type="hidden" name="uid"        value="<?= $u['id'] ?>">
                                        <input type="hidden" name="cur_role"   value="<?= $u['role'] ?>">
                                        <input type="hidden" name="new_status" value="<?= $isActive ? 'user' : $u['role'] ?>">
                                        <button type="submit" class="action-icon-btn" title="<?= $isActive ? 'Deactivate' : 'Restore' ?>"
                                                style="background:<?= $isActive ? 'rgba(217,119,6,0.08)' : 'rgba(5,150,105,0.08)' ?>;color:<?= $isActive ? '#D97706' : '#059669' ?>;">
                                            <i data-lucide="<?= $isActive ? 'user-x' : 'user-check' ?>" style="width:15px;"></i>
                                        </button>
                                    </form>

                                    <!-- Delete -->
                                    <button class="action-icon-btn" title="Delete Account"
                                            onclick="confirmDeleteUser(<?= $u['id'] ?>, '<?= htmlspecialchars($u['username']) ?>')"
                                            style="background:rgba(220,53,69,0.08);color:#DC3545;">
                                        <i data-lucide="trash-2" style="width:15px;"></i>
                                    </button>
                                    <?php else: ?>
                                    <span class="text-muted small fst-italic">(you)</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
            </div>

            <!-- ── Barangay Management Section ─────────────────────────────── -->
            <div class="row mt-5">
                <div class="col-12">
                    <div class="section-card">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <div>
                                <h5 class="fw-bold mb-0" style="color: var(--nature-forest);">Barangay Management</h5>
                                <small class="text-muted">Directly manage health worker grouping locations</small>
                            </div>
                            <button class="btn fw-bold rounded-pill px-3 shadow-sm btn-sm" 
                                    data-bs-toggle="collapse" data-bs-target="#addBarangayForm"
                                    style="background: var(--nature-forest); color: white; border: none;">
                                <i data-lucide="plus" class="me-1" style="width: 14px;"></i> Add New
                            </button>
                        </div>

                        <!-- Add Form (Collapsible) -->
                        <div class="collapse mb-4" id="addBarangayForm">
                            <form method="POST" class="p-3 rounded-4" style="background:#F7FAF5; border:1px solid #E8EDE0;">
                                <input type="hidden" name="action" value="add_barangay">
                                <label class="sage-label">New Barangay Name</label>
                                <div class="d-flex gap-2">
                                    <input type="text" name="new_barangay_name" class="sage-input form-control" placeholder="e.g. Brgy. Santa Cruz" required>
                                    <button class="btn fw-bold rounded-pill px-4" style="background:var(--nature-forest);color:white;border:none;">Save</button>
                                </div>
                            </form>
                        </div>

                        <div class="grid-container" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 1rem;">
                            <?php if (empty($barangays)): ?>
                                <div class="text-muted small p-4 text-center border rounded-4 w-100" style="grid-column: 1 / -1;">No barangays added yet.</div>
                            <?php else: ?>
                                <?php foreach ($barangays as $bg): ?>
                                <div class="p-3 rounded-4 border d-flex justify-content-between align-items-center bg-white shadow-sm hover-shadow transition-all">
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="bg-light rounded-circle shadow-sm d-flex align-items-center justify-content-center" style="width:32px;height:32px;">
                                            <i data-lucide="map-pin" style="width:14px;color:var(--nature-forest);"></i>
                                        </div>
                                        <span class="fw-semibold text-dark" style="font-size:0.88rem;"><?= htmlspecialchars($bg['barangay_name']) ?></span>
                                    </div>
                                    <div class="d-flex gap-1">
                                        <button class="action-icon-btn btn-sm sm" onclick="editBarangay(<?= $bg['id'] ?>, '<?= addslashes($bg['barangay_name']) ?>')" title="Edit" style="width:30px;height:30px;border-radius:8px;background:rgba(45,79,50,0.05);border:none;color:var(--nature-forest);">
                                            <i data-lucide="edit-3" style="width:13px;"></i>
                                        </button>
                                        <form method="POST" onsubmit="return confirm('Delete this barangay? Staff using it will keep their prefix but won\'t be filterable by this exact name.')">
                                            <input type="hidden" name="action" value="delete_barangay">
                                            <input type="hidden" name="bid"    value="<?= $bg['id'] ?>">
                                            <button class="action-icon-btn btn-sm sm text-danger" title="Delete" style="width:30px;height:30px;border-radius:8px;background:rgba(220,53,69,0.05);border:none;">
                                                <i data-lucide="trash-2" style="width:13px;"></i>
                                            </button>
                                        </form>
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

<!-- ── CREATE USER MODAL ──────────────────────────────────────────────────── -->
<div class="modal fade" id="createUserModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header border-0">
                <div>
                    <h5 class="fw-bold mb-0" style="color:var(--nature-forest);">Create Staff Account</h5>
                    <small class="text-muted">Fill in the details below to grant platform access.</small>
                </div>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body pt-0">
                <form method="POST" id="createForm" novalidate>
                    <input type="hidden" name="action" value="create">

                    <div class="row g-3">
                        <!-- Full Name -->
                        <div class="col-md-6">
                            <label class="sage-label">Full Name</label>
                            <input type="text" name="fullname" class="sage-input form-control"
                                   placeholder="e.g. Maria Santos" required value="<?= htmlspecialchars($_POST['fullname'] ?? '') ?>">
                        </div>
                        <!-- Email -->
                        <div class="col-md-6">
                            <label class="sage-label">Email Address</label>
                            <input type="email" name="email" class="sage-input form-control"
                                   placeholder="staff@healingcompass.ph" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                        </div>
                        <!-- Role -->
                        <div class="col-md-6">
                            <label class="sage-label">Assigned Role</label>
                            <select name="new_role" id="roleSelect" class="sage-input form-select" required onchange="toggleBarangay(this.value)">
                                <option value="" disabled <?= empty($_POST['new_role']) ? 'selected' : '' ?>>Choose role…</option>
                                <option value="admin" <?= (($_POST['new_role']??'') === 'admin') ? 'selected' : '' ?>>Admin — Full platform access</option>
                                <option value="bhw" <?= (($_POST['new_role']??'') === 'bhw') ? 'selected' : '' ?>>BHW — Barangay Health Worker</option>
                            </select>
                        </div>

                        <!-- Barangay (BHW only) -->
                        <div class="col-md-6" id="barangayWrap" style="display: <?= (($_POST['new_role']??'') === 'bhw') ? 'block' : 'none' ?>;">
                            <label class="sage-label">Assigned Barangay</label>
                            <select name="barangay" id="barangaySelect" class="sage-input form-select" <?= (($_POST['new_role']??'') === 'bhw') ? 'required' : '' ?>>
                                <option value="" disabled <?= empty($_POST['barangay']) ? 'selected' : '' ?>>Select barangay…</option>
                                <?php foreach ($barangays as $b): ?>
                                    <option value="<?= htmlspecialchars($b['barangay_name']) ?>" <?= (($_POST['barangay']??'') === $b['barangay_name']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($b['barangay_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Temp Password -->
                        <div class="col-12">
                            <label class="sage-label">Temporary Password</label>
                            <div class="pass-gen-group">
                                <input type="text" name="temp_password" id="tempPassInput"
                                       class="sage-input form-control" placeholder="At least 6 characters" required>
                                <button type="button" class="pass-gen-btn" onclick="generatePassword()">⚡ Generate</button>
                            </div>
                            <small class="text-muted" style="font-size: 0.72rem;">The staff member should change this on first login.</small>
                        </div>
                    </div>

                    <!-- Role Permission Preview -->
                    <div id="permPreview" class="mt-4 p-3 rounded-4" style="background:#F7FAF5;border:1px solid #E8EDE0;display:none;">
                        <div class="sage-label mb-2">Role Capabilities</div>
                        <div id="permList"></div>
                    </div>
                </form>
            </div>
            <div class="modal-footer border-0 gap-2">
                <button type="button" class="btn rounded-pill px-4 fw-semibold" data-bs-dismiss="modal"
                        style="background:#F7FAF5;border:2px solid #E8EDE0;color:var(--nature-forest);">Cancel</button>
                <button type="submit" form="createForm" class="btn rounded-pill px-5 fw-bold"
                        style="background:var(--nature-forest);color:white;border:none;">
                    <i data-lucide="user-plus" class="me-2" style="width:16px;"></i> Create Account
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ── EDIT BARANGAY MODAL ────────────────────────────────────────────────── -->
<div class="modal fade" id="editBarangayModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0">
                <div>
                    <h5 class="fw-bold mb-0" style="color:var(--nature-forest);">Edit Barangay</h5>
                    <small class="text-muted">Update the name of this location.</small>
                </div>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form method="POST" id="editBarangayForm">
                    <input type="hidden" name="action" value="update_barangay">
                    <input type="hidden" name="bid" id="editBgId">
                    <label class="sage-label">Barangay Name</label>
                    <input type="text" name="barangay_name" id="editBgName" class="sage-input form-control" required>
                </form>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn rounded-pill px-4 fw-semibold" data-bs-dismiss="modal"
                        style="background:#F7FAF5;border:2px solid #E8EDE0;color:var(--nature-forest);">Cancel</button>
                <button type="submit" form="editBarangayForm" class="btn rounded-pill px-5 fw-bold"
                        style="background:var(--nature-forest);color:white;border:none;">Save Changes</button>
            </div>
        </div>
    </div>
</div>

<!-- ── RESET PASSWORD MODAL ───────────────────────────────────────────────── -->
<div class="modal fade" id="resetPasswordModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0">
                <div>
                    <h5 class="fw-bold mb-0" style="color:var(--nature-forest);">Reset Password</h5>
                    <small class="text-muted" id="resetSubtitle"></small>
                </div>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body pt-0">
                <form method="POST" id="resetForm">
                    <input type="hidden" name="action" value="reset">
                    <input type="hidden" name="uid" id="resetUid">
                    <label class="sage-label">New Password</label>
                    <div class="pass-gen-group">
                        <input type="text" name="new_password" id="resetPassInput"
                               class="sage-input form-control" placeholder="New password (min. 6 chars)" required>
                        <button type="button" class="pass-gen-btn" onclick="generatePasswordReset()">⚡ Generate</button>
                    </div>
                </form>
            </div>
            <div class="modal-footer border-0 gap-2">
                <button type="button" class="btn rounded-pill px-4 fw-semibold" data-bs-dismiss="modal"
                        style="background:#F7FAF5;border:2px solid #E8EDE0;color:var(--nature-forest);">Cancel</button>
                <button type="submit" form="resetForm" class="btn rounded-pill px-5 fw-bold"
                        style="background:#7C3AED;color:white;border:none;">
                    <i data-lucide="key-round" class="me-2" style="width:16px;"></i> Reset Password
                </button>
            </div>
        </div>
    </div>
</div>

<!-- DELETE form (hidden) -->
<form method="POST" id="deleteForm" style="display:none;">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="uid" id="deleteUid">
</form>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
lucide.createIcons();

// ── Show/hide barangay field ──────────────────────────────────────────────────
function toggleBarangay(val) {
    const wrap = document.getElementById('barangayWrap');
    const bSel = document.getElementById('barangaySelect');
    wrap.style.display = val === 'bhw' ? 'block' : 'none';
    bSel.required      = val === 'bhw';

    const perms = {
        admin: ['Approve/Reject Plants', 'Manage Healer Profiles', 'View Audit Logs', 'Manage Staff Accounts', 'System Configuration'],
        bhw:   ['Submit Field Reports', 'Edit Local Healer Data', 'Book Appointments for Patients', 'View Community Plant Library']
    };

    const preview = document.getElementById('permPreview');
    const list    = document.getElementById('permList');
    if (perms[val]) {
        preview.style.display = 'block';
        list.innerHTML = perms[val]
            .map(p => `<span class="badge rounded-pill me-1 mb-1 fw-semibold" style="background:${val==='admin'?'rgba(45,79,50,0.1)':'rgba(21,101,192,0.1)'};color:${val==='admin'?'#2D4F32':'#1565C0'};padding:5px 12px;font-size:0.7rem;">${p}</span>`)
            .join('');
    } else { preview.style.display = 'none'; }
}

// ── Edit Barangay Handler ────────────────────────────────────────────────────
function editBarangay(id, name) {
    document.getElementById('editBgId').value   = id;
    document.getElementById('editBgName').value = name;
    new bootstrap.Modal(document.getElementById('editBarangayModal')).show();
}

// ── Password generators ───────────────────────────────────────────────────────
function rand() {
    const chars = 'ABCDEFGHJKMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789@#!';
    return Array.from({length: 12}, () => chars[Math.floor(Math.random()*chars.length)]).join('');
}
function generatePassword()      { document.getElementById('tempPassInput').value  = rand(); }
function generatePasswordReset() { document.getElementById('resetPassInput').value = rand(); }

// ── Reset password modal ──────────────────────────────────────────────────────
function showResetModal(uid, uname) {
    document.getElementById('resetUid').value   = uid;
    document.getElementById('resetSubtitle').textContent = 'Resetting password for: ' + uname;
    document.getElementById('resetPassInput').value = '';
    new bootstrap.Modal(document.getElementById('resetPasswordModal')).show();
}

// ── Delete confirmation ───────────────────────────────────────────────────────
function confirmDeleteUser(uid, uname) {
    Swal.fire({
        title: 'Remove Account?',
        html: `This will permanently delete <strong>${uname}</strong>'s account. This cannot be undone.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#DC3545',
        cancelButtonColor: '#2D4F32',
        confirmButtonText: 'Yes, Remove',
        cancelButtonText: 'Cancel',
    }).then(r => {
        if (r.isConfirmed) {
            document.getElementById('deleteUid').value = uid;
            document.getElementById('deleteForm').submit();
        }
    });
}

// ── Live table search ─────────────────────────────────────────────────────────
document.getElementById('tableSearch').addEventListener('input', function() {
    const q = this.value.toLowerCase();
    document.querySelectorAll('#staffTable tbody tr.staff-row').forEach(row => {
        row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
});

// ── Auto-open modal on errors ─────────────────────────────────────────────────
<?php if (!empty($errors) && !empty($_POST['action']) && $_POST['action'] === 'create'): ?>
const createModal = new bootstrap.Modal(document.getElementById('createUserModal'));
createModal.show();
// Triger the toggle manually to show capabilities preview
toggleBarangay(document.getElementById('roleSelect').value);
<?php endif; ?>

// ── Show success SweetAlert ───────────────────────────────────────────────────
<?php if ($success): ?>
Swal.fire({ icon: 'success', title: 'Done!', html: '<?= addslashes($success) ?>', confirmButtonColor: '#2D4F32', timer: 3000, timerProgressBar: true });
<?php endif; ?>
</script>
</body>
</html>

<?php
session_start();
require_once 'config/database.php';

$isLoggedIn = isset($_SESSION['user_id']);
if ($isLoggedIn) {
    $role = $_SESSION['role'];
    $username = $_SESSION['username'];
}

// Admin Deletion Handler
if (isset($role) && $role === 'admin' && isset($_GET['action']) && $_GET['action'] === 'delete') {
    $delId = (int)$_GET['id'];
    try {
        $pdo->beginTransaction();
        // Get user_id first to clean up
        $stmtUid = $pdo->prepare("SELECT user_id FROM healers WHERE id = ?");
        $stmtUid->execute([$delId]);
        $uid = $stmtUid->fetchColumn();

        $pdo->prepare("DELETE FROM healers WHERE id = ?")->execute([$delId]);
        if ($uid) {
            $pdo->prepare("DELETE FROM users WHERE id = ? AND role = 'healer'")->execute([$uid]);
        }
        $pdo->commit();
        header("Location: healers.php?delete=success");
        exit();
    } catch (PDOException $e) {
        $pdo->rollBack();
        header("Location: healers.php?error=" . urlencode("Could not delete healer."));
        exit();
    }
}

// Fetch healers
try {
    $search = $_GET['search'] ?? '';
    if ($search) {
        $stmt = $pdo->prepare("SELECT healers.*, users.username FROM healers JOIN users ON healers.user_id = users.id WHERE healers.full_name LIKE ? OR healers.specialization LIKE ? OR healers.location_name LIKE ?");
        $stmt->execute(["%$search%", "%$search%", "%$search%"]);
    } else {
        $stmt = $pdo->query("SELECT healers.*, users.username FROM healers JOIN users ON healers.user_id = users.id");
    }
    $healers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $healers = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Healer Directory | Healing Compass</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Lexend:wght@300;400;500;600;700;800&family=Outfit:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="<?= $isLoggedIn ? 'dashboard-app' : '' ?>" style="background-color: var(--nature-bg);">

    <?php if (!$isLoggedIn): ?>
    <!-- Public Navbar for Guests -->
    <nav class="navbar navbar-expand-lg sticky-top bg-white border-bottom shadow-sm">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center" href="index.php">
                <img src="assets/img/logo.png" alt="Healing Compass Logo" style="height: 40px;" class="me-2">
                <span>HEALING COMPASS<sup>&reg;</sup></span>
            </a>
            <div class="ms-auto">
                <a href="login.php" class="btn btn-outline-success btn-sm rounded-pill px-4 me-2">Login</a>
                <a href="register.php" class="btn btn-success btn-sm rounded-pill px-4">Join Community</a>
            </div>
        </div>
    </nav>
    <?php endif; ?>

    <div class="container-fluid p-0">
        <div class="row g-0">
            <?php if ($isLoggedIn): ?>
            <!-- Fixed Navigation Sidebar -->
            <nav class="col-md-3 col-lg-2 sidebar p-4">
                <div class="d-flex align-items-center mb-5 ps-2">
                    <img src="assets/img/logo.png" alt="Logo" style="height: 45px;" class="me-3">
                    <h6 class="fw-bold text-success mb-0" style="letter-spacing: -0.5px; line-height: 1.1;">HEALING<br>COMPASS</h6>
                </div>
                
                <div class="nav-menu">
                    <a href="dashboard.php" class="sidebar-link">
                        <i data-lucide="layout-grid"></i> Home
                    </a>

                    <a href="dashboard.php" class="sidebar-link">
                        <i data-lucide="sprout"></i> Medicinal Plants
                    </a>

                    <a href="healers.php" class="sidebar-link active">
                        <i data-lucide="users"></i> Healers
                    </a>

                    <a href="map.php" class="sidebar-link">
                        <i data-lucide="map-pin"></i> Healing Map
                    </a>
                    
                    <?php if (isset($role) && $role === 'admin'): ?>
                        <div class="mt-5 mb-3 small text-muted text-uppercase fw-bold ps-3" style="font-size: 0.65rem; letter-spacing: 1px;">Management</div>
                        <a href="admin-dashboard.php" class="sidebar-link">
                            <i data-lucide="shield-check"></i> Admin Panel
                        </a>
                        <a href="admin-plants.php" class="sidebar-link">
                            <i data-lucide="settings"></i> Manage Plants
                        </a>
                    <?php endif; ?>
                </div>

                <div class="mt-auto" style="position: absolute; bottom: 30px; left: 20px; right: 20px;">
                    <a href="controllers/AuthController.php?action=logout" class="sidebar-link text-danger">
                        <i data-lucide="log-out"></i> Logout
                    </a>
                </div>
            </nav>
            <?php endif; ?>

            <!-- Main Content -->
            <main class="<?= $isLoggedIn ? 'col-md-9 ms-sm-auto col-lg-10 px-md-5' : 'container' ?> py-5">

                <!-- Page Header -->
                <div class="mb-4">
                    <h1 class="fw-bold mb-1" style="font-size: 2.4rem; letter-spacing: -1.5px; color: var(--nature-forest);">Healer Directory</h1>
                    <p class="text-muted fw-medium mb-0"><?= count($healers) ?> verified traditional practitioners in your area</p>
                </div>

                <!-- Search + Filter Row -->
                <div class="mb-4">
                    <form action="healers.php" method="GET" class="d-flex gap-3 align-items-center mb-3">
                        <div class="flex-grow-1 position-relative">
                            <i data-lucide="search" class="position-absolute text-muted" style="width:18px; top: 50%; left: 18px; transform: translateY(-50%); pointer-events:none;"></i>
                            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>"
                                placeholder="Search by name, specialty, or location..."
                                class="form-control rounded-pill border-0 shadow-sm ps-5"
                                style="height: 52px; background: white; font-size: 0.95rem; color: var(--nature-forest);">
                        </div>
                        <button type="submit" class="btn btn-success rounded-pill px-4 fw-bold shadow-sm" style="background: var(--nature-forest); border: none; height: 52px;">
                            Search
                        </button>
                    </form>

                    <!-- Filter Chips -->
                    <div class="filter-chips-container">
                        <a href="healers.php" class="filter-chip <?= !$search ? 'active' : '' ?> text-decoration-none">All</a>
                        <a href="healers.php?search=Hilot" class="filter-chip text-decoration-none">Hilot</a>
                        <a href="healers.php?search=Herbalist" class="filter-chip text-decoration-none">Herbalist</a>
                        <a href="healers.php?search=Midwife" class="filter-chip text-decoration-none">Midwife</a>
                        <a href="healers.php?search=Bentusa" class="filter-chip text-decoration-none">Bentusa</a>
                        <a href="healers.php?search=Homeopathy" class="filter-chip text-decoration-none">Homeopathy</a>
                        <a href="healers.php?search=Acupuncture" class="filter-chip text-decoration-none">Acupuncture</a>
                    </div>
                </div>

                <!-- Healers Grid -->
                <div class="row g-4">
                    <?php if (empty($healers)): ?>
                        <div class="col-12 text-center py-5">
                            <div class="bg-white rounded-4 p-5 shadow-sm">
                                <i data-lucide="user-x" class="text-muted mb-3" style="width: 60px; height: 60px; opacity: 0.3;"></i>
                                <h4 class="fw-bold" style="color: var(--nature-forest);">No Healers Found</h4>
                                <p class="text-muted small mb-4">Try a different keyword like "Herbalist" or "Hilot".</p>
                                <a href="healers.php" class="btn btn-success rounded-pill px-4" style="background: var(--nature-forest); border: none;">Clear Filters</a>
                            </div>
                        </div>
                    <?php else: ?>
                        <?php foreach ($healers as $healer):
                            // Parse specialization into tags
                            $specTags = preg_split('/[,\/\|\n]+/', $healer['specialization'] ?? '');
                            $specTags = array_slice(array_filter(array_map('trim', $specTags)), 0, 3);
                        ?>
                            <div class="col-md-6 col-lg-4">
                                <div class="bg-white rounded-4 shadow-sm overflow-hidden h-100 d-flex flex-column" style="border: 1px solid rgba(0,0,0,0.03); transition: all 0.3s ease;" onmouseover="this.style.transform='translateY(-6px)';this.style.boxShadow='0 20px 40px rgba(45,79,50,0.10)'" onmouseout="this.style.transform='';this.style.boxShadow=''">
                                    <!-- Hero Image -->
                                    <div class="position-relative" style="height: 220px; overflow: hidden;">
                                        <div style="width: 100%; height: 100%; background: url('<?= htmlspecialchars($healer['profile_picture'] ?? 'https://cdn-icons-png.flaticon.com/512/3135/3135715.png') ?>') center/cover no-repeat; background-color: var(--nature-accent);"></div>
                                        <!-- Badges overlay -->
                                        <div class="position-absolute top-0 start-0 p-3 d-flex gap-2">
                                            <span class="badge rounded-pill px-3 py-2 fw-semibold" style="background: var(--nature-forest); font-size: 0.68rem;">
                                                <i data-lucide="shield-check" style="width: 11px; height: 11px; margin-right: 4px;"></i> Verified
                                            </span>
                                        </div>
                                        <?php if (!empty($healer['years_of_experience'])): ?>
                                        <div class="position-absolute top-0 end-0 p-3">
                                            <span class="badge rounded-pill px-3 py-2 fw-bold" style="background: rgba(255,255,255,0.95); color: var(--nature-forest); font-size: 0.68rem; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                                                <?= htmlspecialchars($healer['years_of_experience']) ?> Yrs XP
                                            </span>
                                        </div>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Card Body -->
                                    <div class="p-4 d-flex flex-column flex-grow-1">
                                        <!-- Name -->
                                        <h5 class="fw-bold mb-1" style="color: var(--nature-forest);">
                                            <?= htmlspecialchars($healer['full_name']) ?>
                                        </h5>

                                        <!-- Location -->
                                        <div class="d-flex align-items-center text-muted small mb-3">
                                            <i data-lucide="map-pin" style="width: 13px; height: 13px; margin-right: 5px; color: var(--nature-forest);"></i>
                                            <span><?= htmlspecialchars($healer['location_name'] ?? 'Location not specified') ?></span>
                                        </div>

                                        <!-- Specialty Tags -->
                                        <div class="mb-3">
                                            <?php foreach ($specTags as $tag): ?>
                                                <span class="badge rounded-pill me-1 mb-1 fw-semibold" style="background: var(--nature-accent); color: var(--nature-forest); padding: 6px 12px; font-size: 0.7rem;">
                                                    <?= htmlspecialchars($tag) ?>
                                                </span>
                                            <?php endforeach; ?>
                                        </div>

                                        <!-- Brief Description -->
                                        <?php if ($healer['description']): ?>
                                        <p class="text-muted small lh-base mb-3 text-truncate-2 flex-grow-1">
                                            <?= htmlspecialchars($healer['description']) ?>
                                        </p>
                                        <?php endif; ?>

                                        <!-- Divider -->
                                        <hr class="my-3" style="border-color: rgba(0,0,0,0.05);">

                                        <!-- Action Buttons -->
                                        <?php if (isset($role) && $role === 'admin'): ?>
                                            <div class="d-flex gap-2">
                                                <a href="admin-healers.php?edit=<?= $healer['id'] ?>"
                                                   class="btn btn-outline-success flex-grow-1 rounded-pill py-2 fw-semibold" style="font-size: 0.85rem;">
                                                    <i data-lucide="pencil" style="width: 14px;"></i> Edit
                                                </a>
                                                <button onclick="confirmDelete(<?= $healer['id'] ?>)"
                                                    class="btn btn-outline-danger flex-grow-1 rounded-pill py-2 fw-semibold" style="font-size: 0.85rem;">
                                                    <i data-lucide="trash-2" style="width: 14px;"></i> Delete
                                                </button>
                                            </div>
                                        <?php else: ?>
                                            <a href="<?= $isLoggedIn ? 'booking.php?healer_id='.$healer['id'] : 'login.php' ?>"
                                               class="btn w-100 rounded-pill py-3 fw-bold" style="background: var(--nature-forest); color: white; border: none; font-size: 0.9rem;">
                                                <?= $isLoggedIn ? 'Request Consultation' : 'Login to Consult' ?>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <?php if (!$isLoggedIn): ?>
                <div class="mt-5 p-5 text-center rounded-4 bg-white shadow-sm" style="border: 2px dashed var(--nature-accent);">
                    <h4 class="fw-bold mb-2" style="color: var(--nature-forest);">Discover More Practitioners</h4>
                    <p class="text-muted mb-4">Create a free account to unlock full profiles, interactive maps, and Aura AI guidance.</p>
                    <a href="register.php" class="btn btn-success rounded-pill px-5 py-3 shadow-sm fw-bold" style="background: var(--nature-forest); border: none;">Join Free</a>
                </div>
                <?php endif; ?>

            </main>
        </div>
    </div>


    <?php if (!$isLoggedIn): ?>
    <footer class="py-5 bg-white border-top">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <h5 class="fw-bold text-success mb-2">HEALING COMPASS</h5>
                    <p class="text-muted small mb-0">Providing transparent access to verified traditional practitioners since 2026.</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <p class="text-muted small mb-0">&copy; 2026 CHOCOBOL Platform. All rights reserved.</p>
                </div>
            </div>
        </div>
    </footer>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Initialize Lucide Icons
        lucide.createIcons();

        function confirmDelete(id) {
            Swal.fire({
                title: 'Are you sure?',
                text: "This will permanently remove the healer and their account.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#2d6a4f',
                confirmButtonText: 'Yes, remove them',
                background: '#fff',
                color: '#2d6a4f'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = `healers.php?action=delete&id=${id}`;
                }
            })
        }

        // Show alerts based on URL
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('delete') === 'success') {
            Swal.fire({
                title: 'Profile Removed',
                text: 'The healer records have been purged successfully.',
                icon: 'success',
                confirmButtonColor: '#2d6a4f'
            });
        }
        if (urlParams.has('error')) {
            Swal.fire({
                title: 'Action Blocked',
                text: urlParams.get('error'),
                icon: 'error',
                confirmButtonColor: '#2d6a4f'
            });
        }
    </script>
</body>
</html>

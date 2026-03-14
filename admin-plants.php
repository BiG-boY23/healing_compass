<?php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$role = $_SESSION['role'];
$username = $_SESSION['username'];

// Fetch plants
try {
    $stmt = $pdo->query("SELECT * FROM plants ORDER BY created_at DESC");
    $plants = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Fetch barangays for dropdown
    $barangays = $pdo->query("SELECT barangay_name FROM barangays ORDER BY barangay_name ASC")->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    $plants = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Plants | Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Lexend:wght@300;400;500;600;700;800&family=Outfit:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="dashboard-app">

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
                    <a href="admin-management.php" class="sidebar-link">
                        <i data-lucide="user-cog"></i> User Management
                    </a>
                    <a href="admin-healers.php" class="sidebar-link">
                        <i data-lucide="users"></i> Manage Healers
                    </a>
                    <a href="admin-plants.php" class="sidebar-link active">
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

            <!-- Main Content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-5 py-4">
                <div class="d-flex justify-content-between align-items-center mb-5">
                    <div>
                        <h2 class="fw-bold text-success mb-1">Medicinal Plant Archive</h2>
                        <p class="text-muted mb-0">Manage and verify herbal botanical records</p>
                    </div>
                    <button class="btn btn-success rounded-pill px-4 shadow-sm" data-bs-toggle="modal" data-bs-target="#addPlantModal">
                        <i class="bi bi-plus-lg me-2 text-white"></i> Add New Plant
                    </button>
                </div>

                <div class="dash-tile p-4 overflow-hidden shadow-sm border bg-white">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="bg-light">
                                <tr>
                                    <th class="border-0 px-3">Species Image</th>
                                    <th class="border-0 px-3">Botanical Name</th>
                                    <th class="border-0 px-3">Scientific Profile</th>
                                    <th class="border-0 px-3">Primary Use</th>
                                    <th class="border-0 px-3">Verification</th>
                                    <th class="border-0 px-3 text-end">Management</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($plants as $plant): ?>
                                <tr>
                                    <td class="px-3">
                                        <div class="rounded-3 shadow-sm" style="width: 50px; height: 50px; background: url('<?= !empty($plant['plant_image']) ? $plant['plant_image'] : 'https://images.unsplash.com/photo-1596131397999-bb013f9eee17?w=100' ?>') center/cover no-repeat;"></div>
                                    </td>
                                    <td class="px-3 fw-bold text-success"><?= htmlspecialchars($plant['plant_name']) ?></td>
                                    <td class="px-3 text-muted fst-italic"><?= htmlspecialchars($plant['scientific_name']) ?></td>
                                    <td class="px-3 small text-truncate" style="max-width: 200px;"><?= htmlspecialchars($plant['illness_treated']) ?></td>
                                    <td class="px-3">
                                        <span class="badge border py-2 px-3 <?= $plant['is_approved'] ? 'bg-success bg-opacity-10 text-success' : 'bg-warning bg-opacity-10 text-warning' ?>">
                                            <i class="bi <?= $plant['is_approved'] ? 'bi-patch-check-fill' : 'bi-hourglass-split' ?> me-2"></i>
                                            <?= $plant['is_approved'] ? 'Verified' : 'Review Required' ?>
                                        </span>
                                    </td>
                                    <td class="px-3 text-end">
                                        <div class="btn-group shadow-sm rounded-pill overflow-hidden">
                                            <button class="btn btn-sm btn-light border-end edit-plant-btn" 
                                                    data-id="<?= $plant['id'] ?>"
                                                    data-name="<?= htmlspecialchars($plant['plant_name']) ?>"
                                                    data-scientific="<?= htmlspecialchars($plant['scientific_name']) ?>"
                                                    data-desc="<?= htmlspecialchars($plant['description']) ?>"
                                                    data-illness="<?= htmlspecialchars($plant['illness_treated']) ?>"
                                                    data-prep="<?= htmlspecialchars($plant['preparation_method']) ?>"
                                                    data-barangay="<?= htmlspecialchars($plant['barangay']) ?>"
                                                    data-approved="<?= $plant['is_approved'] ?>"
                                                    data-image="<?= $plant['plant_image'] ?>"
                                                    title="Edit Species Info">
                                                <i class="bi bi-pencil-square text-success"></i>
                                            </button>
                                            <button class="btn btn-sm btn-light delete-plant-btn" 
                                                    data-id="<?= $plant['id'] ?>"
                                                    data-name="<?= htmlspecialchars($plant['plant_name']) ?>"
                                                    title="Archive Entry">
                                                <i class="bi bi-trash3 text-danger"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($plants)): ?>
                                    <tr><td colspan="6" class="text-center py-5 text-muted">No botanical records found in the archive.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Modal for adding plant -->
    <div class="modal fade" id="addPlantModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content border-0 rounded-5 overflow-hidden">
                <div class="modal-header bg-success p-4 border-0">
                    <h5 class="modal-title fw-bold text-white">Register Herbal Botanical</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form action="controllers/PlantController.php" method="POST" enctype="multipart/form-data">
                    <div class="modal-body p-5">
                        <input type="hidden" name="action" value="add">
                        <div class="row g-4">
                            <div class="col-md-6">
                                <label class="form-label fw-bold small text-muted">Botanical/Common Name</label>
                                <input type="text" name="plant_name" class="form-control rounded-3 py-3 shadow-sm border-light bg-light" placeholder="e.g. Aloe Vera" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold small text-muted">Scientific Nomenclature</label>
                                <input type="text" name="scientific_name" class="form-control rounded-3 py-3 shadow-sm border-light bg-light" placeholder="e.g. Aloe barbadensis">
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-bold small text-muted">Botanical Profile Description</label>
                                <textarea name="description" class="form-control rounded-3 py-3 shadow-sm border-light bg-light" rows="3" placeholder="Describe the plant's characteristics..."></textarea>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold small text-muted">Ailments Treated</label>
                                <input type="text" name="illness_treated" class="form-control rounded-3 py-3 shadow-sm border-light bg-light" placeholder="e.g. Burns, Indigestion">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold small text-muted">Traditional Preparation</label>
                                <input type="text" name="preparation_method" class="form-control rounded-3 py-3 shadow-sm border-light bg-light" placeholder="e.g. Extract gel directly...">
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-bold small text-muted">Found in Barangay</label>
                                <select name="barangay" class="form-select rounded-3 py-3 shadow-sm border-light bg-light">
                                    <option value="" selected>General / Local Wild</option>
                                    <?php foreach ($barangays as $bg): ?>
                                        <option value="<?= htmlspecialchars($bg) ?>"><?= htmlspecialchars($bg) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-bold small text-muted">Botanical Image (Clear Shot)</label>
                                <input type="file" name="plant_image" class="form-control rounded-3 py-3 shadow-sm border-light bg-light">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer p-4 border-0 bg-light">
                        <button type="button" class="btn btn-outline-secondary border-0 px-4" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success rounded-pill px-5 shadow-sm fw-bold">Commit to Database</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal for editing plant -->
    <div class="modal fade" id="editPlantModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content border-0 rounded-5 overflow-hidden">
                <div class="modal-header bg-success p-4 border-0">
                    <h5 class="modal-title fw-bold text-white">Modify Botanical Record</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form action="controllers/PlantController.php" method="POST" enctype="multipart/form-data">
                    <div class="modal-body p-5">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="id" id="edit_id">
                        <div class="row g-4">
                            <div class="col-md-6">
                                <label class="form-label fw-bold small text-muted">Botanical/Common Name</label>
                                <input type="text" name="plant_name" id="edit_name" class="form-control rounded-3 py-3 shadow-sm border-light bg-light" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold small text-muted">Scientific Nomenclature</label>
                                <input type="text" name="scientific_name" id="edit_scientific" class="form-control rounded-3 py-3 shadow-sm border-light bg-light">
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-bold small text-muted">Botanical Profile Description</label>
                                <textarea name="description" id="edit_desc" class="form-control rounded-3 py-3 shadow-sm border-light bg-light" rows="3"></textarea>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold small text-muted">Ailments Treated</label>
                                <input type="text" name="illness_treated" id="edit_illness" class="form-control rounded-3 py-3 shadow-sm border-light bg-light">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold small text-muted">Traditional Preparation</label>
                                <input type="text" name="preparation_method" id="edit_prep" class="form-control rounded-3 py-3 shadow-sm border-light bg-light">
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-bold small text-muted">Found in Barangay</label>
                                <select name="barangay" id="edit_barangay" class="form-select rounded-3 py-3 shadow-sm border-light bg-light">
                                    <option value="">General / Local Wild</option>
                                    <?php foreach ($barangays as $bg): ?>
                                        <option value="<?= htmlspecialchars($bg) ?>"><?= htmlspecialchars($bg) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-8">
                                <label class="form-label fw-bold small text-muted">Update Botanical Image</label>
                                <input type="file" name="plant_image" class="form-control rounded-3 py-3 shadow-sm border-light bg-light">
                            </div>
                            <div class="col-md-4 d-flex align-items-center">
                                <div class="form-check form-switch mt-4">
                                    <input class="form-check-input" type="checkbox" name="is_approved" id="edit_approved">
                                    <label class="form-check-label fw-bold small text-success" for="edit_approved">Verified Status</label>
                                </div>
                            </div>
                            <div class="col-12 mt-3">
                                <p class="small text-muted mb-2">Current Image Preview:</p>
                                <div id="edit_image_preview" class="rounded-3 shadow-sm bg-light border d-flex align-items-center justify-content-center overflow-hidden" style="height: 150px; width: 150px;">
                                    <span class="text-muted">No Image</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer p-4 border-0 bg-light">
                        <button type="button" class="btn btn-outline-secondary border-0 px-4" data-bs-dismiss="modal">Cancel Changes</button>
                        <button type="submit" class="btn btn-success rounded-pill px-5 shadow-sm fw-bold">Update Archive</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Edit functionality
            const editBtns = document.querySelectorAll('.edit-plant-btn');
            const editModal = new bootstrap.Modal(document.getElementById('editPlantModal'));

            editBtns.forEach(btn => {
                btn.addEventListener('click', function() {
                    const id = this.getAttribute('data-id');
                    const name = this.getAttribute('data-name');
                    const scientific = this.getAttribute('data-scientific');
                    const desc = this.getAttribute('data-desc');
                    const illness = this.getAttribute('data-illness');
                    const prep = this.getAttribute('data-prep');
                    const barangay = this.getAttribute('data-barangay');
                    const approved = this.getAttribute('data-approved') === '1';
                    const image = this.getAttribute('data-image');

                    document.getElementById('edit_id').value = id;
                    document.getElementById('edit_name').value = name;
                    document.getElementById('edit_scientific').value = scientific;
                    document.getElementById('edit_desc').value = desc;
                    document.getElementById('edit_illness').value = illness;
                    document.getElementById('edit_prep').value = prep;
                    document.getElementById('edit_barangay').value = barangay || "";
                    document.getElementById('edit_approved').checked = approved;

                    const preview = document.getElementById('edit_image_preview');
                    if (image && image !== 'null') {
                        preview.innerHTML = `<img src="${image}" class="img-fluid h-100 w-100" style="object-fit: cover;">`;
                    } else {
                        preview.innerHTML = `<span class="text-muted">No Image</span>`;
                    }

                    editModal.show();
                });
            });

            // Delete functionality
            const deleteBtns = document.querySelectorAll('.delete-plant-btn');
            deleteBtns.forEach(btn => {
                btn.addEventListener('click', function() {
                    const id = this.getAttribute('data-id');
                    const name = this.getAttribute('data-name');

                    Swal.fire({
                        title: 'Are you sure?',
                        text: `You are about to archive the record for "${name}". This action cannot be undone immediately.`,
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#d33',
                        cancelButtonColor: '#264626',
                        confirmButtonText: 'Yes, archive it!',
                        cancelButtonText: 'Cancel'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            window.location.href = `controllers/PlantController.php?action=delete&id=${id}`;
                        }
                    });
                });
            });

            // Alerts based on URL success parameter
            const urlParams = new URLSearchParams(window.location.search);
            const success = urlParams.get('success');
            if (success) {
                let title = 'Success!';
                let text = 'Operation completed.';
                if (success === 'added') text = 'New botanical record added successfully.';
                if (success === 'updated') text = 'Botanical record updated successfully.';
                if (success === 'deleted') text = 'Botanical record archived successfully.';

                Swal.fire({
                    title: title,
                    text: text,
                    icon: 'success',
                    confirmButtonColor: '#264626'
                });
            }
        });
    </script>
</body>
</html>

<?php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$role = $_SESSION['role'];
$username = $_SESSION['username'];

// Fetch barangays for dropdown
try {
    $barangays = $pdo->query("SELECT barangay_name FROM barangays ORDER BY barangay_name ASC")->fetchAll(PDO::FETCH_COLUMN);
    // Fetch BHWs for assignment
    $stmtBHWs = $pdo->query("SELECT id, username, barangay FROM users WHERE role = 'bhw' ORDER BY username ASC");
    $bhwList = $stmtBHWs->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { 
    $barangays = []; 
    $bhwList = [];
}

// Handle Actions (Add/Edit/Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add' || $action === 'edit') {
        $fullName = $_POST['full_name'];
        $specialization = $_POST['specialization'];
        $locationName = $_POST['location_name'];
        $latitude = $_POST['latitude'];
        $longitude = $_POST['longitude'];
        $contactInfo = $_POST['contact_info'];
        $description = $_POST['description'];
        $methods = $_POST['treatment_methods'];
        $herbs = $_POST['herbs_used'];
        $years = (int)$_POST['years_of_experience'];
        $barangay = $_POST['barangay'] ?? '';
        $healerId = $_POST['healer_id'] ?? null;
        
        $profilePath = $_POST['existing_profile_picture'] ?? 'assets/img/avatar.png';

        // Handle Image Upload or Capture
        if (!empty($_POST['captured_image'])) {
            $data = $_POST['captured_image'];
            list($type, $data) = explode(';', $data);
            list(, $data)      = explode(',', $data);
            $data = base64_decode($data);
            $fileName = 'healer_' . time() . '.png';
            file_put_contents('assets/uploads/healers/' . $fileName, $data);
            $profilePath = 'assets/uploads/healers/' . $fileName;
        } elseif (isset($_FILES['profile_file']) && $_FILES['profile_file']['error'] === 0) {
            $ext = pathinfo($_FILES['profile_file']['name'], PATHINFO_EXTENSION);
            $fileName = 'healer_' . time() . '.' . $ext;
            if (move_uploaded_file($_FILES['profile_file']['tmp_name'], 'assets/uploads/healers/' . $fileName)) {
                $profilePath = 'assets/uploads/healers/' . $fileName;
            }
        }
        
        try {
            if ($action === 'add') {
                $bhwId = $_POST['managed_by_bhw_id'] ?: null;
                $stmtHealer = $pdo->prepare("INSERT INTO healers (full_name, specialization, location_name, latitude, longitude, contact_info, description, treatment_methods, herbs_used, years_of_experience, profile_picture, barangay, managed_by_bhw_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmtHealer->execute([$fullName, $specialization, $locationName, $latitude, $longitude, $contactInfo, $description, $methods, $herbs, $years, $profilePath, $barangay, $bhwId]);
                $success = "Healer profiled and assigned successfully!";
            } else {
                $bhwId = $_POST['managed_by_bhw_id'] ?: null;
                $stmt = $pdo->prepare("UPDATE healers SET full_name = ?, specialization = ?, location_name = ?, latitude = ?, longitude = ?, contact_info = ?, description = ?, treatment_methods = ?, herbs_used = ?, years_of_experience = ?, profile_picture = ?, barangay = ?, managed_by_bhw_id = ? WHERE id = ?");
                $stmt->execute([$fullName, $specialization, $locationName, $latitude, $longitude, $contactInfo, $description, $methods, $herbs, $years, $profilePath, $barangay, $bhwId, $healerId]);
                $success = "Healer profile updated!";
            }
        } catch (PDOException $e) {
            $error = "Database Error: " . $e->getMessage();
        }
    } elseif ($action === 'delete') {
        $healerId = $_POST['healer_id'];
        try {
            $pdo->prepare("DELETE FROM healers WHERE id = ?")->execute([$healerId]);
            $success = "Healer profile removed from system.";
        } catch (PDOException $e) {
            $error = "Delete error: " . $e->getMessage();
        }
    }
}

// Fetch healers with managing BHW info
try {
    $stmt = $pdo->query("SELECT healers.*, users.username as bhw_name FROM healers LEFT JOIN users ON healers.managed_by_bhw_id = users.id ORDER BY healers.id DESC");
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
    <title>Healer Profiling | Admin Dashboard</title>
    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Lexend:wght@300;400;500;600;700;800&family=Outfit:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <script src="https://unpkg.com/lucide@latest"></script>
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        #healerMap { height: 350px; border-radius: 15px; border: 2px solid #e9f5ef; margin-bottom: 20px; }
        .healer-form-card { border: none; border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.05); }
        .sidebar-link i { font-size: 1.1rem !important; }
        .btn-sm i { font-size: 0.85rem !important; }
        .badge { font-size: 0.75rem !important; font-weight: 600 !important; }
        .table img { width: 35px; height: 35px; }
        .sidebar-link { padding: 10px 15px !important; font-size: 0.95rem !important; }
        .dash-tile h5 { font-size: 1rem !important; }
    </style>
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
                    <a href="admin-healers.php" class="sidebar-link active">
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

            <!-- Main Content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-5 py-4">
                <div class="d-flex justify-content-between align-items-center mb-5">
                    <div>
                        <h2 class="fw-bold text-success mb-1">Healer Profiling</h2>
                        <p class="text-muted mb-0">Register and map verified traditional healers.</p>
                    </div>
                    <button class="btn btn-success rounded-pill px-4 shadow-sm" data-bs-toggle="modal" data-bs-target="#addHealerModal">
                        <i class="bi bi-plus-lg me-2"></i> Register New Healer
                    </button>
                </div>

                <?php if (isset($success)): ?>
                    <div class="alert alert-success border-0 rounded-4 shadow-sm mb-4"><?= $success ?></div>
                <?php endif; ?>
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger border-0 rounded-4 shadow-sm mb-4"><?= $error ?></div>
                <?php endif; ?>

                <div class="row g-4">
                    <div class="col-12">
                        <div class="dash-tile p-4 overflow-hidden shadow-sm border bg-white">
                            <h5 class="fw-bold text-success mb-4 text-start">Current Certified Healers</h5>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle">
                                    <thead class="bg-light">
                                        <tr>
                                            <th>Healer</th>
                                            <th>Barangay</th>
                                            <th>Managed By</th>
                                            <th>Specialization</th>
                                            <th class="text-end">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($healers)): ?>
                                            <tr><td colspan="6" class="text-center py-4 text-muted small">No healers registered yet.</td></tr>
                                        <?php else: ?>
                                            <?php foreach ($healers as $h): ?>
                                            <tr>
                                                <td>
                                                    <div class="d-flex align-items-center text-start">
                                                        <img src="<?= htmlspecialchars($h['profile_picture'] ?? 'assets/img/avatar.png') ?>" class="rounded-circle me-3" style="width: 40px; height: 40px; object-fit: cover;">
                                                        <div>
                                                            <div class="fw-bold text-success"><?= htmlspecialchars($h['full_name']) ?></div>
                                                            <div class="small text-muted"><?= htmlspecialchars($h['contact_info']) ?></div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="small fw-semibold"><?= htmlspecialchars($h['barangay']) ?></td>
                                                <td>
                                                    <div class="small text-dark">
                                                        <?php if ($h['bhw_name']): ?>
                                                            <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill"><i class="bi bi-person-badge me-1"></i><?= htmlspecialchars($h['bhw_name']) ?></span>
                                                        <?php else: ?>
                                                            <span class="badge bg-danger bg-opacity-10 text-danger rounded-pill">Unassigned</span>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                                <td class="small"><?= htmlspecialchars($h['specialization']) ?></td>
                                                <td class="text-end">
                                                    <button class="btn btn-sm btn-light rounded-circle shadow-sm me-1" onclick='editHealer(<?= json_encode($h) ?>)' title="Edit Profile"><i class="bi bi-pencil text-success"></i></button>
                                                    <button class="btn btn-sm btn-light rounded-circle shadow-sm" onclick="confirmHealerDelete(<?= $h['id'] ?>)" title="Remove Healer"><i class="bi bi-trash text-danger"></i></button>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Add Healer Modal -->
    <div class="modal fade" id="addHealerModal" tabindex="-1">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content rounded-5 border-0 shadow-lg p-3">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold text-success display-6 ms-3" id="modalTitle">Register Professional Healer</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <form action="admin-healers.php" method="POST" id="healerForm" enctype="multipart/form-data">
                        <input type="hidden" name="action" id="formAction" value="add">
                        <input type="hidden" name="healer_id" id="healerIdInput">
                        <input type="hidden" name="existing_profile_picture" id="existingProfilePic">
                        <input type="hidden" name="captured_image" id="capturedImage">
                        <div class="row g-4">
                            <!-- Basic Info -->
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
                                    <div class="col-md-12 mb-3">
                                        <label class="form-label small fw-bold text-muted text-uppercase">Assigned Barangay</label>
                                        <select name="barangay" class="form-select" required>
                                            <option value="" disabled selected>Select Barangay...</option>
                                            <?php foreach ($barangays as $bg): ?>
                                                <option value="<?= htmlspecialchars($bg) ?>"><?= htmlspecialchars($bg) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-12 mb-3">
                                        <label class="form-label small fw-bold text-muted text-uppercase">Assign Managing BHW</label>
                                        <select name="managed_by_bhw_id" id="bhwSelect" class="form-select">
                                            <option value="">Unassigned (Open for BHW to claim)</option>
                                            <?php foreach ($bhwList as $bhw): ?>
                                                <option value="<?= $bhw['id'] ?>" data-barangay="<?= htmlspecialchars($bhw['barangay']) ?>">
                                                    <?= htmlspecialchars($bhw['username']) ?> (<?= htmlspecialchars($bhw['barangay']) ?>)
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>

                                <!-- Image Selection UI -->
                                <div class="mb-3">
                                    <label class="form-label small fw-bold text-muted text-uppercase">Profile Identity (Photo)</label>
                                    <div class="d-flex gap-2 mb-2">
                                        <button type="button" class="btn btn-sm btn-outline-success flex-grow-1" id="startCam">
                                            <i class="bi bi-camera me-1"></i> Take Photo
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-primary flex-grow-1" id="triggerFile">
                                            <i class="bi bi-upload me-1"></i> Upload File
                                        </button>
                                    </div>
                                    <input type="file" name="profile_file" id="profileFile" class="d-none" accept="image/*">
                                    
                                    <!-- Camera Preview Container -->
                                    <div id="cameraContainer" class="d-none mb-2">
                                        <video id="video" width="100%" height="auto" autoplay class="rounded-4 bg-dark"></video>
                                        <button type="button" class="btn btn-success btn-sm w-100 mt-2" id="captureBtn">
                                            <i class="bi bi-camera-fill me-2"></i> Capture Now
                                        </button>
                                    </div>

                                    <!-- Preview Display -->
                                    <div id="imagePreview" class="text-center p-2 bg-light rounded-4 d-none">
                                        <img id="previewImg" src="" class="img-fluid rounded-4 shadow-sm" style="max-height: 200px;">
                                        <div class="mt-2 small text-success fw-bold"><i class="bi bi-check-circle me-1"></i> Image Selected</div>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label small fw-bold text-muted text-uppercase">Treatment Methods</label>
                                    <textarea name="treatment_methods" class="form-control" rows="2" placeholder="Describe the therapeutic techniques used..."></textarea>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label small fw-bold text-muted text-uppercase">Herbs / Plants Utilized</label>
                                    <textarea name="herbs_used" class="form-control" rows="2" placeholder="List key botanical components..."></textarea>
                                </div>
                                <div class="mb-0">
                                    <label class="form-label small fw-bold text-muted text-uppercase">Biography / Background</label>
                                    <textarea name="description" class="form-control" rows="3" placeholder="Brief history of the tradition or background..."></textarea>
                                </div>
                            </div>

                                <!-- Map and Location -->
                                <div class="col-md-7">
                                    <div class="mb-3">
                                        <label class="form-label small fw-bold text-muted text-uppercase">Practice Location Name</label>
                                        <div class="input-group">
                                            <input type="text" name="location_name" id="locName" class="form-control" placeholder="e.g. Brgy. Don Felipe, Ormoc City" required>
                                            <button class="btn btn-outline-success" type="button" id="searchLocBtn">
                                                <i class="bi bi-geo-fill"></i> Locate
                                            </button>
                                        </div>
                                    </div>
                                    <p class="text-muted small mb-2"><i class="bi bi-info-circle me-2"></i>Type a location and click <strong>Locate</strong>, or click directly on the map.</p>
                                <div id="healerMap"></div>
                                <div class="row">
                                    <div class="col-6">
                                        <input type="text" name="latitude" id="lat" class="form-control form-control-sm bg-light" placeholder="Latitude" readonly required>
                                    </div>
                                    <div class="col-6">
                                        <input type="text" name="longitude" id="lng" class="form-control form-control-sm bg-light" placeholder="Longitude" readonly required>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="text-end mt-4">
                            <button type="button" class="btn btn-light rounded-pill px-4 me-2" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-success rounded-pill px-5 shadow-sm">Save & Register Profile</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Leaflet JS -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>lucide.createIcons();</script>
    <script>
        // Map Initialization
        let map = L.map('healerMap').setView([11.2407, 124.9961], 13);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap contributors'
        }).addTo(map);

        let marker;
        map.on('click', function(e) {
            if (marker) map.removeLayer(marker);
            marker = L.marker(e.latlng).addTo(map);
            document.getElementById('lat').value = e.latlng.lat;
            document.getElementById('lng').value = e.latlng.lng;
        });

        // Ensure map renders correctly in modal
        const modal = document.getElementById('addHealerModal');
        modal.addEventListener('shown.bs.modal', function () {
            map.invalidateSize();
        });

        // --- Geocoding Logic ---
        const searchLocBtn = document.getElementById('searchLocBtn');
        const locNameInput = document.getElementById('locName');

        async function searchLocation() {
            const query = locNameInput.value;
            if (!query) return;

            searchLocBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
            searchLocBtn.disabled = true;

            try {
                const response = await fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(query)}`);
                const data = await response.json();

                if (data.length > 0) {
                    const { lat, lon } = data[0];
                    const latlng = [parseFloat(lat), parseFloat(lon)];
                    
                    map.setView(latlng, 16);
                    
                    if (marker) map.removeLayer(marker);
                    marker = L.marker(latlng).addTo(map);
                    
                    document.getElementById('lat').value = lat;
                    document.getElementById('lng').value = lon;
                } else {
                    alert("Location not found. Please try adding city or province name.");
                }
            } catch (err) {
                console.error("Geocoding error:", err);
            } finally {
                searchLocBtn.innerHTML = '<i class="bi bi-geo-fill"></i> Locate';
                searchLocBtn.disabled = false;
            }
        }

        searchLocBtn.addEventListener('click', searchLocation);
        locNameInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                searchLocation();
            }
        });

        // --- Camera & Upload Logic ---
        const video = document.getElementById('video');
        const startCamBtn = document.getElementById('startCam');
        const captureBtn = document.getElementById('captureBtn');
        const cameraContainer = document.getElementById('cameraContainer');
        const imagePreview = document.getElementById('imagePreview');
        const previewImg = document.getElementById('previewImg');
        const capturedInput = document.getElementById('capturedImage');
        const fileInput = document.getElementById('profileFile');
        const triggerFileBtn = document.getElementById('triggerFile');

        // File Selection Preview
        triggerFileBtn.addEventListener('click', () => fileInput.click());
        fileInput.addEventListener('change', function(e) {
            if (this.files && this.files[0]) {
                const reader = new FileReader();
                reader.onload = function(ex) {
                    previewImg.src = ex.target.result;
                    imagePreview.classList.remove('d-none');
                    cameraContainer.classList.add('d-none');
                    capturedInput.value = ''; // Clear captured if uploading
                }
                reader.readAsDataURL(this.files[0]);
            }
        });

        // Camera Management
        startCamBtn.addEventListener('click', async () => {
            try {
                const stream = await navigator.mediaDevices.getUserMedia({ video: true });
                video.srcObject = stream;
                cameraContainer.classList.remove('d-none');
                imagePreview.classList.add('d-none');
                fileInput.value = ''; // Clear file if capture
            } catch (err) {
                alert("Could not access camera: " + err);
            }
        });

        captureBtn.addEventListener('click', () => {
            const canvas = document.createElement('canvas');
            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
            canvas.getContext('2d').drawImage(video, 0, 0);
            
            const dataUrl = canvas.toDataURL('image/png');
            previewImg.src = dataUrl;
            capturedInput.value = dataUrl;
            
            imagePreview.classList.remove('d-none');
            cameraContainer.classList.add('d-none');
            
            // Stop camera stream
            const stream = video.srcObject;
            const tracks = stream.getTracks();
            tracks.forEach(t => t.stop());
        });

        // --- Edit / Delete Functions ---
        function editHealer(data) {
            document.getElementById('modalTitle').innerText = "Edit Healer Profile";
            document.getElementById('formAction').value = "edit";
            document.getElementById('healerIdInput').value = data.id;
            document.getElementById('existingProfilePic').value = data.profile_picture;
            
            // Populate Fields
            const form = document.getElementById('healerForm');
            form.full_name.value = data.full_name;
            form.specialization.value = data.specialization;
            form.years_of_experience.value = data.years_of_experience;
            form.contact_info.value = data.contact_info;
            form.treatment_methods.value = data.treatment_methods;
            form.herbs_used.value = data.herbs_used;
            form.description.value = data.description;
            form.location_name.value = data.location_name;
            form.latitude.value = data.latitude;
            form.longitude.value = data.longitude;
            form.barangay.value = data.barangay || "";
            form.managed_by_bhw_id.value = data.managed_by_bhw_id || "";

            // Update Map
            const latlng = [parseFloat(data.latitude), parseFloat(data.longitude)];
            map.setView(latlng, 16);
            if (marker) map.removeLayer(marker);
            marker = L.marker(latlng).addTo(map);

            // Show Preview
            if (data.profile_picture) {
                previewImg.src = data.profile_picture;
                imagePreview.classList.remove('d-none');
            }

            const modal = new bootstrap.Modal(document.getElementById('addHealerModal'));
            modal.show();
        }

        function confirmHealerDelete(id) {
            Swal.fire({
                title: 'Confirm Full Removal',
                text: "Deleting this healer profile will also remove all their associated appointment records from the BHW system. This action cannot be undone.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#2d6a4f',
                confirmButtonText: 'Yes, Delete Everything'
            }).then((result) => {
                if (result.isConfirmed) {
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = 'admin-healers.php';
                    
                    const actionInput = document.createElement('input');
                    actionInput.type = 'hidden';
                    actionInput.name = 'action';
                    actionInput.value = 'delete';
                    
                    const idInput = document.createElement('input');
                    idInput.type = 'hidden';
                    idInput.name = 'healer_id';
                    idInput.value = id;
                    
                    form.appendChild(actionInput);
                    form.appendChild(idInput);
                    document.body.appendChild(form);
                    form.submit();
                }
            });
        }

        // Reset modal on close
        document.getElementById('addHealerModal').addEventListener('hidden.bs.modal', function () {
            document.getElementById('healerForm').reset();
            document.getElementById('modalTitle').innerText = "Register Professional Healer";
            document.getElementById('formAction').value = "add";
            document.getElementById('imagePreview').classList.add('d-none');
            document.getElementById('cameraContainer').classList.add('d-none');
            if (marker) map.removeLayer(marker);
            map.setView([11.2407, 124.9961], 13);
        });
    </script>
</body>
</html>

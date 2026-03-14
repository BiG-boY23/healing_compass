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

// Handle Actions (Edit/Verify/Add)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'verify_healer') {
        $healer_id = (int)$_POST['healer_id'];
        try {
            $stmt = $pdo->prepare("UPDATE healers SET is_verified = 1, managed_by_bhw_id = ? WHERE id = ? AND barangay = ?");
            $stmt->execute([$user_id, $healer_id, $barangay]);
            $success = "Healer verified and added to your management list.";
        } catch (PDOException $e) { $error = $e->getMessage(); }
    }
    if ($action === 'toggle_availability') {
        $healer_id = (int)$_POST['healer_id'];
        $status = (int)$_POST['status'];
        try {
            $stmt = $pdo->prepare("UPDATE healers SET is_available = ? WHERE id = ? AND managed_by_bhw_id = ?");
            $stmt->execute([$status, $healer_id, $user_id]);
            $success = "Healer availability updated.";
        } catch (PDOException $e) { $error = $e->getMessage(); }
    }

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
                $stmtHealer = $pdo->prepare("INSERT INTO healers (full_name, specialization, location_name, latitude, longitude, contact_info, description, treatment_methods, herbs_used, years_of_experience, profile_picture, barangay, managed_by_bhw_id, is_verified) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)");
                $stmtHealer->execute([$fullName, $specialization, $locationName, $latitude, $longitude, $contactInfo, $description, $methods, $herbs, $years, $profilePath, $barangay, $user_id]);
                $success = "Healer registered and added to your managed list successfully!";
            } else {
                $stmt = $pdo->prepare("UPDATE healers SET full_name = ?, specialization = ?, location_name = ?, latitude = ?, longitude = ?, contact_info = ?, description = ?, treatment_methods = ?, herbs_used = ?, years_of_experience = ?, profile_picture = ? WHERE id = ? AND barangay = ? AND managed_by_bhw_id = ?");
                $stmt->execute([$fullName, $specialization, $locationName, $latitude, $longitude, $contactInfo, $description, $methods, $herbs, $years, $profilePath, $healerId, $barangay, $user_id]);
                $success = "Healer profile updated!";
            }
        } catch (PDOException $e) {
            $error = "Database Error: " . $e->getMessage();
        }
    }
}

// Fetch healers in this barangay
try {
    $stmt = $pdo->prepare("SELECT * FROM healers WHERE barangay = ? ORDER BY is_verified ASC, full_name ASC");
    $stmt->execute([$barangay]);
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
    <title>Healer Management | BHW Dashboard</title>
    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
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
        #healerMap { height: 350px; border-radius: 15px; border: 2px solid var(--bhw-accent); margin-bottom: 20px; }
        .bhw-badge-pending { background: rgba(255,152,0,0.1); color: #F57C00; font-weight: 700; }
        .bhw-badge-verified { background: rgba(76,175,80,0.1); color: #388E3C; font-weight: 700; }
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
                <a href="bhw-healers.php" class="sidebar-link active">
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
            <div class="d-flex justify-content-between align-items-center mb-5">
                <div>
                    <h1 class="fw-bold mb-1" style="font-size: 2.2rem; letter-spacing: -1px; color: var(--bhw-text);">Healer Verification</h1>
                    <p class="text-muted fw-medium mb-0">Managing healers in <strong><?= htmlspecialchars($barangay) ?></strong></p>
                </div>
                <button class="btn btn-primary rounded-pill px-4 shadow-sm" data-bs-toggle="modal" data-bs-target="#healerModal">
                    <i data-lucide="user-plus" class="me-2" style="width: 18px;"></i> Register New Healer
                </button>
            </div>

            <div class="card border-0 rounded-4 shadow-sm p-4">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr style="font-size: 0.75rem; text-transform: uppercase;">
                                <th>Healer</th>
                                <th>Specialization</th>
                                <th>Managed By</th>
                                <th>Status</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($healers)): ?>
                                <tr>
                                    <td colspan="4" class="text-center py-5 text-muted">
                                        <div class="mb-3"><i data-lucide="user-plus" style="width: 48px; opacity: 0.2;"></i></div>
                                        <h5 class="fw-bold">No healers registered yet</h5>
                                        <p class="small">Start by registering traditional healers in <strong><?= htmlspecialchars($barangay) ?></strong> to begin the verification process.</p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($healers as $h): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <img src="<?= htmlspecialchars($h['profile_picture'] ?? 'assets/img/avatar.png') ?>" class="rounded-circle me-3" style="width: 40px; height: 40px; object-fit: cover;">
                                            <div>
                                                <div class="fw-bold text-dark"><?= htmlspecialchars($h['full_name']) ?></div>
                                                <small class="text-muted"><?= htmlspecialchars($h['location_name']) ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="small fw-semibold"><?= htmlspecialchars($h['specialization']) ?></td>
                                    <td>
                                        <?php if ($h['managed_by_bhw_id'] == $user_id): ?>
                                            <span class="badge bg-primary bg-opacity-10 text-primary px-3 py-2 rounded-pill small">Me (BHW)</span>
                                        <?php elseif ($h['managed_by_bhw_id']): ?>
                                            <span class="badge bg-secondary bg-opacity-10 text-secondary px-3 py-2 rounded-pill small">Other BHW</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger bg-opacity-10 text-danger px-3 py-2 rounded-pill small">Unmanaged</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($h['is_available']): ?>
                                            <span class="badge rounded-pill bg-success-subtle text-success border border-success-subtle px-3 py-2">Available</span>
                                        <?php else: ?>
                                            <span class="badge rounded-pill bg-danger-subtle text-danger border border-danger-subtle px-3 py-2">Offline</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <?php if (!$h['managed_by_bhw_id']): ?>
                                            <form method="POST" class="d-inline" onsubmit="return confirm('Claim management for this healer?')">
                                                <input type="hidden" name="action" value="verify_healer">
                                                <input type="hidden" name="healer_id" value="<?= $h['id'] ?>">
                                                <button class="btn btn-sm btn-primary rounded-pill px-3 fw-bold">Claim & Verify</button>
                                            </form>
                                        <?php elseif ($h['managed_by_bhw_id'] == $user_id): ?>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="action" value="toggle_availability">
                                                <input type="hidden" name="healer_id" value="<?= $h['id'] ?>">
                                                <input type="hidden" name="status" value="<?= $h['is_available'] ? 0 : 1 ?>">
                                                <button class="btn btn-sm <?= $h['is_available'] ? 'btn-outline-danger' : 'btn-outline-success' ?> rounded-pill px-3 me-1">
                                                    <?= $h['is_available'] ? 'Set Away' : 'Set Active' ?>
                                                </button>
                                            </form>
                                            <button class="btn btn-sm btn-success rounded-pill px-3" onclick='editHealer(<?= json_encode($h) ?>)'>Edit</button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Healer Modal (Add/Edit) -->
<div class="modal fade" id="healerModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content rounded-5 border-0 shadow-lg p-3">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold text-primary display-6 ms-3" id="modalTitle">Register Local Healer</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <form method="POST" id="healerForm" enctype="multipart/form-data">
                    <input type="hidden" name="action" id="formAction" value="add">
                    <input type="hidden" name="healer_id" id="healerIdInput">
                    <input type="hidden" name="existing_profile_picture" id="existingProfilePic">
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
                                <label class="form-label small fw-bold text-muted text-uppercase">Profile Identity (Photo)</label>
                                <div class="d-flex gap-2 mb-2">
                                    <button type="button" class="btn btn-sm btn-outline-success flex-grow-1" id="startCam"><i data-lucide="camera" class="me-1" style="width:14px;"></i> Take Photo</button>
                                    <button type="button" class="btn btn-sm btn-outline-primary flex-grow-1" id="triggerFile"><i data-lucide="upload" class="me-1" style="width:14px;"></i> Upload File</button>
                                </div>
                                <input type="file" name="profile_file" id="profileFile" class="d-none" accept="image/*">
                                <div id="cameraContainer" class="d-none mb-2">
                                    <video id="video" width="100%" height="auto" autoplay class="rounded-4 bg-dark"></video>
                                    <button type="button" class="btn btn-success btn-sm w-100 mt-2" id="captureBtn">Capture Now</button>
                                </div>
                                <div id="imagePreview" class="text-center p-2 bg-light rounded-4 d-none">
                                    <img id="previewImg" src="" class="img-fluid rounded-4 shadow-sm" style="max-height: 200px;">
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label small fw-bold text-muted text-uppercase">Treatment Methods</label>
                                <textarea name="treatment_methods" class="form-control" rows="2" placeholder="Describe therapeutic techniques..."></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-bold text-muted text-uppercase">Herbs / Plants Utilized</label>
                                <textarea name="herbs_used" class="form-control" rows="2" placeholder="List key botanical components..."></textarea>
                            </div>
                        </div>

                        <div class="col-md-7">
                            <div class="mb-3">
                                <label class="form-label small fw-bold text-muted text-uppercase">Practice Location Name</label>
                                <div class="input-group">
                                    <input type="text" name="location_name" id="locName" class="form-control" placeholder="Specific clinic or house address" required>
                                    <button class="btn btn-outline-primary" type="button" id="searchLocBtn">Locate</button>
                                </div>
                            </div>
                            <div id="healerMap"></div>
                            <div class="row">
                                <div class="col-6">
                                    <input type="text" name="latitude" id="lat" class="form-control form-control-sm bg-light" placeholder="Latitude" readonly required>
                                </div>
                                <div class="col-6">
                                    <input type="text" name="longitude" id="lng" class="form-control form-control-sm bg-light" placeholder="Longitude" readonly required>
                                </div>
                            </div>
                            <div class="mt-4">
                                <label class="form-label small fw-bold text-muted text-uppercase">Biography / Background</label>
                                <textarea name="description" class="form-control" rows="4" placeholder="Brief history or background..."></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="text-end mt-4">
                        <button type="button" class="btn btn-light rounded-pill px-4 me-2" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary rounded-pill px-5 shadow-sm fw-bold">Save Healer Profile</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    lucide.createIcons();

    let map = L.map('healerMap').setView([11.2407, 124.9961], 13);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);
    let marker;

    map.on('click', function(e) {
        if (marker) map.removeLayer(marker);
        marker = L.marker(e.latlng).addTo(map);
        document.getElementById('lat').value = e.latlng.lat;
        document.getElementById('lng').value = e.latlng.lng;
    });

    document.getElementById('healerModal').addEventListener('shown.bs.modal', () => map.invalidateSize());

    async function searchLocation() {
        const query = document.getElementById('locName').value;
        if (!query) return;
        try {
            const res = await fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(query + " " + "<?= $barangay ?>")}`);
            const data = await res.json();
            if (data.length > 0) {
                const { lat, lon } = data[0];
                map.setView([lat, lon], 16);
                if (marker) map.removeLayer(marker);
                marker = L.marker([lat, lon]).addTo(map);
                document.getElementById('lat').value = lat;
                document.getElementById('lng').value = lon;
            }
        } catch (e) {}
    }
    document.getElementById('searchLocBtn').addEventListener('click', searchLocation);

    function editHealer(data) {
        document.getElementById('modalTitle').innerText = "Edit Healer Profile";
        document.getElementById('formAction').value = "edit";
        document.getElementById('healerIdInput').value = data.id;
        document.getElementById('existingProfilePic').value = data.profile_picture;
        
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

        if (data.latitude && data.longitude) {
            const latlng = [parseFloat(data.latitude), parseFloat(data.longitude)];
            map.setView(latlng, 16);
            if (marker) map.removeLayer(marker);
            marker = L.marker(latlng).addTo(map);
        }

        if (data.profile_picture) {
            document.getElementById('previewImg').src = data.profile_picture;
            document.getElementById('imagePreview').classList.remove('d-none');
        }

        new bootstrap.Modal(document.getElementById('healerModal')).show();
    }

    // Camera/Upload Logic (Simplified)
    document.getElementById('triggerFile').addEventListener('click', () => document.getElementById('profileFile').click());
    document.getElementById('profileFile').addEventListener('change', function() {
        if (this.files[0]) {
            const reader = new FileReader();
            reader.onload = (e) => {
                document.getElementById('previewImg').src = e.target.result;
                document.getElementById('imagePreview').classList.remove('d-none');
            };
            reader.readAsDataURL(this.files[0]);
        }
    });

    const video = document.getElementById('video');
    document.getElementById('startCam').addEventListener('click', async () => {
        const stream = await navigator.mediaDevices.getUserMedia({ video: true });
        video.srcObject = stream;
        document.getElementById('cameraContainer').classList.remove('d-none');
    });

    document.getElementById('captureBtn').addEventListener('click', () => {
        const canvas = document.createElement('canvas');
        canvas.width = video.videoWidth; canvas.height = video.videoHeight;
        canvas.getContext('2d').drawImage(video, 0, 0);
        const dataUrl = canvas.toDataURL('image/png');
        document.getElementById('previewImg').src = dataUrl;
        document.getElementById('capturedImage').value = dataUrl;
        document.getElementById('imagePreview').classList.remove('d-none');
        document.getElementById('cameraContainer').classList.add('d-none');
        video.srcObject.getTracks().forEach(t => t.stop());
    });

    <?php if ($success): ?>Swal.fire({ icon: 'success', title: 'Success', text: '<?= $success ?>' });<?php endif; ?>
    <?php if ($error): ?>Swal.fire({ icon: 'error', title: 'Error', text: '<?= $error ?>' });<?php endif; ?>
</script>
</body>
</html>

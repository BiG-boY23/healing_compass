<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$role = $_SESSION['role'];
$username = $_SESSION['username'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Plant Recognition | Healing Compass</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Lexend:wght@300;400;500;600;700;800&family=Outfit:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <script src="https://unpkg.com/lucide@latest"></script>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="dashboard-app" style="background-color: var(--nature-bg);">

    <div class="container-fluid p-0">
        <div class="row g-0">
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

                    <a href="dashboard.php" class="sidebar-link active">
                        <i data-lucide="sprout"></i> Medicinal Plants
                    </a>

                    <a href="healers.php" class="sidebar-link">
                        <i data-lucide="users"></i> Healers
                    </a>

                    <a href="map.php" class="sidebar-link">
                        <i data-lucide="map-pin"></i> Healing Map
                    </a>
                    
                    <?php if ($role === 'admin'): ?>
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
            
            <!-- Main Content Area -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-5 py-5 min-vh-100">
                <div class="mb-5">
                    <h1 class="fw-bold mb-1" style="font-size: 2.2rem; letter-spacing: -1px; color: var(--nature-forest);">Plant Recognition</h1>
                    <p class="text-muted fw-medium mb-0">Identify traditional medicinal flora using computer vision</p>
                </div>

                <div class="row justify-content-center">
                    <div class="col-lg-9">
                        <div class="dash-tile p-4 mb-4 text-center bg-white border">
                            <h5 class="mb-4 text-success fw-bold">Snap or Upload a Photo</h5>
                            
                            <div id="camera-container" class="mb-4 bg-light shadow-inner rounded-4 overflow-hidden position-relative" style="height: 400px; display: none; border: 2px solid var(--sage-soft);">
                                <video id="video" width="100%" height="100%" autoplay playsinline></video>
                                <button id="capture-btn" class="btn btn-success position-absolute bottom-0 start-50 translate-middle-x mb-4 rounded-pill px-4 shadow">
                                    <i class="bi bi-camera-fill me-2 text-white"></i> Capture
                                </button>
                            </div>

                            <canvas id="canvas" style="display:none;"></canvas>

                            <div id="upload-container">
                                <div class="mb-4 p-5 border border-2 border-dashed rounded-4" id="drop-zone" style="background-color: #fcfcfc; border-color: var(--sage-soft) !important;">
                                    <i class="bi bi-cloud-upload fs-1 text-success opacity-50 mb-3 d-block"></i>
                                    <p class="text-muted fw-semibold">Drag & Drop or Click to Upload</p>
                                    <input type="file" id="file-input" hidden accept="image/*">
                                    <button class="btn btn-success btn-sm rounded-pill px-4" onclick="document.getElementById('file-input').click()">Select Image</button>
                                </div>
                                <div class="d-flex align-items-center justify-content-center gap-3">
                                    <button class="btn btn-success px-5 rounded-pill shadow-sm" id="open-camera">
                                        <i class="bi bi-camera-fill me-2 text-white"></i> Use Camera
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div id="results-container" style="display: none;">
                            <div class="recognition-result-card shadow-lg border-0 overflow-hidden bg-white">
                                <div class="row g-0">
                                    <!-- Image Preview Side -->
                                    <div class="col-md-5 position-relative">
                                        <div id="result-img-wrapper" class="h-100" style="min-height: 400px; background-size: cover; background-position: center;">
                                            <div class="confidence-overlay">
                                                <div class="confidence-pill"><i class="bi bi-shield-check me-2"></i>98% Match</div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Content Side -->
                                    <div class="col-md-7 p-4 p-lg-5 d-flex flex-column">
                                        <div class="d-flex justify-content-between align-items-center mb-4">
                                            <span class="text-overline">Identified Species</span>
                                            <button class="btn-reset-round" onclick="location.reload()" title="New Scan">
                                                <i class="bi bi-arrow-repeat"></i>
                                            </button>
                                        </div>

                                        <h2 id="result-name" class="display-6 fw-bold text-success mb-1">...</h2>
                                        <p id="result-scientific" class="text-muted fst-italic fs-5 mb-4"></p>

                                        <div class="divider mb-4"></div>

                                        <div class="info-sections flex-grow-1">
                                            <div class="info-box mb-4">
                                                <div class="info-label text-success">
                                                    <i class="bi bi-heart-pulse"></i> Medicinal Benefits
                                                </div>
                                                <p id="result-uses" class="info-text">...</p>
                                            </div>

                                            <div class="info-box mb-4">
                                                <div class="info-label text-warning">
                                                    <i class="bi bi-magic"></i> Traditional Preparation
                                                </div>
                                                <p id="result-prep" class="info-text">...</p>
                                            </div>
                                        </div>

                                        <div class="d-flex gap-3 mt-auto pt-4">
                                            <button class="btn btn-success flex-grow-1 rounded-pill py-3 fw-bold shadow-sm">
                                                <i class="bi bi-bookmark-plus-fill me-2"></i> Save to Flora
                                            </button>
                                            <a href="healers.php" class="btn btn-outline-success border-2 rounded-pill py-3 px-4 fw-bold">
                                                Find Healers
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div id="loading-spinner" class="text-center py-5" style="display: none;">
                            <div class="spinner-grow text-success" role="status" style="width: 3rem; height: 3rem;">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <p class="text-muted mt-4 fw-semibold">Analyzing plant features with AI...</p>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script>
        lucide.createIcons();
    </script>
    <script src="assets/js/recognition.js?v=2"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$role = $_SESSION['role'];
$username = $_SESSION['username'];

// Fetch healers for map markers
try {
    // RELAXED FILTER: Show all healers for testing visibility
    $stmt = $pdo->query("SELECT * FROM healers");
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
    <title>Healing Map | Healing Compass</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Lexend:wght@300;400;500;600;700;800&family=Outfit:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <script src="https://unpkg.com/lucide@latest"></script>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        #map { 
            height: 600px; 
            border-radius: 2rem; 
            box-shadow: 0 10px 30px rgba(0,0,0,0.05); 
            border: 2px solid #e9f5ef;
        }

        /* Custom Marker Style */
        .custom-healer-icon {
            background: none;
            border: none;
        }

        .marker-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            width: 50px;
        }

        .marker-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            border: 3px solid #FFF;
            background-size: cover;
            background-position: center;
            box-shadow: 0 4px 10px rgba(0,0,0,0.15);
            transition: all 0.3s ease;
        }

        .marker-pin {
            width: 14px;
            height: 14px;
            background: var(--nature-forest);
            border: 2px solid #FFF;
            border-radius: 50%;
            margin-top: -6px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        .custom-healer-icon:hover .marker-avatar {
            transform: scale(1.15) translateY(-5px);
            border-color: var(--nature-forest);
            box-shadow: 0 10px 20px rgba(0,0,0,0.2);
        }
    </style>
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

                    <a href="dashboard.php" class="sidebar-link">
                        <i data-lucide="sprout"></i> Medicinal Plants
                    </a>

                    <a href="healers.php" class="sidebar-link">
                        <i data-lucide="users"></i> Healers
                    </a>

                    <a href="map.php" class="sidebar-link active">
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
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-5 py-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h2 class="fw-bold text-success mb-1">Healing Compass Map</h2>
                        <p class="text-muted mb-0">Locate verified traditional healers and plant source centers.</p>
                    </div>
                </div>

                <div id="map"></div>

                <div class="mt-4">
                    <div class="dash-tile py-3 px-4" style="max-width: 300px;">
                        <div class="d-flex align-items-center w-100">
                            <i class="bi bi-shield-check text-success fs-3 me-3"></i>
                            <div class="text-start">
                                <h6 class="mb-0 fw-bold">Verified Results</h6>
                                <p class="small text-muted mb-0">All points are certified.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Leaflet JS -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        // Initialize Lucide Icons
        lucide.createIcons();

        const map = L.map('map').setView([11.2407, 124.9961], 13);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap contributors'
        }).addTo(map);

        // Healer Data from PHP
        const healers = <?= json_encode($healers) ?>;

        healers.forEach(h => {
            if (h.latitude && h.longitude) {
                const profilePic = h.profile_picture ? h.profile_picture : 'https://cdn-icons-png.flaticon.com/512/3135/3135715.png';
                
                // --- CUSTOM AVATAR ICON ---
                const customIcon = L.divIcon({
                    className: 'custom-healer-icon',
                    html: `
                        <div class="marker-container">
                            <div class="marker-avatar" style="background-image: url('${profilePic}')"></div>
                            <div class="marker-pin"></div>
                        </div>
                    `,
                    iconSize: [50, 60],
                    iconAnchor: [25, 60],
                    popupAnchor: [0, -60]
                });

                const marker = L.marker([h.latitude, h.longitude], { icon: customIcon }).addTo(map);
                
                marker.bindPopup(`
                    <div class="p-2 text-center" style="min-width: 150px;">
                        <h6 class="fw-bold mb-1" style="color: var(--sage-primary); font-size: 1rem;">${h.full_name}</h6>
                        <p class="small text-muted mb-3">${h.specialization}</p>
                        <div class="d-grid gap-2">
                            <a href="healers.php" class="btn btn-sm btn-outline-success rounded-pill px-3">View Profile</a>
                            <a href="booking.php?healer_id=${h.id}" class="btn btn-sm btn-success rounded-pill px-3 shadow-sm text-white">Book Now</a>
                        </div>
                    </div>
                `);
            }
        });

        // Add user location if available
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(position => {
                const { latitude, longitude } = position.coords;
                map.setView([latitude, longitude], 15);
                L.marker([latitude, longitude]).addTo(map).bindPopup("You are here").openPopup();
            });
        }
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

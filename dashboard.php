<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
require_once 'config/database.php';

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];
$username = $_SESSION['username'];

// Fetch Dynamic Statistics for Home
try {
    // 1. Plants Identified by this user
    $stmtIdentified = $pdo->prepare("SELECT COUNT(*) FROM plant_identifications WHERE user_id = ?");
    $stmtIdentified->execute([$user_id]);
    $identifiedCount = $stmtIdentified->fetchColumn();

    // 2. Appointments Today
    $stmtAppointments = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE user_id = ? AND appointment_date = CURDATE()");
    $stmtAppointments->execute([$user_id]);
    $appointmentsCount = $stmtAppointments->fetchColumn();

    // 3. Community Alerts (Let's use count of verified plants for now as a placeholder for 'active wisdom')
    $stmtAlerts = $pdo->query("SELECT COUNT(*) FROM plants WHERE is_approved = 1 AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
    $alertsCount = $stmtAlerts->fetchColumn();
} catch (PDOException $e) {
    $identifiedCount = $appointmentsCount = $alertsCount = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | Healing Compass</title>
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
                    <a href="#" class="sidebar-link active" onclick="switchView('home', this)">
                        <i data-lucide="layout-grid"></i> Home
                    </a>

                    <a href="#" class="sidebar-link" onclick="switchView('plants', this)">
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
                
                <!-- Home / Discover Hub -->
                <div id="view-home" class="dashboard-view">
                    
                    <!-- Hero Header Section -->
                    <div class="mb-5">
                        <h1 class="fw-bold mb-1" style="font-size: 2.6rem; letter-spacing: -1.5px; color: var(--nature-forest);">Welcome back, <?= htmlspecialchars($username) ?>!</h1>
                        <p class="text-muted fw-medium fs-5">Explore healers and herbal wisdom near you today.</p>
                    </div>

                    <!-- Quick Insights Row -->
                    <div class="row g-3 mb-5">
                        <div class="col-md-4">
                            <div class="insight-card shadow-sm">
                                <div class="insight-icon" style="background: rgba(45, 79, 50, 0.1);">
                                    <i data-lucide="leaf" class="text-success" style="width: 20px;"></i>
                                </div>
                                <div>
                                    <h6 class="fw-bold mb-0"><?= $identifiedCount ?> Plants</h6>
                                    <small class="text-muted">Identified</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="insight-card shadow-sm">
                                <div class="insight-icon" style="background: rgba(255, 193, 7, 0.1);">
                                    <i data-lucide="calendar" class="text-warning" style="width: 20px;"></i>
                                </div>
                                <div>
                                    <h6 class="fw-bold mb-0"><?= $appointmentsCount ?> Bookings</h6>
                                    <small class="text-muted">Active Today</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="insight-card shadow-sm">
                                <div class="insight-icon" style="background: rgba(220, 53, 69, 0.1);">
                                    <i data-lucide="bell" class="text-danger" style="width: 20px;"></i>
                                </div>
                                <div>
                                    <h6 class="fw-bold mb-0"><?= $alertsCount ?> Alerts</h6>
                                    <small class="text-muted">Community</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <?php
                    // Fetch a random plant for the 'Daily Tip' to make it feel dynamic
                    try {
                        $tipStmt = $pdo->query("SELECT * FROM plants WHERE is_approved = 1 ORDER BY RAND() LIMIT 1");
                        $dailyTip = $tipStmt->fetch(PDO::FETCH_ASSOC);
                    } catch (PDOException $e) { $dailyTip = null; }
                    ?>

                    <?php if ($dailyTip): ?>
                    <!-- Dynamic Featured Wisdom (Replaces Static Tip) -->
                    <div class="mb-5">
                        <div class="smart-notif glow-soft" style="background: linear-gradient(135deg, white 0%, #F9FAF7 100%);">
                            <div class="row align-items-center">
                                <div class="col-md-8">
                                    <div class="d-flex align-items-center mb-2">
                                        <div class="bg-success bg-opacity-10 p-2 rounded-3 me-3">
                                            <i data-lucide="sparkles" class="text-success" style="width: 20px;"></i>
                                        </div>
                                        <h6 class="fw-bold mb-0 text-success">Daily Botanical Insight</h6>
                                    </div>
                                    <h4 class="fw-bold mb-2">Harnessing <?= htmlspecialchars($dailyTip['plant_name']) ?></h4>
                                    <p class="text-muted mb-0">Did you know? **<?= htmlspecialchars($dailyTip['plant_name']) ?>** is traditionally used for **<?= htmlspecialchars($dailyTip['illness_treated']) ?>**. Explore the preparation methods in our library.</p>
                                </div>
                                <div class="col-md-4 text-end d-none d-md-block">
                                    <button onclick="switchView('plants')" class="btn btn-success rounded-pill px-4 fw-bold shadow-sm" style="background: var(--nature-forest); border: none;">Learn Preparation</button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Healers Nearby Section -->
                    <div class="mb-5">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h5 class="fw-bold mb-0" style="color: var(--nature-forest);">Healers Nearby</h5>
                            <a href="healers.php" class="text-success text-decoration-none fw-bold small">Direct View <i data-lucide="arrow-right" class="d-inline" style="width: 14px;"></i></a>
                        </div>
                        <div class="healer-row">
                            <?php
                            try {
                                $stmt = $pdo->query("SELECT * FROM healers WHERE is_verified = 1 ORDER BY RAND() LIMIT 6");
                                $nearby = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                foreach ($nearby as $h):
                            ?>
                                <div class="healer-mini-card">
                                    <div class="mini-avatar shadow-sm" style="background-image: url('<?= htmlspecialchars($h['profile_picture'] ?? 'https://cdn-icons-png.flaticon.com/512/3135/3135715.png') ?>')"></div>
                                    <h6 class="fw-bold mb-1" style="color: var(--nature-forest);"><?= htmlspecialchars($h['full_name']) ?></h6>
                                    <span class="badge rounded-pill mb-2" style="background: var(--nature-accent); color: var(--nature-forest); font-size: 0.6rem;"><?= htmlspecialchars($h['specialization']) ?></span>
                                    <div class="d-flex justify-content-center align-items-center text-muted small mt-1">
                                        <i data-lucide="map-pin" style="width: 12px; margin-right: 4px;"></i>
                                        <span><?= (rand(1, 5) . '.' . rand(0, 9)) ?> miles away</span>
                                    </div>
                                    <a href="booking.php?healer_id=<?= $h['id'] ?>" class="btn btn-sm btn-success w-100 rounded-pill mt-3 py-2 fw-bold" style="background: var(--nature-forest); border: none;">Book Now</a>
                                </div>
                            <?php 
                                endforeach;
                            } catch (PDOException $e) { echo "<p>No healers nearby found at this time.</p>"; }
                            ?>
                        </div>
                    </div>

                    <!-- Discovery Grid (Condensed) -->
                    <div class="row g-4 mb-5">
                        <div class="col-md-8">
                            <div class="d-flex justify-content-between align-items-center mb-4">
                                <h5 class="fw-bold mb-0" style="color: var(--nature-forest);">Trending Plants</h5>
                                <button onclick="switchView('plants', null)" class="btn btn-sm btn-link text-success p-0 text-decoration-none fw-bold">View All Archive</button>
                            </div>
                            <div class="row g-3">
                                <?php
                                try {
                                    $stmt = $pdo->query("SELECT * FROM plants WHERE is_approved = 1 ORDER BY RAND() LIMIT 2");
                                    $trending = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                    foreach ($trending as $p):
                                ?>
                                    <div class="col-12">
                                        <a href="plant-details.php?id=<?= $p['id'] ?>" class="text-decoration-none">
                                            <div class="nature-card shadow-sm p-3 nature-card-hover" style="padding: 1rem !important;">
                                                <div class="d-flex align-items-center">
                                                    <div class="rounded-4 me-3 flex-shrink-0" style="width: 75px; height: 75px; background: url('<?= htmlspecialchars($p['plant_image'] ?? 'assets/img/hero.png') ?>') center/cover; border-radius: 1.2rem !important;"></div>
                                                    <div class="flex-grow-1">
                                                        <h6 class="fw-bold mb-0" style="color: var(--nature-forest);"><?= htmlspecialchars($p['plant_name']) ?></h6>
                                                        <small class="text-muted fst-italic"><?= htmlspecialchars($p['scientific_name']) ?></small>
                                                        <p class="small text-muted mb-0 text-truncate-2"><?= htmlspecialchars($p['illness_treated']) ?></p>
                                                    </div>
                                                    <i data-lucide="chevron-right" class="text-muted flex-shrink-0"></i>
                                                </div>
                                            </div>
                                        </a>
                                    </div>
                                <?php 
                                    endforeach;
                                } catch (PDOException $e) { /* Error */ }
                                ?>
                                <!-- Quick Action: Identify Flora -->
                                <div class="col-12">
                                    <a href="plant-recognition.php" class="text-decoration-none">
                                        <div class="nature-card shadow-sm p-3" style="background: linear-gradient(135deg, var(--nature-forest), #1E3822); border: none; color: white;">
                                            <div class="d-flex align-items-center">
                                                <div class="bg-white bg-opacity-20 rounded-4 me-3 d-flex align-items-center justify-content-center" style="width: 80px; height: 80px;">
                                                    <i data-lucide="camera" style="width: 32px; height: 32px;"></i>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <h6 class="fw-bold mb-0">Identify Flora</h6>
                                                    <p class="small text-white text-opacity-75 mb-0">Snap a photo and get instant wisdom</p>
                                                </div>
                                                <i data-lucide="sparkles" style="width: 20px;"></i>
                                            </div>
                                        </div>
                                    </a>
                                </div>
                            </div>
                        </div>

                        <!-- Map Preview -->
                        <div class="col-md-4">
                            <h5 class="fw-bold mb-4" style="color: var(--nature-forest);">Interactive Map</h5>
                            <div class="map-preview-box shadow-sm mb-3">
                                <!-- Animated Map Dots (Dynamically Positioned) -->
                                <?php
                                try {
                                    $mapStmt = $pdo->query("SELECT latitude, longitude FROM healers WHERE latitude IS NOT NULL LIMIT 5");
                                    $mapHealers = $mapStmt->fetchAll(PDO::FETCH_ASSOC);
                                    foreach ($mapHealers as $mh):
                                        // Simple mapping of lat/lng to percentage for the preview box
                                        // This is a rough visualization
                                        $top = 20 + (($mh['latitude'] * 100) % 60);
                                        $left = 20 + (($mh['longitude'] * 100) % 60);
                                ?>
                                    <div class="map-dot animate-pulse-forest" style="top: <?= $top ?>%; left: <?= $left ?>%;"></div>
                                <?php 
                                    endforeach;
                                } catch (PDOException $e) { /* Error */ }
                                ?>
                                <div class="text-center p-4" style="background: rgba(255, 255, 255, 0.6); backdrop-filter: blur(4px); border-radius: 1.5rem; position: relative; z-index: 10;">
                                    <p class="fw-bold mb-1" style="color: var(--nature-forest);">Healing Map</p>
                                    <p class="small text-muted mb-3"><?= count($mapHealers ?? []) ?> Active healers found nearby.</p>
                                    <a href="map.php" class="btn btn-sm btn-success rounded-pill px-4 fw-bold shadow-sm" style="background: var(--nature-forest); border: none;">Expand Map</a>
                                </div>
                            </div>
                            <div class="p-3 bg-white rounded-4 border border-light shadow-sm">
                                <div class="d-flex align-items-center">
                                    <i data-lucide="navigation" class="text-success me-2" style="width: 16px;"></i>
                                    <small class="fw-bold text-muted">Tracking enabled</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Plants View (Complete Library Catalog) -->
                <div id="view-plants" class="dashboard-view d-none">
                    <div class="mb-5">
                        <div class="d-flex justify-content-between align-items-end mb-4">
                            <div>
                                <h1 class="fw-bold mb-1" style="font-size: 2.2rem; letter-spacing: -1px; color: var(--nature-forest);">Medicinal Plant Library</h1>
                                <p class="text-muted fw-medium mb-0">Comprehensive catalog of traditional healing wisdom</p>
                            </div>
                            <div class="filter-chips-container mb-0">
                                <div class="filter-chip active">All Spirits</div>
                                <div class="filter-chip">Roots</div>
                                <div class="filter-chip">Leaves</div>
                                <div class="filter-chip">Flowers</div>
                            </div>
                        </div>
                    </div>

                    <div class="row g-4 overflow-visible">
                        <?php
                        try {
                            $stmt = $pdo->query("SELECT * FROM plants WHERE is_approved = 1 ORDER BY plant_name ASC");
                            $all_plants = $stmt->fetchAll(PDO::FETCH_ASSOC);
                            
                            foreach ($all_plants as $p): 
                            ?>
                                <div class="col-md-6 col-lg-4">
                                    <a href="plant-details.php?id=<?= $p['id'] ?>" class="text-decoration-none">
                                        <div class="nature-card shadow-sm nature-card-hover">
                                            <div class="d-flex justify-content-between align-items-start mb-4">
                                                <div class="card-avatar shadow-sm" style="width: 65px; height: 65px; border-radius: 20px; background: url('<?= htmlspecialchars($p['plant_image'] ?? 'assets/img/hero.png') ?>') center/cover; border: 3px solid white;"></div>
                                                <div class="text-end">
                                                    <h5 class="fw-bold mb-0" style="color: var(--nature-forest); text-decoration: none;"><?= htmlspecialchars($p['plant_name']) ?></h5>
                                                    <small class="text-muted fst-italic"><?= htmlspecialchars($p['scientific_name']) ?></small>
                                                </div>
                                            </div>
                                            <p class="text-muted small lh-base mb-4 text-truncate-3"><?= htmlspecialchars($p['illness_treated']) ?></p>
                                            <div class="btn btn-sm btn-success rounded-pill w-100 fw-bold" style="background: var(--nature-forest); padding: 10px 0; border: none;">View Full Wisdom</div>
                                        </div>
                                    </a>
                                </div>
                            <?php endforeach;
                        } catch (PDOException $e) { echo '<div class="alert alert-danger">Library database error.</div>'; }
                        ?>
                    </div>
                </div>

                <!-- Plants View (Alias) -->
                <div id="view-plants" class="dashboard-view d-none">
                    <!-- Same view logic as above, or we can just switch them -->
                </div>

                <!-- Aura Assistant Floating Window & Toggle -->
                <div class="aura-toggle shadow-lg" onclick="toggleAura()">
                    <i data-lucide="message-circle" style="width: 30px; height: 30px;"></i>
                </div>

                <div id="aura-chat-panel" class="shadow-2xl d-none" style="position: fixed; bottom: 110px; right: 30px; width: 380px; height: 500px; background: white; border-radius: 2rem; z-index: 1060; border: 1px solid rgba(0,0,0,0.05); flex-direction: column; overflow: hidden; display: none;">
                    <div class="p-4 d-flex align-items-center justify-content-between" style="background: var(--nature-forest); color: white;">
                        <div class="d-flex align-items-center">
                            <div class="bg-white bg-opacity-20 p-2 rounded-3 me-3">
                                <i data-lucide="sparkles" style="width: 20px;"></i>
                            </div>
                            <h6 class="mb-0 fw-bold">Aura Assistant</h6>
                        </div>
                        <button class="btn btn-sm text-white" onclick="toggleAura()">
                            <i data-lucide="x" style="width: 18px;"></i>
                        </button>
                    </div>
                    <div id="chat-messages" class="flex-grow-1 p-4 overflow-auto bg-light bg-opacity-50" style="display: flex; flex-direction: column;">
                        <div class="chat-bubble bubble-aura">
                            Hello! I'm Aura. Search for medicinal plants or describe symptoms, and I'll share botanical wisdom from our forest.
                        </div>
                    </div>
                    <div class="p-3 bg-white border-top">
                        <div class="input-group">
                            <input type="text" id="chat-input" class="form-control border-0 bg-light rounded-pill px-4" placeholder="Ask Aura anything..." style="height: 50px;">
                            <button class="btn btn-success ms-2 rounded-circle" id="chat-send" style="width: 50px; height: 50px; background: var(--nature-forest);">
                                <i data-lucide="send-horizontal" style="width: 18px;"></i>
                            </button>
                        </div>
                    </div>
                </div>

            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Initialize Lucide Icons and handle ?view= URL param
        window.addEventListener('DOMContentLoaded', () => {
            lucide.createIcons();
            loadHistory();

            // Auto-switch view based on URL parameter (e.g. from plant-details.php back button)
            const urlParams = new URLSearchParams(window.location.search);
            const requestedView = urlParams.get('view');
            if (requestedView && document.getElementById('view-' + requestedView)) {
                switchView(requestedView, null);
                // Highlight matching sidebar link
                document.querySelectorAll('.sidebar-link').forEach(link => {
                    link.classList.remove('active');
                });
                if (requestedView === 'plants') {
                    const plantsLink = document.querySelector('.sidebar-link[onclick*="plants"]');
                    if (plantsLink) plantsLink.classList.add('active');
                }
            }
        });

        function switchView(viewName, element) {
            // Hide all dashboard views
            document.querySelectorAll('.dashboard-view').forEach(view => {
                view.classList.add('d-none');
            });
            
            // Show the target view
            const targetView = document.getElementById('view-' + viewName);
            if (targetView) {
                targetView.classList.remove('d-none');
            }

            // Update sidebar active states
            if (element) {
                document.querySelectorAll('.sidebar-link').forEach(link => {
                    link.classList.remove('active');
                });
                element.classList.add('active');
            }
            
            // Re-initialize icons in the now-visible view
            lucide.createIcons();
        }

        // Aura Assistant Panel Logic
        function toggleAura() {
            const panel = document.getElementById('aura-chat-panel');
            if (panel.style.display === 'none' || panel.classList.contains('d-none')) {
                panel.style.display = 'flex';
                panel.classList.remove('d-none');
                panel.classList.add('animate__animated', 'animate__fadeInUp');
            } else {
                panel.style.display = 'none';
                panel.classList.add('d-none');
            }
            lucide.createIcons();
        }

        // Chat Logic
        const chatInput = document.getElementById('chat-input');
        const chatSend = document.getElementById('chat-send');
        const chatMessages = document.getElementById('chat-messages');

        async function loadHistory() {
            // Simplified for the new floating layout
        }

        function appendMessage(role, text) {
            const div = document.createElement('div');
            div.className = `chat-bubble bubble-${role}`;
            div.style.marginBottom = "15px";
            div.style.padding = "12px 18px";
            div.style.borderRadius = "1.5rem";
            div.style.fontSize = "0.9rem";
            
            if (role === 'aura') {
                div.style.background = "var(--nature-accent)";
                div.style.color = "var(--nature-forest)";
                div.style.alignSelf = "flex-start";
                div.style.borderBottomLeftRadius = "4px";
            } else {
                div.style.background = "var(--nature-forest)";
                div.style.color = "white";
                div.style.alignSelf = "flex-end";
                div.style.borderBottomRightRadius = "4px";
            }
            
            div.textContent = text;
            chatMessages.appendChild(div);
            chatMessages.scrollTop = chatMessages.scrollHeight;
            return div;
        }

        async function sendMessage() {
            const query = chatInput.value.trim();
            if (!query) return;

            appendMessage('user', query);
            chatInput.value = '';

            const typingMsg = appendMessage('aura', 'Drawing from ancient wisdom...');
            typingMsg.style.opacity = '0.5';

            try {
                const response = await fetch('api/chatbot.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ query })
                });
                const data = await response.json();
                
                typingMsg.remove();
                appendMessage('aura', data.reply);
            } catch (error) {
                typingMsg.remove();
                appendMessage('aura', 'Connection failed. Please check your internet.');
            }
        }

        if (chatSend) chatSend.addEventListener('click', sendMessage);
        if (chatInput) chatInput.addEventListener('keypress', (e) => { if (e.key === 'Enter') sendMessage(); });
    </script>
</body>
</html>

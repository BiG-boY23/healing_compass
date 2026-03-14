<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once 'config/database.php';

$role = $_SESSION['role'];
$username = $_SESSION['username'];

// Get plant ID from URL
$plant_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$plant_id) {
    header("Location: dashboard.php");
    exit();
}

// Fetch the plant details
try {
    $stmt = $pdo->prepare("SELECT * FROM plants WHERE id = ? AND is_approved = 1");
    $stmt->execute([$plant_id]);
    $plant = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $plant = null;
}

if (!$plant) {
    header("Location: dashboard.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($plant['plant_name']) ?> | Healing Compass</title>
    <meta name="description" content="Traditional medicinal wisdom for <?= htmlspecialchars($plant['plant_name']) ?> — <?= htmlspecialchars(substr($plant['illness_treated'] ?? '', 0, 150)) ?>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Lexend:wght@300;400;500;600;700;800&family=Outfit:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <script src="https://unpkg.com/lucide@latest"></script>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .plant-hero {
            width: 100%;
            height: 380px;
            border-radius: 2.5rem;
            object-fit: cover;
            background-size: cover;
            background-position: center;
            background-color: #E8EDE0;
        }

        .section-block {
            background: white;
            border-radius: 2rem;
            padding: 2rem;
            border: 1px solid rgba(0,0,0,0.03);
            box-shadow: 0 4px 20px rgba(45, 79, 50, 0.04);
        }

        .section-icon {
            width: 45px;
            height: 45px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .detail-chip {
            display: inline-block;
            background: var(--nature-accent);
            color: var(--nature-forest);
            padding: 6px 16px;
            border-radius: 100px;
            font-size: 0.8rem;
            font-weight: 600;
            margin: 4px 4px 4px 0;
        }

        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: white;
            border: 1px solid rgba(0,0,0,0.06);
            border-radius: 100px;
            padding: 10px 20px;
            font-weight: 600;
            color: var(--nature-forest);
            text-decoration: none;
            font-size: 0.9rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            transition: all 0.2s ease;
        }

        .back-btn:hover {
            background: var(--nature-forest);
            color: white;
            transform: translateX(-4px);
        }

        .warning-block {
            background: #FFF8F0;
            border-left: 5px solid #fd7e14;
            border-radius: 0 1.5rem 1.5rem 0;
            padding: 1.5rem;
        }

        .source-block {
            background: #F7F9F5;
            border-radius: 1.5rem;
            padding: 1.5rem;
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
                    <a href="dashboard.php?view=plants" class="sidebar-link active">
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

                <!-- Back Button -->
                <div class="mb-4">
                    <a href="dashboard.php?view=plants" class="back-btn">
                        <i data-lucide="arrow-left" style="width: 16px;"></i>
                        Back to Library
                    </a>
                </div>

                <!-- Hero Section -->
                <div class="mb-5">
                    <?php
                    $heroImg = $plant['plant_image'] ?? '';
                    ?>
                    <div class="plant-hero mb-4"
                         style="background-image: url('<?= htmlspecialchars($heroImg) ?>'); <?= !$heroImg ? "background: linear-gradient(135deg, #E8EDE0, #D4DCC8);" : '' ?>">
                        <?php if (!$heroImg): ?>
                            <div class="d-flex align-items-center justify-content-center h-100">
                                <i data-lucide="leaf" style="width: 80px; height: 80px; color: var(--nature-forest); opacity: 0.3;"></i>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Title & Taxonomy -->
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h1 class="fw-bold mb-1" style="font-size: 3rem; letter-spacing: -2px; color: var(--nature-forest);"><?= htmlspecialchars($plant['plant_name']) ?></h1>
                            <?php if ($plant['scientific_name']): ?>
                                <p class="text-muted fst-italic fs-5 mb-3"><?= htmlspecialchars($plant['scientific_name']) ?></p>
                            <?php endif; ?>
                            <span class="detail-chip">
                                <i class="bi bi-check-circle-fill me-1"></i> Verified Wisdom
                            </span>
                        </div>
                        <a href="plant-recognition.php" class="btn btn-success rounded-pill px-4 fw-bold d-none d-md-block" style="background: var(--nature-forest); border: none;">
                            <i data-lucide="camera" class="me-2" style="width: 16px;"></i> Identify Plant
                        </a>
                    </div>
                </div>

                <div class="row g-4">
                    <!-- Left Column: Main Info -->
                    <div class="col-lg-8">

                        <!-- Description -->
                        <?php if ($plant['description']): ?>
                        <div class="section-block mb-4">
                            <div class="d-flex align-items-center mb-3">
                                <div class="section-icon bg-success bg-opacity-10 me-3">
                                    <i data-lucide="book-open" class="text-success" style="width: 20px;"></i>
                                </div>
                                <h5 class="fw-bold mb-0" style="color: var(--nature-forest);">About This Plant</h5>
                            </div>
                            <p class="text-muted lh-lg mb-0"><?= nl2br(htmlspecialchars($plant['description'])) ?></p>
                        </div>
                        <?php endif; ?>

                        <!-- Medicinal Uses -->
                        <?php if ($plant['illness_treated']): ?>
                        <div class="section-block mb-4">
                            <div class="d-flex align-items-center mb-3">
                                <div class="section-icon bg-success bg-opacity-10 me-3">
                                    <i data-lucide="heart-pulse" class="text-success" style="width: 20px;"></i>
                                </div>
                                <h5 class="fw-bold mb-0" style="color: var(--nature-forest);">Medicinal Uses</h5>
                            </div>
                            <p class="text-muted small mb-3">Traditional conditions and symptoms this plant has been used to treat:</p>
                            <div class="mb-2">
                                <?php
                                // Split the illness_treated by comma or newline and show as chips
                                $illnesses = preg_split('/[,\n]+/', $plant['illness_treated']);
                                foreach ($illnesses as $illness):
                                    $illness = trim($illness);
                                    if ($illness): ?>
                                        <span class="detail-chip"><?= htmlspecialchars($illness) ?></span>
                                <?php endif; endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Preparation & Dosage -->
                        <?php if ($plant['preparation_method']): ?>
                        <div class="section-block mb-4">
                            <div class="d-flex align-items-center mb-3">
                                <div class="section-icon me-3" style="background: rgba(13, 110, 253, 0.1);">
                                    <i data-lucide="flask-conical" class="text-primary" style="width: 20px;"></i>
                                </div>
                                <h5 class="fw-bold mb-0" style="color: var(--nature-forest);">Preparation & Dosage</h5>
                            </div>
                            <p class="text-muted small mb-3">Traditional preparation methods and usage guidelines:</p>
                            <div class="p-3 rounded-4" style="background: #F7F9F5; border-left: 4px solid var(--nature-forest);">
                                <p class="mb-0 lh-lg" style="color: #444; font-size: 0.95rem;"><?= nl2br(htmlspecialchars($plant['preparation_method'])) ?></p>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Warnings / Side Effects -->
                        <div class="section-block mb-4">
                            <div class="d-flex align-items-center mb-3">
                                <div class="section-icon me-3" style="background: rgba(253, 126, 20, 0.1);">
                                    <i data-lucide="triangle-alert" class="text-warning" style="width: 20px;"></i>
                                </div>
                                <h5 class="fw-bold mb-0" style="color: var(--nature-forest);">Warnings & Contraindications</h5>
                            </div>
                            <div class="warning-block">
                                <ul class="mb-0 text-muted lh-lg" style="font-size: 0.95rem;">
                                    <li>Always consult a licensed practitioner before use, especially during pregnancy or breastfeeding.</li>
                                    <li>Do not self-medicate for serious conditions. This plant knowledge is supplementary, not a replacement for medical advice.</li>
                                    <li>Start with small dosages to check for allergic reactions.</li>
                                    <li>Keep out of reach of children unless under adult supervision with healer guidance.</li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <!-- Right Column: Meta Info -->
                    <div class="col-lg-4">

                        <!-- Quick Facts -->
                        <div class="section-block mb-4">
                            <h6 class="fw-bold mb-4" style="color: var(--nature-forest);">Quick Facts</h6>
                            
                            <div class="d-flex align-items-center mb-3">
                                <div class="section-icon me-3" style="background: var(--nature-accent); width: 35px; height: 35px; border-radius: 10px;">
                                    <i data-lucide="leaf" class="text-success" style="width: 16px;"></i>
                                </div>
                                <div>
                                    <small class="text-muted d-block" style="font-size: 0.7rem; text-transform: uppercase; letter-spacing: 1px;">Common Name</small>
                                    <span class="fw-bold small"><?= htmlspecialchars($plant['plant_name']) ?></span>
                                </div>
                            </div>

                            <?php if ($plant['scientific_name']): ?>
                            <div class="d-flex align-items-center mb-3">
                                <div class="section-icon me-3" style="background: rgba(13, 110, 253, 0.1); width: 35px; height: 35px; border-radius: 10px;">
                                    <i data-lucide="microscope" class="text-primary" style="width: 16px;"></i>
                                </div>
                                <div>
                                    <small class="text-muted d-block" style="font-size: 0.7rem; text-transform: uppercase; letter-spacing: 1px;">Scientific Name</small>
                                    <span class="fw-bold small fst-italic"><?= htmlspecialchars($plant['scientific_name']) ?></span>
                                </div>
                            </div>
                            <?php endif; ?>

                            <div class="d-flex align-items-center mb-3">
                                <div class="section-icon me-3" style="background: rgba(25, 135, 84, 0.1); width: 35px; height: 35px; border-radius: 10px;">
                                    <i data-lucide="calendar" class="text-success" style="width: 16px;"></i>
                                </div>
                                <div>
                                    <small class="text-muted d-block" style="font-size: 0.7rem; text-transform: uppercase; letter-spacing: 1px;">Date Recorded</small>
                                    <span class="fw-bold small"><?= date('M d, Y', strtotime($plant['created_at'])) ?></span>
                                </div>
                            </div>
                        </div>

                        <!-- Sources / References -->
                        <div class="section-block mb-4">
                            <div class="d-flex align-items-center mb-3">
                                <div class="section-icon me-3" style="background: rgba(108, 117, 125, 0.1); width: 35px; height: 35px; border-radius: 10px;">
                                    <i data-lucide="library" class="text-muted" style="width: 16px;"></i>
                                </div>
                                <h6 class="fw-bold mb-0" style="color: var(--nature-forest);">Source & Reference</h6>
                            </div>
                            <div class="source-block">
                                <p class="small text-muted mb-0">
                                    <?php if ($plant['source_reference']): ?>
                                        <?= nl2br(htmlspecialchars($plant['source_reference'])) ?>
                                    <?php else: ?>
                                        This knowledge was curated from local healer interviews and traditional oral records. Always cross-reference with a licensed botanical practitioner.
                                    <?php endif; ?>
                                </p>
                            </div>
                        </div>

                        <!-- Find a Healer CTA -->
                        <div class="section-block" style="background: linear-gradient(135deg, var(--nature-forest), #1E3822); border: none; color: white;">
                            <h6 class="fw-bold mb-2">Need a Consultation?</h6>
                            <p class="small text-white opacity-75 mb-3">Find a traditional healer near you who specializes in herbal medicine.</p>
                            <a href="healers.php" class="btn btn-light rounded-pill w-100 fw-bold" style="color: var(--nature-forest);">
                                <i data-lucide="users" class="me-2" style="width: 16px;"></i> Find Healers
                            </a>
                        </div>

                    </div>
                </div>

            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        lucide.createIcons();
    </script>
</body>
</html>

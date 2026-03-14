<?php
session_start();
$isLoggedIn = isset($_SESSION['user_id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Healing Compass | Minimal Herbal AI</title>
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <!-- Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

    <!-- Professional White Navbar -->
    <nav class="navbar navbar-expand-lg sticky-top">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center" href="index.php">
                <img src="assets/img/logo.png" alt="Healing Compass Logo" style="height: 40px;" class="me-2">
                <span>HEALING COMPASS<sup>&reg;</sup> <small class="text-muted" style="font-size: 0.6em;">CHOCOBOL</small></span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto align-items-center">
                    <li class="nav-item"><a class="nav-link" href="#services">Services</a></li>
                    <li class="nav-item"><a class="nav-link" href="healers.php">Healers</a></li>
                    <?php if ($isLoggedIn): ?>
                        <li class="nav-item ms-lg-3">
                            <a href="dashboard.php" class="btn btn-success btn-sm rounded-pill px-4">Go to Dashboard</a>
                        </li>
                    <?php else: ?>
                        <li class="nav-item"><a class="nav-link" href="login.php">Login</a></li>
                        <li class="nav-item ms-lg-3">
                            <a href="register.php" class="btn btn-blue btn-sm">Join Now</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Clean Hero Section -->
    <header class="hero">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6">
                    <div class="mb-3 text-primary fw-bold text-uppercase small tracking-widest">Natural Health Platform</div>
                    <h1 class="display-3 mb-4">Discover the Ancient<br><span class="text-primary">Path to Wellness</span></h1>
                    <p class="lead mb-5 fs-5">Effortlessly identify medicinal plants and consult with the world's most trusted traditional healers using our AI-assisted compass.</p>
                    <div class="d-flex flex-wrap gap-3">
                        <a href="plant-recognition.php" class="btn btn-blue px-4 py-3">
                           <i class="bi bi-camera-fill me-2"></i> Identify Plant
                        </a>
                        <a href="healers.php" class="btn btn-outline-blue px-4 py-3">
                            <i class="bi bi-people-fill me-2"></i> Our Healers
                        </a>
                    </div>
                </div>
                <div class="col-lg-6 mt-5 mt-lg-0">
                    <div class="position-relative">
                        <img src="assets/img/hero.png" class="img-fluid rounded-4 shadow-lg border-5 border-white border" alt="Traditional Healing Herbs">
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- Visual Services Grid - Visibility Fixed -->
    <section class="py-5 bg-white" id="services">
        <div class="container py-5">
            <div class="row text-center mb-5">
                <div class="col-lg-8 mx-auto">
                    <h2 class="display-5 mb-3">Professional Services</h2>
                    <p class="text-muted">A minimal and integrated approach to traditional botanical medicine.</p>
                </div>
            </div>
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="plant-card h-100">
                        <i class="bi bi-chat-heart"></i>
                        <h3>User Feedback</h3>
                        <p>Join our community of wellness seekers. Share your experiences with herbal remedies and help others discover natural paths to health.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="plant-card h-100">
                        <i class="bi bi-search"></i>
                        <h3>Plant ID</h3>
                        <p>Quickly identify over 1,000+ medicinal plant species by simply uploading or taking a clear photo.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="plant-card h-100">
                        <i class="bi bi-shield-check"></i>
                        <h3>Verified Experts</h3>
                        <p>We only list healers who have been certified by our traditional boards and background-checked for your safety.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Featured Healers Section (Anti-Fraud Profiling) -->
    <section class="py-5" id="verified-healers">
        <div class="container py-5">
            <div class="text-center mb-5">
                <span class="badge bg-success bg-opacity-10 text-success rounded-pill px-3 py-2 mb-3">TRUSTED PROFESSIONALS</span>
                <h2 class="display-5 fw-bold text-success">Meet Our Verified Healers</h2>
                <p class="text-muted mx-auto" style="max-width: 600px;">We maintain a rigorous verification process for all practitioners to ensure you receive authentic and safe traditional guidance.</p>
            </div>

            <div class="row g-4">
                <?php
                // Fetch 3 random healers for the homepage
                try {
                    require_once 'config/database.php';
                    $stmt = $pdo->query("SELECT * FROM healers ORDER BY RAND() LIMIT 3");
                    $featured_healers = $stmt->fetchAll(PDO::FETCH_ASSOC);
                } catch (PDOException $e) {
                    $featured_healers = [];
                }

                foreach ($featured_healers as $h):
                ?>
                <div class="col-md-4">
                    <div class="dash-tile p-0 text-start overflow-hidden h-100 border bg-white shadow-sm hover-up">
                        <div style="height: 200px; width: 100%; background: url('<?= htmlspecialchars($h['profile_picture'] ?? 'https://images.unsplash.com/photo-1544005313-94ddf0286df2?w=400') ?>') center/cover no-repeat;"></div>
                        <div class="p-4">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <h5 class="fw-bold text-success mb-0"><?= htmlspecialchars($h['full_name']) ?></h5>
                                <span class="badge bg-success bg-opacity-10 text-success small py-1"><?= $h['years_of_experience'] ?>Y Experience</span>
                            </div>
                            <p class="text-primary small fw-bold mb-3"><?= htmlspecialchars($h['specialization']) ?></p>
                            
                            <div class="mb-4">
                                <label class="small fw-bold text-muted mb-1 text-uppercase" style="font-size: 0.65rem; letter-spacing: 1px;">Primary Method</label>
                                <p class="text-muted small mb-0" style="display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; line-height: 1.6;">
                                    <?= htmlspecialchars($h['treatment_methods'] ?? $h['description']) ?>
                                </p>
                            </div>
                            
                            <div class="d-flex align-items-center text-muted small">
                                <i class="bi bi-geo-alt-fill me-2 text-success"></i>
                                <?= htmlspecialchars($h['location_name'] ?? 'Authorized Clinic') ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="text-center mt-5">
                <a href="healers.php" class="btn btn-outline-success rounded-pill px-5 py-3 fw-bold">View Full Directory & Methods</a>
            </div>
        </div>
    </section>

    <!-- Educational Section -->
    <section class="py-5 bg-light border-top">
        <div class="container py-5">
            <div class="row align-items-center g-5">
                <div class="col-md-6">
                    <img src="assets/img/educational.png" class="img-fluid rounded-4 shadow-sm" alt="Traditional Herbal Preparation">
                </div>
                <div class="col-md-6">
                    <h2 class="display-6 mb-4">Cultivating Traditional Knowledge</h2>
                    <p class="mb-4">Healing Compass is more than an app. It is a digital archive of the world's most effective natural treatments, preserved for the next generation.</p>
                    <div class="mb-4">
                        <div class="d-flex align-items-center mb-3">
                            <i class="bi bi-check2-circle text-primary fs-4 me-3"></i>
                            <span class="fw-bold">1,200+ Documented Species</span>
                        </div>
                        <div class="d-flex align-items-center">
                            <i class="bi bi-check2-circle text-primary fs-4 me-3"></i>
                            <span class="fw-bold">450+ Active Certified Healers</span>
                        </div>
                    </div>
                    <a href="register.php" class="btn btn-blue">Start Your Journey</a>
                </div>
            </div>
        </div>
    </section>

    <!-- Clean Footer -->
    <footer class="py-5 bg-white border-top">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <h4 class="fw-bold text-primary mb-3">HEALING COMPASS<sup>&reg;</sup></h4>
                    <p class="text-muted small">CHOCOBOL - Preserving natural wisdom through modern intelligence.</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <p class="text-muted small mb-0">&copy; 2026 Healing Compass Platform. All Rights Reserved.</p>
                </div>
            </div>
        </div>
    </footer>

    <!-- Floating Action - Removed AI Chat -->
    <div class="position-fixed bottom-0 end-0 m-4">
        <a href="register.php" class="btn btn-blue rounded-circle p-3 shadow-lg">
            <i class="bi bi-plus-lg fs-4"></i>
        </a>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js"></script>
    <script>
        // Check for Logout Success
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('logout') && urlParams.get('logout') === 'success') {
            Swal.fire({
                title: 'Logged Out',
                text: 'You have been safely returned to the main portal. See you soon!',
                icon: 'info',
                confirmButtonColor: '#2d6a4f',
                background: '#fff',
                color: '#2d6a4f'
            });
        }
    </script>
</body>
</html>



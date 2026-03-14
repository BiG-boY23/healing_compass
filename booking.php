<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once 'config/database.php';

$role     = $_SESSION['role'];
$username = $_SESSION['username'];
$user_id  = $_SESSION['user_id'];

$healer_id = (int)($_GET['healer_id'] ?? 0);
$healer    = null;

if ($healer_id) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM healers WHERE id = ?");
        $stmt->execute([$healer_id]);
        $healer = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {}
}

if (!$healer) {
    header("Location: healers.php");
    exit();
}

// Parse specialty tags
$specTags = preg_split('/[,\/|\n]+/', $healer['specialization'] ?? '');
$specTags = array_slice(array_filter(array_map('trim', $specTags)), 0, 3);

// Time slots
$timeSlots = [
    '09:00' => '9:00 AM',
    '10:00' => '10:00 AM',
    '11:00' => '11:00 AM',
    '13:00' => '1:00 PM',
    '14:00' => '2:00 PM',
    '15:00' => '3:00 PM',
    '16:00' => '4:00 PM',
    '17:00' => '5:00 PM',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Appointment — <?= htmlspecialchars($healer['full_name']) ?> | Healing Compass</title>
    <meta name="description" content="Book a traditional healing consultation with <?= htmlspecialchars($healer['full_name']) ?>.">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Lexend:wght@300;400;500;600;700;800&family=Outfit:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        /* Time chip grid */
        .time-chip-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 0.6rem;
        }

        .time-chip {
            background: #F7FAF5;
            border: 2px solid #E8EDE0;
            border-radius: 1rem;
            padding: 10px 8px;
            text-align: center;
            font-size: 0.78rem;
            font-weight: 600;
            color: var(--nature-forest);
            cursor: pointer;
            transition: all 0.2s ease;
            user-select: none;
        }

        .time-chip:hover {
            background: var(--nature-accent);
            border-color: var(--nature-forest);
        }

        .time-chip.selected {
            background: var(--nature-forest);
            border-color: var(--nature-forest);
            color: white;
            box-shadow: 0 5px 15px rgba(45, 79, 50, 0.25);
        }

        /* Sage inputs */
        .sage-input {
            background: #F7FAF5 !important;
            border: 2px solid #E8EDE0 !important;
            border-radius: 1rem !important;
            color: var(--nature-forest) !important;
            padding: 14px 18px !important;
            font-family: 'Lexend', sans-serif !important;
            font-size: 0.9rem !important;
            transition: border-color 0.2s ease !important;
        }

        .sage-input:focus {
            outline: none !important;
            box-shadow: none !important;
            border-color: var(--nature-forest) !important;
        }

        .input-icon-wrap {
            position: relative;
        }

        .input-icon-wrap .icon-prefix {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            pointer-events: none;
            color: var(--nature-forest);
            opacity: 0.5;
        }

        .input-icon-wrap .sage-input {
            padding-left: 44px !important;
        }

        /* Booking card */
        .booking-card {
            background: white;
            border-radius: 2.5rem;
            padding: 2.5rem;
            border: 1px solid rgba(0,0,0,0.03);
            box-shadow: 0 20px 60px rgba(45, 79, 50, 0.06);
        }

        /* Healer preview strip */
        .healer-preview {
            background: #F7FAF5;
            border-radius: 1.5rem;
            padding: 1.2rem 1.5rem;
            border: 1px solid #E8EDE0;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .healer-preview-avatar {
            width: 58px;
            height: 58px;
            border-radius: 50%;
            background-size: cover;
            background-position: center;
            background-color: var(--nature-accent);
            border: 3px solid white;
            box-shadow: 0 4px 10px rgba(0,0,0,0.08);
            flex-shrink: 0;
        }

        .form-label-nature {
            font-size: 0.8rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            color: var(--nature-forest);
            opacity: 0.7;
            margin-bottom: 10px;
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
                <a href="dashboard.php?view=plants" class="sidebar-link">
                    <i data-lucide="sprout"></i> Medicinal Plants
                </a>
                <a href="healers.php" class="sidebar-link active">
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

        <!-- Main Content -->
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-5 py-5 min-vh-100 d-flex flex-column">

            <!-- Back link -->
            <div class="mb-4">
                <a href="healers.php" class="back-btn text-decoration-none d-inline-flex align-items-center gap-2"
                   style="background: white; border: 1px solid rgba(0,0,0,0.06); border-radius: 100px; padding: 10px 20px; font-weight: 600; color: var(--nature-forest); font-size: 0.9rem; box-shadow: 0 2px 10px rgba(0,0,0,0.05); transition: all 0.2s ease;">
                    <i data-lucide="arrow-left" style="width: 16px;"></i>
                    Back to Directory
                </a>
            </div>

            <!-- Page Header -->
            <div class="mb-5">
                <h1 class="fw-bold mb-1" style="font-size: 2.4rem; letter-spacing: -1.5px; color: var(--nature-forest);">Book an Appointment</h1>
                <p class="text-muted fw-medium mb-0">Choose your preferred date and time for consultation.</p>
            </div>

            <!-- Centered Booking Card -->
            <div class="row justify-content-center">
                <div class="col-lg-7 col-xl-6">
                    <div class="booking-card">

                        <!-- Healer Preview Strip -->
                        <div class="healer-preview mb-4">
                            <div class="healer-preview-avatar"
                                 style="background-image: url('<?= htmlspecialchars($healer['profile_picture'] ?? '') ?>');">
                                <?php if (empty($healer['profile_picture'])): ?>
                                    <div class="d-flex align-items-center justify-content-center h-100">
                                        <i data-lucide="user" style="width: 24px; color: var(--nature-forest); opacity: 0.4;"></i>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="flex-grow-1">
                                <h6 class="fw-bold mb-0" style="color: var(--nature-forest);"><?= htmlspecialchars($healer['full_name']) ?></h6>
                                <div class="d-flex align-items-center gap-1 mt-1">
                                    <?php foreach ($specTags as $tag): ?>
                                        <span class="badge rounded-pill" style="background: var(--nature-accent); color: var(--nature-forest); font-size: 0.65rem; padding: 4px 10px; font-weight: 600;">
                                            <?= htmlspecialchars($tag) ?>
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <div>
                                <span class="badge rounded-pill px-3 py-2" style="background: rgba(45, 79, 50, 0.08); color: var(--nature-forest); font-size: 0.7rem; font-weight: 700;">
                                    <i data-lucide="shield-check" style="width: 11px;"></i> Verified
                                </span>
                            </div>
                        </div>

                        <!-- Form -->
                        <form action="controllers/BookingController.php" method="POST" id="bookingForm">
                            <input type="hidden" name="healer_id" value="<?= htmlspecialchars($healer_id) ?>">
                            <input type="hidden" name="appointment_time" id="selectedTime">

                            <!-- Date Picker -->
                            <div class="mb-4">
                                <label class="form-label-nature">Consultation Date</label>
                                <div class="input-icon-wrap">
                                    <i data-lucide="calendar" class="icon-prefix" style="width: 17px;"></i>
                                    <input type="date" name="date" class="sage-input form-control"
                                           required min="<?= date('Y-m-d') ?>"
                                           id="dateInput">
                                </div>
                            </div>

                            <!-- Time Chip Grid -->
                            <div class="mb-4">
                                <label class="form-label-nature">Preferred Time</label>
                                <div class="time-chip-grid" id="timeChipGrid">
                                    <?php foreach ($timeSlots as $value => $label): ?>
                                        <div class="time-chip" data-value="<?= $value ?>" onclick="selectTime(this, '<?= $value ?>')">
                                            <?= $label ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <p class="text-muted small mt-2 mb-0" id="timeValidationMsg" style="display: none; color: #dc3545 !important;">
                                    Please select a preferred time.
                                </p>
                            </div>

                            <!-- Notes -->
                            <div class="mb-4">
                                <label class="form-label-nature">Describe Your Condition <span class="fw-normal opacity-50">(Optional)</span></label>
                                <textarea name="notes" rows="4" class="sage-input form-control"
                                          placeholder="Briefly describe what you'd like help with (e.g., chronic back pain, skin irritation)..."></textarea>
                            </div>

                            <!-- Submit -->
                            <button type="submit" class="btn w-100 fw-bold py-3 rounded-pill mb-3"
                                    style="background: var(--nature-forest); color: white; border: none; font-size: 1rem; letter-spacing: 0.3px; box-shadow: 0 8px 25px rgba(45, 79, 50, 0.25);">
                                <i data-lucide="calendar-check" class="me-2" style="width: 18px;"></i>
                                Confirm Booking Request
                            </button>
                            <a href="healers.php" class="btn w-100 rounded-pill py-2 fw-semibold"
                               style="background: #F7FAF5; color: var(--nature-forest); border: 2px solid #E8EDE0; font-size: 0.9rem;">
                                Cancel
                            </a>
                        </form>
                    </div>

                    <!-- Info Note -->
                    <div class="mt-4 p-3 d-flex align-items-start gap-3 rounded-4" style="background: rgba(45, 79, 50, 0.04); border: 1px solid rgba(45, 79, 50, 0.08);">
                        <i data-lucide="info" class="flex-shrink-0 mt-1" style="width: 16px; color: var(--nature-forest); opacity: 0.6;"></i>
                        <p class="small text-muted mb-0 lh-base">Your request will be sent to the <strong>Barangay Health Worker (BHW)</strong> in charge of this healer. They will coordinate your schedule and notify you once the appointment is confirmed.</p>
                    </div>
                </div>
            </div>

        </main>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    lucide.createIcons();

    function selectTime(el, value) {
        // Deselect all
        document.querySelectorAll('.time-chip').forEach(c => c.classList.remove('selected'));
        // Select clicked
        el.classList.add('selected');
        document.getElementById('selectedTime').value = value;
        document.getElementById('timeValidationMsg').style.display = 'none';
    }

    // Form validation
    document.getElementById('bookingForm').addEventListener('submit', function(e) {
        const selectedTime = document.getElementById('selectedTime').value;
        if (!selectedTime) {
            e.preventDefault();
            document.getElementById('timeValidationMsg').style.display = 'block';
            document.getElementById('timeChipGrid').scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    });

    // SweetAlert on success
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('success') === '1') {
        Swal.fire({
            title: 'Booking Sent!',
            text: 'Your consultation request has been submitted successfully.',
            icon: 'success',
            confirmButtonColor: '#2D4F32',
            confirmButtonText: 'Back to Healers',
        }).then(() => {
            window.location.href = 'healers.php';
        });
    }
    if (urlParams.get('error')) {
        Swal.fire({
            title: 'Booking Failed',
            text: urlParams.get('error'),
            icon: 'error',
            confirmButtonColor: '#2D4F32'
        });
    }
</script>
</body>
</html>

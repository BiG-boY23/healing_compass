<?php
session_start();
require_once 'config/database.php';
require_once 'api/totp_helper.php';

// Check if user has a pending 2FA verification
if (!isset($_SESSION['2fa_user_id'])) {
    header("Location: login.php");
    exit();
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['token'] ?? '';
    $userId = $_SESSION['2fa_user_id'];

    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (TOTPHelper::verifyToken($user['totp_secret'], $token)) {
            // TOTP Correct: Finalize Login
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['2fa_verified'] = true;

            // Clear pending session
            unset($_SESSION['2fa_user_id']);

            // Redirect based on role
            if ($user['role'] === 'admin') {
                header("Location: admin-dashboard.php");
            } else {
                header("Location: dashboard.php");
            }
            exit();
        } else {
            $error = "Verification failed. The code provided is incorrect or has expired.";
        }
    } catch (PDOException $e) {
        $error = "Internal system error. Please contact technical support.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>2FA Verification | Healing Compass</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .otp-input-field {
            font-size: 2.5rem; letter-spacing: 1.5rem; font-weight: 800; text-align: center; border: 3px solid #e9f5ef; border-radius: 15px; background: #fdfdfd; padding: 15px; color: #2d6a4f; margin-bottom: 25px;
        }
        .otp-input-field:focus { box-shadow: 0 0 20px rgba(45, 106, 79, 0.1); border-color: #2d6a4f; outline: none; }
    </style>
</head>
<body class="bg-light d-flex align-items-center justify-content-center" style="min-height: 100vh;">

    <div class="auth-card p-5 text-center shadow-lg bg-white rounded-5" style="max-width: 450px; width: 95%;">
        <div class="mb-4">
            <img src="assets/img/logo.png" alt="Logo" style="height: 60px;" class="mb-3">
            <h2 class="fw-extrabold text-success">Secure Identity</h2>
            <p class="text-muted small">Enter the 6-digit verification code from your authenticator app.</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger bg-danger bg-opacity-10 border-0 text-danger rounded-4 small p-3 mb-4">
                <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <label class="small text-muted mb-2 text-uppercase fw-bold" style="letter-spacing: 1px;">Security Token</label>
            <input type="text" name="token" class="form-control otp-input-field" placeholder="------" maxlength="6" pattern="\d{6}" required autofocus autocomplete="one-time-code">
            
            <button type="submit" class="btn btn-success w-100 rounded-pill py-3 fw-bold shadow-sm">
                <span>Authorize Access</span>
                <i class="bi bi-shield-check ms-2"></i>
            </button>
        </form>

        <div class="mt-4 pt-3 border-top">
            <a href="login.php" class="text-muted small text-decoration-none">
                <i class="bi bi-arrow-left me-1"></i> Return to Sign In
            </a>
        </div>
    </div>

    <script>
        // SweetAlert error for initial view
        <?php if ($error): ?>
        Swal.fire({
            title: 'Unauthorized',
            text: '<?= $error ?>',
            icon: 'error',
            confirmButtonColor: '#2d6a4f'
        });
        <?php endif; ?>
    </script>
</body>
</html>

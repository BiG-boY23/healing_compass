<?php
session_start();
require_once 'config/database.php';
require_once 'api/totp_helper.php';

// Prepare Admin
$username = 'Root Administrator';
$email = 'admin@healingcompass.com';
$password = password_hash('admin123', PASSWORD_DEFAULT);
$role = 'admin';

// Generate TOTP Secret for security
$secret = TOTPHelper::generateSecret();

try {
    // Re-insert admin to ID 1
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0; DELETE FROM users; ALTER TABLE users AUTO_INCREMENT = 1; SET FOREIGN_KEY_CHECKS = 1;");
    $stmt = $pdo->prepare("INSERT INTO users (id, username, email, password, role, totp_secret, is_totp_enabled) VALUES (1, ?, ?, ?, ?, ?, 1)");
    $stmt->execute([$username, $email, $password, $role, $secret]);
} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

$qrUrl = TOTPHelper::getQRUrl($email, $secret);
$qrImage = "https://chart.googleapis.com/chart?chs=200x200&cht=qr&chl=" . urlencode($qrUrl);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin TOTP Setup | Healing Compass</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Outfit', sans-serif; background: #f8faf9; color: #2d6a4f; padding: 50px 0; }
        .setup-card { max-width: 500px; margin: auto; background: white; border-radius: 20px; padding: 40px; box-shadow: 0 10px 30px rgba(0,0,0,0.05); text-center; }
        .qr-frame { background: #e9f5ef; padding: 20px; border-radius: 15px; display: inline-block; margin: 20px 0; }
        .secret-key { background: #f1f3f2; padding: 10px; border-radius: 8px; font-family: monospace; font-size: 1.2rem; letter-spacing: 2px; }
    </style>
</head>
<body>
    <div class="setup-card text-center">
        <h2 class="fw-bold mb-4">🔐 2FA Admin Protocol</h2>
        <p class="text-muted">You are the primary root administrator. To secure your account, scan this code with your Authenticator App (Google/Authy).</p>
        
        <div class="qr-frame">
            <img src="<?= $qrImage ?>" alt="QR Code">
        </div>
        
        <div class="my-4">
            <p class="small text-muted mb-1">Backup Key (Manual Entry):</p>
            <div class="secret-key"><?= $secret ?></div>
        </div>

        <div class="alert alert-warning small text-start">
            <strong>CRITICAL:</strong> Save this key. If you lose access to your authenticator device, you will be locked out of the administrative systems.
        </div>

        <a href="login.php" class="btn btn-success btn-lg rounded-pill px-5 mt-3 shadow-sm">Proceed to Login</a>
    </div>
</body>
</html>

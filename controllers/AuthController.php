<?php
session_start();
require_once '../config/database.php';
require_once '../audit_logger.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'register') {
        register($pdo);
    } elseif ($action === 'login') {
        login($pdo);
    } elseif ($action === 'google_login') {
        googleLogin($pdo);
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';
    if ($action === 'logout') {
        logout();
    }
}

function logout() {
    session_start();
    // Log the logout before destroying session
    global $pdo;
    if (isset($_SESSION['user_id'])) {
        auditLog($pdo, $_SESSION['user_id'], $_SESSION['username'] ?? 'unknown', $_SESSION['role'] ?? 'user', 'User Logout', null, 'success');
    }
    session_unset();
    session_destroy();
    header("Location: ../index.php?logout=success");
    exit();
}

function register($pdo) {
    $username         = $_POST['username'];
    $email            = $_POST['email'];
    $password         = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $role             = $_POST['role'] ?? 'user';

    if ($password !== $confirm_password) {
        die("Passwords do not match.");
    }

    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    try {
        $stmt = $pdo->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)");
        $stmt->execute([$username, $email, $hashed_password, $role]);
        $newId = $pdo->lastInsertId();

        // ── Audit: new registration ──
        auditLog($pdo, $newId, $username, $role, 'New Account Registered', 'Email: ' . $email, 'success');

        header("Location: ../login.php?registered=success");
        exit();
    } catch (PDOException $e) {
        header("Location: ../register.php?error=" . urlencode("User already exists or database error."));
        exit();
    }
}

function login($pdo) {
    $email    = $_POST['email'];
    $password = $_POST['password'];

    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id']  = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role']     = $user['role'];

            // ── Audit: successful login ──
            auditLog($pdo, $user['id'], $user['username'], $user['role'], 'User Login', 'Email: ' . $email, 'success');

            if ($user['role'] === 'admin') {
                header("Location: ../admin-dashboard.php");
            } elseif ($user['role'] === 'bhw') {
                $_SESSION['barangay'] = $user['barangay'];
                header("Location: ../bhw-dashboard.php");
            } else {
                header("Location: ../dashboard.php");
            }
            exit();
        } else {
            // ── Audit: failed login ──
            auditLog($pdo, null, 'unknown', 'unknown', 'Failed Login Attempt', 'Email: ' . $email, 'failed');
            header("Location: ../login.php?error=" . urlencode("Invalid email or password."));
            exit();
        }
    } catch (PDOException $e) {
        header("Location: ../login.php?error=" . urlencode("Connection error."));
        exit();
    }
}

function googleLogin($pdo) {
    // In a production environment, you would use Google's PHP library to verify the token:
    // $client = new Google_Client(['client_id' => $CLIENT_ID]);
    // $payload = $client->verifyIdToken($id_token);

    // For demonstration, we'll assume the token can be decoded to find the user
    // Since we don't have the Google Library installed, we'll suggest using it for verification
    
    $id_token = $_POST['id_token'];
    
    // Decoding the JWT (Basic approach - only for demonstration!)
    $parts = explode('.', $id_token);
    if(count($parts) < 2) die("Invalid Token");
    $payload = json_decode(base64_decode($parts[1]), true);
    
    if($payload) {
        $email = $payload['email'];
        $name = $payload['name'];
        
        try {
            // Check if user exists
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user) {
                // Auto-register if user doesn't exist
                // Note: password is NOT NULL in schema, so we provide a placeholder
                $placeholder_password = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, 'user')");
                $stmt->execute([$name, $email, $placeholder_password]);
                
                // Fetch the new user
                $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
                $stmt->execute([$email]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                // Audit: new registration via Google
                auditLog($pdo, $user['id'], $name, 'user', 'Google Account Registered', 'Email: ' . $email, 'success');
            } else {
                // Audit: successful login via Google
                auditLog($pdo, $user['id'], $user['username'], $user['role'], 'Google Login', 'Email: ' . $email, 'success');
            }
            
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            
            if ($user['role'] === 'admin') {
                header("Location: ../admin-dashboard.php");
            } elseif ($user['role'] === 'bhw') {
                $_SESSION['barangay'] = $user['barangay'];
                header("Location: ../bhw-dashboard.php");
            } else {
                header("Location: ../dashboard.php");
            }
        } catch (PDOException $e) {
            die("Error: " . $e->getMessage());
        }
    } else {
        die("Failed to decode Google Token");
    }
}
?>

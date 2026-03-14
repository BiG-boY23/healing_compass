<?php
// ── Load OAuth credentials from funnelOAuth.json ────────────────────────
$oauthFile = __DIR__ . '/funnelOAuth.json';
$googleClientId = '';

if (file_exists($oauthFile)) {
    $oauthData = json_decode(file_get_contents($oauthFile), true);
    // Support both Google API JSON shapes:
    // { "web": { "client_id": "..." } }  — downloaded from Google Cloud Console
    // { "client_id": "..." }              — custom flat shape
    $googleClientId = $oauthData['web']['client_id']
                   ?? $oauthData['client_id']
                   ?? '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | Healing Compass</title>
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
    <!-- Google Identity Services -->
    <script src="https://accounts.google.com/gsi/client" async defer></script>
</head>
<body>

    <div class="auth-wrapper">
        <!-- Visual Side -->
        <div class="auth-side-image bg-login">
            <div class="auth-overlay"></div>
            <div class="auth-side-content">
                <img src="assets/img/logo.png" alt="Logo" style="height: 60px; filter: brightness(0) invert(1);" class="mb-4">
                <h1 class="display-4 fw-bold mb-3 text-white">Rediscover <br>Nature's Secret</h1>
                <p class="lead text-white-50">Join thousands of people who trust our traditional wisdom and modern intelligence for their wellness journey.</p>
            </div>
        </div>

        <!-- Form Side -->
        <div class="auth-main">
            <div class="auth-card">
                <div class="mb-5">
                    <a href="index.php" class="text-decoration-none d-inline-block mb-4">
                        <div class="d-flex align-items-center text-primary fw-bold">
                            <i class="bi bi-arrow-left me-2"></i> Back to Home
                        </div>
                    </a>
                    <h2 class="fw-extrabold text-heading display-6 mb-2">Welcome Back</h2>
                    <p class="text-muted">Please enter your credentials to continue</p>
                </div>

                <form action="controllers/AuthController.php" method="POST" id="loginForm">
                    <input type="hidden" name="action" value="login">
                    
                    <div class="input-group-custom">
                        <i class="bi bi-envelope-at"></i>
                        <input type="email" name="email" class="form-control" placeholder="Email Address" required>
                    </div>

                    <div class="input-group-custom">
                        <i class="bi bi-shield-lock"></i>
                        <input type="password" name="password" id="passwordInput" class="form-control" placeholder="Password" required>
                        <div class="password-toggle" id="togglePassword" style="font-size: 1.5rem; filter: grayscale(1) brightness(1.2);">
                            <span id="toggleEmoji">😴</span>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="rememberMe">
                            <label class="form-check-label small text-muted" for="rememberMe">Remember me</label>
                        </div>
                        <a href="#" class="small text-primary text-decoration-none fw-semibold">Forgot Password?</a>
                    </div>

                    <button type="submit" class="btn btn-blue w-100 py-3 mb-4 shadow-sm">
                        <span>Sign In to Platform</span>
                        <i class="bi bi-arrow-right ms-2"></i>
                    </button>

                    <div class="d-flex align-items-center my-4">
                        <hr class="flex-grow-1">
                        <span class="mx-3 text-muted small">OR</span>
                        <hr class="flex-grow-1">
                    </div>

                    <!-- Google Login Container -->
                    <div id="g_id_onload"
                        data-client_id="<?= htmlspecialchars($googleClientId) ?>"
                        data-context="signin"
                        data-ux_mode="popup"
                        data-callback="handleCredentialResponse"
                        data-auto_prompt="false">
                    </div>

                    <div class="g_id_signin w-100"
                        data-type="standard"
                        data-shape="rectangular"
                        data-theme="outline"
                        data-text="signin_with"
                        data-size="large"
                        data-logo_alignment="left"
                        data-width="400">
                    </div>
                </form>

                <div class="text-center">
                    <p class="text-muted small">New to Healing Compass? <a href="register.php" class="text-primary fw-bold text-decoration-none">Create an Account</a></p>
                </div>

                <div class="mt-5 pt-4 border-top">
                    <div class="d-flex align-items-center justify-content-between">
                        <span class="small text-muted">&copy; 2026 CHOCOBOL</span>
                        <div class="d-flex gap-3">
                            <i class="bi bi-facebook text-muted"></i>
                            <i class="bi bi-instagram text-muted"></i>
                            <i class="bi bi-twitter-x text-muted"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

<script>
        // Check for URL parameters (SweetAlert)
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('registered') && urlParams.get('registered') === 'success') {
            Swal.fire({
                title: 'Welcome Aboard!',
                text: 'Your journey with Healing Compass begins. Please sign in.',
                icon: 'success',
                confirmButtonColor: '#2d6a4f',
                background: '#fff',
                color: '#2d6a4f'
            });
        }
        if (urlParams.has('error')) {
            Swal.fire({
                title: 'Access Denied',
                text: urlParams.get('error'),
                icon: 'error',
                confirmButtonColor: '#2d6a4f',
                background: '#fff',
                color: '#2d6a4f'
            });
        }

        document.getElementById('togglePassword').addEventListener('click', function() {
            const passwordInput = document.getElementById('passwordInput');
            const toggleEmoji = document.getElementById('toggleEmoji');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleEmoji.textContent = '🤯';
                this.style.filter = 'grayscale(0) brightness(1)';
            } else {
                passwordInput.type = 'password';
                toggleEmoji.textContent = '😴';
                this.style.filter = 'grayscale(1) brightness(1.2)';
            }
            
            // "Aha!" moment animation
            toggleEmoji.style.display = 'inline-block';
            toggleEmoji.animate([
                { transform: 'scale(1)', rotate: '0deg' },
                { transform: 'scale(1.4)', rotate: '15deg' },
                { transform: 'scale(1)', rotate: '0deg' }
            ], {
                duration: 400,
                easing: 'ease-out'
            });
        });

        // Handle Google OAuth2 Response
        function handleCredentialResponse(response) {
            console.log("Encoded JWT ID token: " + response.credential);
            
            // To implement this fully, you need to send this token to your backend
            // for verification and user session creation.
            
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'controllers/AuthController.php';
            
            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = 'google_login';
            form.appendChild(actionInput);
            
            const tokenInput = document.createElement('input');
            tokenInput.type = 'hidden';
            tokenInput.name = 'id_token';
            tokenInput.value = response.credential;
            form.appendChild(tokenInput);
            
            document.body.appendChild(form);
            form.submit();
        }
    </script>
</body>
</html>


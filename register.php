<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Join | Healing Compass</title>
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

    <div class="auth-wrapper">
        <!-- Visual Side -->
        <div class="auth-side-image bg-register">
            <div class="auth-overlay"></div>
            <div class="auth-side-content">
                <img src="assets/img/logo.png" alt="Logo" style="height: 60px; filter: brightness(0) invert(1);" class="mb-4">
                <h1 class="display-4 fw-bold mb-3 text-white">Join the <br>Ancient Path</h1>
                <p class="lead text-white-50">Create your account to document plants, consult verified healers, and contribute to our global wisdom archive.</p>
            </div>
        </div>

        <!-- Form Side -->
        <div class="auth-main">
            <div class="auth-card" style="max-width: 550px;">
                <div class="mb-5">
                    <a href="index.php" class="text-decoration-none d-inline-block mb-4">
                        <div class="d-flex align-items-center text-primary fw-bold">
                            <i class="bi bi-arrow-left me-2"></i> Back to Home
                        </div>
                    </a>
                    <h2 class="fw-extrabold text-heading display-6 mb-2">Create Account</h2>
                    <p class="text-muted">Start your wellness journey with Healing Compass</p>
                </div>

                <form action="controllers/AuthController.php" method="POST">
                    <input type="hidden" name="action" value="register">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="input-group-custom">
                                <i class="bi bi-person"></i>
                                <input type="text" name="username" class="form-control" placeholder="Full Name" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="input-group-custom">
                                <i class="bi bi-envelope"></i>
                                <input type="email" name="email" class="form-control" placeholder="Email Address" required>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="input-group-custom">
                                <i class="bi bi-lock"></i>
                                <input type="password" name="password" id="regPassword" class="form-control" placeholder="Password" required>
                                <div class="password-toggle" onclick="toggleVisibility('regPassword', 'regIcon1')" style="font-size: 1.3rem; filter: grayscale(1) brightness(1.2);">
                                    <span id="regIcon1">😴</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="input-group-custom">
                                <i class="bi bi-shield-check"></i>
                                <input type="password" name="confirm_password" id="confirmPassword" class="form-control" placeholder="Confirm" required>
                                <div class="password-toggle" onclick="toggleVisibility('confirmPassword', 'regIcon2')" style="font-size: 1.3rem; filter: grayscale(1) brightness(1.2);">
                                    <span id="regIcon2">😴</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="input-group-custom">
                        <i class="bi bi-tags"></i>
                        <select name="role" class="form-select form-control ps-5" required>
                            <option value="user">General User (Seeking health advice)</option>
                            <option value="healer">Traditional Healer (Offering services)</option>
                        </select>
                    </div>

                    <div class="form-check mb-4">
                        <input class="form-check-input" type="checkbox" id="terms" required>
                        <label class="form-check-label small text-muted" for="terms">
                            I agree to the <a href="#" class="text-primary text-decoration-none">Terms of Service</a> and <a href="#" class="text-primary text-decoration-none">Privacy Policy</a>
                        </label>
                    </div>

                    <button type="submit" class="btn btn-blue w-100 py-3 mb-4 shadow-sm">
                        <span>Initialize My Profile</span>
                        <i class="bi bi-person-plus ms-2"></i>
                    </button>
                </form>

                <div class="text-center">
                    <p class="text-muted small">Already a member? <a href="login.php" class="text-primary fw-bold text-decoration-none">Sign In Instead</a></p>
                </div>

                <div class="mt-5 pt-4 border-top">
                    <div class="d-flex align-items-center justify-content-between">
                        <span class="small text-muted">&copy; 2026 CHOCOBOL</span>
                        <div class="d-flex gap-3 text-muted small">
                            <span>TRADEMARK REGISTERED</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

<script>
        // Check for URL parameters (SweetAlert)
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('error')) {
            Swal.fire({
                title: 'Registration Failed',
                text: urlParams.get('error'),
                icon: 'error',
                confirmButtonColor: '#2d6a4f',
                background: '#fff',
                color: '#2d6a4f'
            });
        }

        function toggleVisibility(inputId, iconId) {
            const input = document.getElementById(inputId);
            const icon = document.getElementById(iconId);
            const wrapper = icon.parentElement;
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.textContent = '🤯';
                wrapper.style.filter = 'grayscale(0) brightness(1)';
            } else {
                input.type = 'password';
                icon.textContent = '😴';
                wrapper.style.filter = 'grayscale(1) brightness(1.2)';
            }

            icon.animate([
                { transform: 'scale(1)', rotate: '0deg' },
                { transform: 'scale(1.4)', rotate: '15deg' },
                { transform: 'scale(1)', rotate: '0deg' }
            ], {
                duration: 400,
                easing: 'ease-out'
            });
        }
    </script>
</body>
</html>

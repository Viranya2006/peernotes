<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: profile.php');
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitizeInput($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $error = 'Please enter both email and password.';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT id, email, password, name, is_admin FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password'])) {
                // Login successful
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['name'] = $user['name'];
                $_SESSION['is_admin'] = $user['is_admin'];
                
                // Log activity
                $stmt = $pdo->prepare("INSERT INTO activity_log (user_id, action, ip_address, user_agent) VALUES (?, 'login', ?, ?)");
                $stmt->execute([$user['id'], $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '']);
                
                // Redirect to profile or intended page
                $redirect = $_GET['redirect'] ?? 'profile.php';
                header('Location: ' . $redirect);
                exit();
            } else {
                $error = 'Invalid email or password.';
            }
        } catch (PDOException $e) {
            $error = 'Login failed. Please try again.';
            error_log("Login error: " . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - PeerNotes</title>
    <meta name="description" content="Login to your PeerNotes account to access academic resources">
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand fw-bold" href="index.php">
                <i class="bi bi-book-half me-2"></i>PeerNotes
            </a>
            
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="index.php">Home</a>
                <a class="nav-link" href="register.php">Register</a>
            </div>
        </div>
    </nav>

    <!-- Login Form -->
    <section class="py-5">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-5 col-md-7">
                    <div class="login-card">
                        <div class="card-header text-center">
                            <div class="login-icon mb-3">
                                <i class="bi bi-person-circle"></i>
                            </div>
                            <h2 class="card-title">Welcome Back</h2>
                            <p class="card-subtitle">Sign in to your PeerNotes account</p>
                        </div>
                        
                        <div class="card-body">
                            <?php if ($error): ?>
                                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                    <i class="bi bi-exclamation-triangle me-2"></i>
                                    <?php echo $error; ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                </div>
                            <?php endif; ?>
                            
                            <form method="POST" id="loginForm" novalidate>
                                <div class="mb-3">
                                    <label for="email" class="form-label">
                                        <i class="bi bi-envelope me-2"></i>Email Address
                                    </label>
                                    <input type="email" class="form-control" id="email" name="email" 
                                           value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" 
                                           required autocomplete="email" autofocus>
                                    <div class="invalid-feedback"></div>
                                </div>
                                
                                <div class="mb-4">
                                    <label for="password" class="form-label">
                                        <i class="bi bi-lock me-2"></i>Password
                                    </label>
                                    <div class="password-input-group">
                                        <input type="password" class="form-control" id="password" name="password" 
                                               required autocomplete="current-password">
                                        <button type="button" class="btn btn-outline-secondary password-toggle" 
                                                onclick="togglePassword('password')">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                    </div>
                                    <div class="invalid-feedback"></div>
                                </div>
                                
                                <div class="mb-3 form-check">
                                    <input type="checkbox" class="form-check-input" id="remember" name="remember">
                                    <label class="form-check-label" for="remember">
                                        Remember me
                                    </label>
                                </div>
                                
                                <button type="submit" class="btn btn-primary btn-lg w-100 mb-3">
                                    <i class="bi bi-box-arrow-in-right me-2"></i>Sign In
                                </button>
                            </form>
                            
                            <div class="text-center">
                                <p class="mb-0">
                                    Don't have an account? 
                                    <a href="register.php" class="text-decoration-none fw-semibold">Create one here</a>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer bg-dark text-light py-4 mt-5">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <h5><i class="bi bi-book-half me-2"></i>PeerNotes</h5>
                    <p class="mb-0">Empowering Sri Lankan students through shared knowledge.</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <p class="mb-0">&copy; 2024 PeerNotes. All rights reserved.</p>
                </div>
            </div>
        </div>
    </footer>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Custom JS -->
    <script src="assets/js/main.js"></script>
    <script>
        // Form validation
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('loginForm');
            const validator = new FormValidator(form);
            
            // Add validation rules
            validator.addRule('email', {
                validator: (value) => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value),
                message: 'Please enter a valid email address'
            });
            
            validator.addRule('password', {
                validator: (value) => value.length >= 1,
                message: 'Password is required'
            });
            
            // Add loading state to form submission
            form.addEventListener('submit', function() {
                const submitBtn = form.querySelector('button[type="submit"]');
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Signing In...';
            });
        });
        
        // Password toggle function
        function togglePassword(fieldId) {
            const field = document.getElementById(fieldId);
            const button = field.nextElementSibling;
            const icon = button.querySelector('i');
            
            if (field.type === 'password') {
                field.type = 'text';
                icon.className = 'bi bi-eye-slash';
            } else {
                field.type = 'password';
                icon.className = 'bi bi-eye';
            }
        }
        
        // Add keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Focus email field with Ctrl+E or Cmd+E
            if ((e.ctrlKey || e.metaKey) && e.key === 'e') {
                e.preventDefault();
                document.getElementById('email').focus();
            }
        });
    </script>
    
    <style>
        .login-card {
            background: var(--bg-primary);
            border-radius: 20px;
            box-shadow: var(--shadow-xl);
            border: 1px solid var(--border-color);
            overflow: hidden;
            margin-top: 2rem;
        }
        
        .card-header {
            background: var(--gradient-primary);
            color: white;
            padding: 2rem;
        }
        
        .login-icon {
            font-size: 3rem;
            opacity: 0.9;
        }
        
        .card-title {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        
        .card-subtitle {
            opacity: 0.9;
            margin-bottom: 0;
        }
        
        .card-body {
            padding: 2rem;
        }
        
        .password-input-group {
            position: relative;
        }
        
        .password-toggle {
            position: absolute;
            right: 0;
            top: 0;
            height: 100%;
            border-left: none;
            border-top-left-radius: 0;
            border-bottom-left-radius: 0;
        }
        
        .form-control:focus + .password-toggle {
            border-color: var(--primary-color);
        }
        
        .alert {
            border-radius: 12px;
            border: none;
        }
        
        .alert-danger {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger-color);
        }
        
        .form-check-input:checked {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        @media (max-width: 768px) {
            .login-card {
                margin-top: 1rem;
            }
            
            .card-header {
                padding: 1.5rem;
            }
            
            .card-body {
                padding: 1.5rem;
            }
        }
    </style>
</body>
</html>

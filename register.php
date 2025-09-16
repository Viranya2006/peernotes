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
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitizeInput($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $name = sanitizeInput($_POST['name'] ?? '');
    
    // Validation
    if (empty($email) || empty($password) || empty($confirmPassword) || empty($name)) {
        $error = 'All fields are required.';
    } elseif (!validateEmail($email)) {
        $error = 'Please enter a valid email address.';
    } elseif (!validatePassword($password)) {
        $error = 'Password must be at least 8 characters long.';
    } elseif ($password !== $confirmPassword) {
        $error = 'Passwords do not match.';
    } else {
        try {
            // Check if email already exists
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            
            if ($stmt->fetch()) {
                $error = 'Email address is already registered.';
            } else {
                // Hash password and insert user
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                
                $stmt = $pdo->prepare("INSERT INTO users (email, password, name, created_at) VALUES (?, ?, ?, NOW())");
                $stmt->execute([$email, $hashedPassword, $name]);
                
                $success = 'Account created successfully! Please login to continue.';
                
                // Redirect to login page after 2 seconds
                header('refresh:2;url=login.php');
            }
        } catch (PDOException $e) {
            $error = 'Registration failed. Please try again.';
            error_log("Registration error: " . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - PeerNotes</title>
    <meta name="description" content="Create your PeerNotes account to start sharing academic resources">
    
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
                <a class="nav-link" href="login.php">Login</a>
            </div>
        </div>
    </nav>

    <!-- Register Form -->
    <section class="py-5">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-6 col-md-8">
                    <div class="register-card">
                        <div class="card-header text-center">
                            <div class="register-icon mb-3">
                                <i class="bi bi-person-plus-fill"></i>
                            </div>
                            <h2 class="card-title">Create Account</h2>
                            <p class="card-subtitle">Join PeerNotes and start sharing academic resources</p>
                        </div>
                        
                        <div class="card-body">
                            <?php if ($error): ?>
                                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                    <i class="bi bi-exclamation-triangle me-2"></i>
                                    <?php echo $error; ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($success): ?>
                                <div class="alert alert-success alert-dismissible fade show" role="alert">
                                    <i class="bi bi-check-circle me-2"></i>
                                    <?php echo $success; ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                </div>
                            <?php endif; ?>
                            
                            <form method="POST" id="registerForm" novalidate>
                                <div class="mb-3">
                                    <label for="name" class="form-label">
                                        <i class="bi bi-person me-2"></i>Full Name
                                    </label>
                                    <input type="text" class="form-control" id="name" name="name" 
                                           value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>" 
                                           required autocomplete="name">
                                    <div class="invalid-feedback"></div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="email" class="form-label">
                                        <i class="bi bi-envelope me-2"></i>Email Address
                                    </label>
                                    <input type="email" class="form-control" id="email" name="email" 
                                           value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" 
                                           required autocomplete="email">
                                    <div class="invalid-feedback"></div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="password" class="form-label">
                                        <i class="bi bi-lock me-2"></i>Password
                                    </label>
                                    <div class="password-input-group">
                                        <input type="password" class="form-control" id="password" name="password" 
                                               required autocomplete="new-password">
                                        <button type="button" class="btn btn-outline-secondary password-toggle" 
                                                onclick="togglePassword('password')">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                    </div>
                                    <div class="form-text">Password must be at least 8 characters long</div>
                                    <div class="invalid-feedback"></div>
                                </div>
                                
                                <div class="mb-4">
                                    <label for="confirm_password" class="form-label">
                                        <i class="bi bi-lock-fill me-2"></i>Confirm Password
                                    </label>
                                    <div class="password-input-group">
                                        <input type="password" class="form-control" id="confirm_password" 
                                               name="confirm_password" required autocomplete="new-password">
                                        <button type="button" class="btn btn-outline-secondary password-toggle" 
                                                onclick="togglePassword('confirm_password')">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                    </div>
                                    <div class="invalid-feedback"></div>
                                </div>
                                
                                <button type="submit" class="btn btn-primary btn-lg w-100 mb-3">
                                    <i class="bi bi-person-plus me-2"></i>Create Account
                                </button>
                            </form>
                            
                            <div class="text-center">
                                <p class="mb-0">
                                    Already have an account? 
                                    <a href="login.php" class="text-decoration-none fw-semibold">Login here</a>
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
            const form = document.getElementById('registerForm');
            const validator = new FormValidator(form);
            
            // Add validation rules
            validator.addRule('name', {
                validator: (value) => value.length >= 2,
                message: 'Name must be at least 2 characters long'
            });
            
            validator.addRule('email', {
                validator: (value) => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value),
                message: 'Please enter a valid email address'
            });
            
            validator.addRule('password', {
                validator: (value) => value.length >= 8,
                message: 'Password must be at least 8 characters long'
            });
            
            validator.addRule('confirm_password', {
                validator: (value) => {
                    const password = document.getElementById('password').value;
                    return value === password;
                },
                message: 'Passwords do not match'
            });
            
            // Real-time password confirmation validation
            document.getElementById('confirm_password').addEventListener('input', function() {
                validator.validateField('confirm_password');
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
    </script>
    
    <style>
        .register-card {
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
        
        .register-icon {
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
        
        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success-color);
        }
        
        @media (max-width: 768px) {
            .register-card {
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

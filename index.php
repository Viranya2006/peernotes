<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PeerNotes - Share & Discover Academic Resources</title>
    <meta name="description" content="Centralized platform for university students in Sri Lanka to share and discover academic resources">
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <!-- AOS Animation Library -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="assets/css/style.css" rel="stylesheet">
    <!-- Theme Color Meta -->
    <meta name="theme-color" content="#ffffff">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary fixed-top">
        <div class="container">
            <a class="navbar-brand fw-bold" href="index.php">
                <i class="bi bi-book-half me-2"></i>PeerNotes
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="index.php">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="search.php">Search</a>
                    </li>
                    <?php if (isset($_SESSION['user_id'])): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="upload.php">Upload</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="profile.php">Profile</a>
                    </li>
                    <?php if (isAdmin($_SESSION['user_id'])): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="admin.php">Admin</a>
                    </li>
                    <?php endif; ?>
                    <?php endif; ?>
                </ul>
                
                <ul class="navbar-nav">
                    <?php if (isset($_SESSION['user_id'])): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-person-circle me-1"></i><?php echo htmlspecialchars($_SESSION['name']); ?>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="profile.php"><i class="bi bi-person me-2"></i>Profile</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
                        </ul>
                    </li>
                    <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link" href="login.php">Login</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link btn btn-outline-light ms-2 px-3" href="register.php">Register</a>
                    </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section">
        <!-- Animated Background -->
        <div class="hero-background">
            <div class="floating-shapes">
                <div class="shape shape-1"></div>
                <div class="shape shape-2"></div>
                <div class="shape shape-3"></div>
                <div class="shape shape-4"></div>
                <div class="shape shape-5"></div>
                <div class="shape shape-6"></div>
            </div>
            <div class="gradient-overlay"></div>
        </div>
        
        <div class="container">
            <div class="row min-vh-100 align-items-center">
                <div class="col-lg-10 mx-auto">
                    <div class="hero-content text-center">
                        <!-- Main Title -->
                        <div class="hero-title-container" data-aos="fade-up" data-aos-duration="1000">
                            <h1 class="hero-title mb-3">
                                <span class="text-gradient-primary">PeerNotes</span>
                            </h1>
                            <div class="title-decoration">
                                <div class="decoration-line"></div>
                                <i class="bi bi-book-half decoration-icon"></i>
                                <div class="decoration-line"></div>
                            </div>
                        </div>
                        
                        <!-- Subtitle -->
                        <p class="hero-subtitle mb-4" data-aos="fade-up" data-aos-delay="200" data-aos-duration="800">
                            Empowering Sri Lankan Students Through Shared Knowledge
                        </p>
                        
                        <!-- Description -->
                        <p class="hero-description mb-5" data-aos="fade-up" data-aos-delay="400" data-aos-duration="800">
                            Join thousands of students sharing notes, papers, presentations, and study materials. 
                            Discover resources from your university and beyond.
                        </p>
                        
                        <!-- Search Bar -->
                        <div class="search-container mb-5" data-aos="fade-up" data-aos-delay="600" data-aos-duration="800">
                            <form action="search.php" method="GET" class="search-form">
                                <div class="search-wrapper">
                                    <div class="search-icon">
                                        <i class="bi bi-search"></i>
                                    </div>
                                    <input type="text" class="search-input" name="query" 
                                           placeholder="Search for notes, papers, presentations..." 
                                           autocomplete="off">
                                    <button class="search-btn" type="submit">
                                        <span>Search</span>
                                        <i class="bi bi-arrow-right"></i>
                                    </button>
                                </div>
                            </form>
                        </div>
                        
                        <!-- CTA Buttons -->
                        <div class="cta-buttons" data-aos="fade-up" data-aos-delay="800" data-aos-duration="800">
                            <?php if (!isset($_SESSION['user_id'])): ?>
                            <a href="register.php" class="btn btn-primary btn-lg me-3 modern-btn">
                                <i class="bi bi-person-plus me-2"></i>
                                <span>Get Started</span>
                                <div class="btn-shine"></div>
                            </a>
                            <a href="login.php" class="btn btn-outline-light btn-lg modern-btn">
                                <i class="bi bi-box-arrow-in-right me-2"></i>
                                <span>Sign In</span>
                            </a>
                            <?php else: ?>
                            <a href="upload.php" class="btn btn-primary btn-lg me-3 modern-btn">
                                <i class="bi bi-cloud-upload me-2"></i>
                                <span>Upload Resource</span>
                                <div class="btn-shine"></div>
                            </a>
                            <a href="search.php" class="btn btn-outline-light btn-lg modern-btn">
                                <i class="bi bi-search me-2"></i>
                                <span>Browse Resources</span>
                            </a>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Stats -->
                        <div class="hero-stats" data-aos="fade-up" data-aos-delay="1000" data-aos-duration="800">
                            <div class="stat-item">
                                <div class="stat-number" data-count="1000">0</div>
                                <div class="stat-label">Resources</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-number" data-count="500">0</div>
                                <div class="stat-label">Students</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-number" data-count="50">0</div>
                                <div class="stat-label">Universities</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Scroll Indicator -->
        <div class="scroll-indicator" data-aos="fade-up" data-aos-delay="1200">
            <div class="scroll-text">Scroll to explore</div>
            <div class="scroll-arrow">
                <i class="bi bi-chevron-down"></i>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="features-section py-5">
        <div class="container">
            <div class="row">
                <div class="col-12 text-center mb-5">
                    <h2 class="section-title" data-aos="fade-up">Why Choose PeerNotes?</h2>
                    <p class="section-subtitle" data-aos="fade-up" data-aos-delay="200">
                        The ultimate platform for academic resource sharing
                    </p>
                </div>
            </div>
            
            <div class="row g-4">
                <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-delay="100">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="bi bi-share"></i>
                        </div>
                        <h4 class="feature-title">Easy Sharing</h4>
                        <p class="feature-description">
                            Upload and share your academic resources with fellow students in just a few clicks.
                        </p>
                    </div>
                </div>
                
                <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-delay="200">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="bi bi-search"></i>
                        </div>
                        <h4 class="feature-title">Smart Search</h4>
                        <p class="feature-description">
                            Find exactly what you need with our advanced search and filtering system.
                        </p>
                    </div>
                </div>
                
                <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-delay="300">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="bi bi-star"></i>
                        </div>
                        <h4 class="feature-title">Quality Rated</h4>
                        <p class="feature-description">
                            Rate and review resources to help others find the best academic materials.
                        </p>
                    </div>
                </div>
                
                <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-delay="400">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="bi bi-shield-check"></i>
                        </div>
                        <h4 class="feature-title">Verified Content</h4>
                        <p class="feature-description">
                            All resources are verified and moderated to ensure quality and accuracy.
                        </p>
                    </div>
                </div>
                
                <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-delay="500">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="bi bi-people"></i>
                        </div>
                        <h4 class="feature-title">Community Driven</h4>
                        <p class="feature-description">
                            Join a vibrant community of students helping each other succeed.
                        </p>
                    </div>
                </div>
                
                <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-delay="600">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="bi bi-mobile"></i>
                        </div>
                        <h4 class="feature-title">Mobile Friendly</h4>
                        <p class="feature-description">
                            Access your resources anywhere, anytime with our responsive design.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Featured Resources -->
    <section class="featured-section py-5">
        <div class="container">
            <div class="row">
                <div class="col-12 text-center mb-5">
                    <h2 class="section-title" data-aos="fade-up">Featured Resources</h2>
                    <p class="section-subtitle" data-aos="fade-up" data-aos-delay="200">
                        Discover the most popular academic materials
                    </p>
                </div>
            </div>
            
            <div class="row g-4" id="featured-resources">
                <!-- Featured resources will be loaded here via JavaScript -->
                <div class="col-12 text-center">
                    <div class="loading-skeleton-container">
                        <div class="skeleton-card"></div>
                        <div class="skeleton-card"></div>
                        <div class="skeleton-card"></div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Stats Section -->
    <section class="stats-section py-5">
        <div class="container">
            <div class="row g-4">
                <div class="col-lg-3 col-md-6" data-aos="fade-up" data-aos-delay="100">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="bi bi-file-earmark-text"></i>
                        </div>
                        <div class="stat-number" data-count="1250">0</div>
                        <div class="stat-label">Resources Shared</div>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6" data-aos="fade-up" data-aos-delay="200">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="bi bi-people"></i>
                        </div>
                        <div class="stat-number" data-count="850">0</div>
                        <div class="stat-label">Active Students</div>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6" data-aos="fade-up" data-aos-delay="300">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="bi bi-building"></i>
                        </div>
                        <div class="stat-number" data-count="25">0</div>
                        <div class="stat-label">Universities</div>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6" data-aos="fade-up" data-aos-delay="400">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="bi bi-download"></i>
                        </div>
                        <div class="stat-number" data-count="5000">0</div>
                        <div class="stat-label">Downloads</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer bg-dark text-light py-4">
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
    <!-- AOS Animation Library -->
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <!-- Custom JS -->
    <script src="assets/js/theme.js"></script>
    <script src="assets/js/main.js"></script>
    <script src="assets/js/home.js"></script>
    
    <script>
        // Initialize AOS animations
        AOS.init({
            duration: 800,
            easing: 'ease-in-out',
            once: true,
            offset: 100
        });
        
        // Initialize counter animations
        document.addEventListener('DOMContentLoaded', function() {
            const counters = document.querySelectorAll('.stat-number[data-count]');
            
            const animateCounter = (counter) => {
                const target = parseInt(counter.getAttribute('data-count'));
                const duration = 2000;
                const increment = target / (duration / 16);
                let current = 0;
                
                const timer = setInterval(() => {
                    current += increment;
                    if (current >= target) {
                        current = target;
                        clearInterval(timer);
                    }
                    counter.textContent = Math.floor(current).toLocaleString();
                }, 16);
            };
            
            // Intersection Observer for counter animation
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        animateCounter(entry.target);
                        observer.unobserve(entry.target);
                    }
                });
            });
            
            counters.forEach(counter => {
                observer.observe(counter);
            });
        });
        
        // Smooth scroll for scroll indicator
        document.querySelector('.scroll-indicator').addEventListener('click', function() {
            document.querySelector('.features-section').scrollIntoView({
                behavior: 'smooth'
            });
        });
    </script>
</body>
</html>

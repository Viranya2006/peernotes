<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Require login
requireLogin();

$userId = $_SESSION['user_id'];

try {
    // Get user's download history and preferences
    $stmt = $pdo->prepare("
        SELECT 
            r.subject,
            r.course,
            r.academic_year,
            COUNT(*) as download_count
        FROM activity_log al
        LEFT JOIN resources r ON al.resource_id = r.id
        WHERE al.user_id = ? AND al.action = 'download' AND r.id IS NOT NULL
        GROUP BY r.subject, r.course, r.academic_year
        ORDER BY download_count DESC
        LIMIT 10
    ");
    $stmt->execute([$userId]);
    $userPreferences = $stmt->fetchAll();
    
    // Get user's uploaded resources to understand their field
    $stmt = $pdo->prepare("
        SELECT DISTINCT subject, course, academic_year
        FROM resources 
        WHERE user_id = ?
        ORDER BY upload_date DESC
        LIMIT 5
    ");
    $stmt->execute([$userId]);
    $userUploads = $stmt->fetchAll();
    
    // Build recommendation query based on user preferences
    $recommendations = [];
    
    if (!empty($userPreferences)) {
        // Recommend based on download history
        $preferredSubjects = array_column($userPreferences, 'subject');
        $preferredCourses = array_column($userPreferences, 'course');
        $preferredYears = array_column($userPreferences, 'academic_year');
        
        $subjectPlaceholders = str_repeat('?,', count($preferredSubjects) - 1) . '?';
        $coursePlaceholders = str_repeat('?,', count($preferredCourses) - 1) . '?';
        $yearPlaceholders = str_repeat('?,', count($preferredYears) - 1) . '?';
        
        $stmt = $pdo->prepare("
            SELECT 
                r.*,
                u.name as uploader_name,
                u.university as uploader_university,
                COALESCE(AVG(reviews.rating), 0) as average_rating,
                COUNT(reviews.id) as review_count,
                COUNT(al.id) as download_count
            FROM resources r
            LEFT JOIN users u ON r.user_id = u.id
            LEFT JOIN reviews ON r.id = reviews.resource_id
            LEFT JOIN activity_log al ON r.id = al.resource_id AND al.action = 'download'
            WHERE r.is_flagged = 0 
            AND r.user_id != ?
            AND (r.subject IN ($subjectPlaceholders) 
                 OR r.course IN ($coursePlaceholders) 
                 OR r.academic_year IN ($yearPlaceholders))
            GROUP BY r.id
            ORDER BY average_rating DESC, review_count DESC, download_count DESC
            LIMIT 12
        ");
        
        $params = array_merge([$userId], $preferredSubjects, $preferredCourses, $preferredYears);
        $stmt->execute($params);
        $recommendations = $stmt->fetchAll();
    }
    
    // If no recommendations based on history, get popular resources
    if (empty($recommendations)) {
        $stmt = $pdo->prepare("
            SELECT 
                r.*,
                u.name as uploader_name,
                u.university as uploader_university,
                COALESCE(AVG(reviews.rating), 0) as average_rating,
                COUNT(reviews.id) as review_count
            FROM resources r
            LEFT JOIN users u ON r.user_id = u.id
            LEFT JOIN reviews ON r.id = reviews.resource_id
            WHERE r.is_flagged = 0 
            AND r.user_id != ?
            GROUP BY r.id
            ORDER BY r.download_count DESC, average_rating DESC
            LIMIT 12
        ");
        $stmt->execute([$userId]);
        $recommendations = $stmt->fetchAll();
    }
    
    // Get trending resources (most downloaded in last 7 days)
    $stmt = $pdo->prepare("
        SELECT 
            r.*,
            u.name as uploader_name,
            u.university as uploader_university,
            COALESCE(AVG(reviews.rating), 0) as average_rating,
            COUNT(reviews.id) as review_count,
            COUNT(al.id) as recent_downloads
        FROM resources r
        LEFT JOIN users u ON r.user_id = u.id
        LEFT JOIN reviews ON r.id = reviews.resource_id
        LEFT JOIN activity_log al ON r.id = al.resource_id 
            AND al.action = 'download' 
            AND al.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        WHERE r.is_flagged = 0 
        AND r.user_id != ?
        GROUP BY r.id
        HAVING recent_downloads > 0
        ORDER BY recent_downloads DESC, average_rating DESC
        LIMIT 8
    ");
    $stmt->execute([$userId]);
    $trendingResources = $stmt->fetchAll();
    
    // Get resources from same university
    $stmt = $pdo->prepare("
        SELECT university FROM users WHERE id = ?
    ");
    $stmt->execute([$userId]);
    $userUniversity = $stmt->fetchColumn();
    
    $universityResources = [];
    if ($userUniversity) {
        $stmt = $pdo->prepare("
            SELECT 
                r.*,
                u.name as uploader_name,
                u.university as uploader_university,
                COALESCE(AVG(reviews.rating), 0) as average_rating,
                COUNT(reviews.id) as review_count
            FROM resources r
            LEFT JOIN users u ON r.user_id = u.id
            LEFT JOIN reviews ON r.id = reviews.resource_id
            WHERE r.is_flagged = 0 
            AND r.user_id != ?
            AND u.university = ?
            GROUP BY r.id
            ORDER BY average_rating DESC, review_count DESC
            LIMIT 8
        ");
        $stmt->execute([$userId, $userUniversity]);
        $universityResources = $stmt->fetchAll();
    }
    
} catch (PDOException $e) {
    error_log("Recommendations error: " . $e->getMessage());
    $recommendations = [];
    $trendingResources = [];
    $universityResources = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recommendations - PeerNotes</title>
    <meta name="description" content="Personalized resource recommendations based on your interests">
    
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
                        <a class="nav-link" href="index.php">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="search.php">Search</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="upload.php">Upload</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="profile.php">Profile</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="study-groups.php">Study Groups</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="recommendations.php">Recommendations</a>
                    </li>
                </ul>
                
                <ul class="navbar-nav">
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
                </ul>
            </div>
        </div>
    </nav>

    <!-- Recommendations Section -->
    <section class="py-5" style="margin-top: 80px;">
        <div class="container">
            <div class="row mb-4">
                <div class="col-12">
                    <div class="text-center mb-4">
                        <h1 class="text-gradient">Personalized Recommendations</h1>
                        <p class="lead">Discover resources tailored to your interests and academic journey</p>
                    </div>
                </div>
            </div>
            
            <!-- For You Section -->
            <div class="row mb-5">
                <div class="col-12">
                    <h3 class="mb-4">
                        <i class="bi bi-heart me-2"></i>Recommended for You
                        <small class="text-muted">Based on your download history and preferences</small>
                    </h3>
                    
                    <?php if (empty($recommendations)): ?>
                        <div class="empty-state text-center py-5">
                            <i class="bi bi-lightbulb display-1 text-muted mb-3"></i>
                            <h5>No recommendations yet</h5>
                            <p class="text-muted">Start downloading resources to get personalized recommendations!</p>
                            <a href="search.php" class="btn btn-primary">
                                <i class="bi bi-search me-2"></i>Explore Resources
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="row">
                            <?php foreach ($recommendations as $resource): ?>
                                <div class="col-lg-4 col-md-6 mb-4">
                                    <div class="resource-card h-100">
                                        <div class="card-icon">
                                            <i class="<?php echo getFileIcon($resource['file_type']); ?>"></i>
                                        </div>
                                        <h5 class="card-title"><?php echo htmlspecialchars($resource['title']); ?></h5>
                                        <p class="card-subtitle"><?php echo htmlspecialchars($resource['subject']); ?> • <?php echo htmlspecialchars($resource['course']); ?></p>
                                        <p class="card-description"><?php echo htmlspecialchars(substr($resource['description'], 0, 100)) . (strlen($resource['description']) > 100 ? '...' : ''); ?></p>
                                        
                                        <div class="card-meta">
                                            <div class="rating-stars">
                                                <?php echo generateStars($resource['average_rating']); ?>
                                                <span class="ms-1 text-muted">(<?php echo $resource['review_count']; ?>)</span>
                                            </div>
                                            <small class="text-muted"><?php echo timeAgo($resource['upload_date']); ?></small>
                                        </div>
                                        
                                        <div class="card-actions mt-3">
                                            <a href="resource.php?id=<?php echo $resource['id']; ?>" class="btn btn-primary btn-sm">
                                                <i class="bi bi-eye me-1"></i>View
                                            </a>
                                            <a href="preview.php?id=<?php echo $resource['id']; ?>" class="btn btn-outline-secondary btn-sm ms-2" target="_blank">
                                                <i class="bi bi-eye me-1"></i>Preview
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Trending Section -->
            <div class="row mb-5">
                <div class="col-12">
                    <h3 class="mb-4">
                        <i class="bi bi-fire me-2"></i>Trending This Week
                        <small class="text-muted">Most downloaded resources in the last 7 days</small>
                    </h3>
                    
                    <?php if (empty($trendingResources)): ?>
                        <div class="empty-state text-center py-5">
                            <i class="bi bi-graph-up display-1 text-muted mb-3"></i>
                            <h5>No trending resources</h5>
                            <p class="text-muted">Check back later for trending content!</p>
                        </div>
                    <?php else: ?>
                        <div class="row">
                            <?php foreach ($trendingResources as $resource): ?>
                                <div class="col-lg-3 col-md-6 mb-4">
                                    <div class="resource-card h-100">
                                        <div class="card-icon">
                                            <i class="<?php echo getFileIcon($resource['file_type']); ?>"></i>
                                        </div>
                                        <h6 class="card-title"><?php echo htmlspecialchars($resource['title']); ?></h6>
                                        <p class="card-subtitle"><?php echo htmlspecialchars($resource['subject']); ?></p>
                                        
                                        <div class="card-meta">
                                            <div class="rating-stars">
                                                <?php echo generateStars($resource['average_rating']); ?>
                                            </div>
                                            <small class="text-muted"><?php echo $resource['recent_downloads']; ?> downloads this week</small>
                                        </div>
                                        
                                        <div class="card-actions mt-3">
                                            <a href="resource.php?id=<?php echo $resource['id']; ?>" class="btn btn-primary btn-sm">
                                                <i class="bi bi-eye me-1"></i>View
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- University Section -->
            <?php if (!empty($universityResources)): ?>
            <div class="row">
                <div class="col-12">
                    <h3 class="mb-4">
                        <i class="bi bi-building me-2"></i>From Your University
                        <small class="text-muted">Resources shared by students from <?php echo htmlspecialchars($userUniversity); ?></small>
                    </h3>
                    
                    <div class="row">
                        <?php foreach ($universityResources as $resource): ?>
                            <div class="col-lg-3 col-md-6 mb-4">
                                <div class="resource-card h-100">
                                    <div class="card-icon">
                                        <i class="<?php echo getFileIcon($resource['file_type']); ?>"></i>
                                    </div>
                                    <h6 class="card-title"><?php echo htmlspecialchars($resource['title']); ?></h6>
                                    <p class="card-subtitle"><?php echo htmlspecialchars($resource['subject']); ?></p>
                                    
                                    <div class="card-meta">
                                        <div class="rating-stars">
                                            <?php echo generateStars($resource['average_rating']); ?>
                                        </div>
                                        <small class="text-muted"><?php echo htmlspecialchars($resource['uploader_name']); ?></small>
                                    </div>
                                    
                                    <div class="card-actions mt-3">
                                        <a href="resource.php?id=<?php echo $resource['id']; ?>" class="btn btn-primary btn-sm">
                                            <i class="bi bi-eye me-1"></i>View
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
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
    <!-- Custom JS -->
    <script src="assets/js/main.js"></script>
    <script src="assets/js/theme.js"></script>
    
    <style>
        .empty-state {
            background: var(--bg-secondary);
            border-radius: 20px;
            padding: 3rem;
        }
        
        .resource-card {
            transition: all 0.3s ease;
        }
        
        .resource-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
        }
    </style>
</body>
</html>

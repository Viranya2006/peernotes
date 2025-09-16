<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Require login
requireLogin();

$userId = $_SESSION['user_id'];

try {
    // Get user statistics
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(DISTINCT r.id) as total_uploads,
            SUM(r.download_count) as total_downloads,
            SUM(r.view_count) as total_views,
            SUM(r.favorites_count) as total_favorites,
            COUNT(DISTINCT reviews.id) as total_reviews,
            COALESCE(AVG(reviews.rating), 0) as average_rating_given
        FROM resources r
        LEFT JOIN reviews ON r.user_id = reviews.user_id
        WHERE r.user_id = ?
    ");
    $stmt->execute([$userId]);
    $userStats = $stmt->fetch();
    
    // Get recent activity
    $stmt = $pdo->prepare("
        SELECT 
            action,
            resource_id,
            created_at,
            r.title as resource_title
        FROM activity_log al
        LEFT JOIN resources r ON al.resource_id = r.id
        WHERE al.user_id = ?
        ORDER BY al.created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$userId]);
    $recentActivity = $stmt->fetchAll();
    
    // Get download trends (last 30 days)
    $stmt = $pdo->prepare("
        SELECT 
            DATE(al.created_at) as date,
            COUNT(*) as downloads
        FROM activity_log al
        WHERE al.user_id = ? 
        AND al.action = 'download'
        AND al.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY DATE(al.created_at)
        ORDER BY date DESC
        LIMIT 30
    ");
    $stmt->execute([$userId]);
    $downloadTrends = $stmt->fetchAll();
    
    // Get top performing resources
    $stmt = $pdo->prepare("
        SELECT 
            r.*,
            COALESCE(AVG(reviews.rating), 0) as average_rating,
            COUNT(reviews.id) as review_count
        FROM resources r
        LEFT JOIN reviews ON r.id = reviews.resource_id
        WHERE r.user_id = ?
        GROUP BY r.id
        ORDER BY r.download_count DESC, average_rating DESC
        LIMIT 5
    ");
    $stmt->execute([$userId]);
    $topResources = $stmt->fetchAll();
    
    // Get study group activity
    $stmt = $pdo->prepare("
        SELECT 
            sg.name as group_name,
            sgm.role,
            sg.subject
        FROM study_groups sg
        LEFT JOIN study_group_members sgm ON sg.id = sgm.group_id
        WHERE sgm.user_id = ?
        ORDER BY sgm.joined_at DESC
        LIMIT 5
    ");
    $stmt->execute([$userId]);
    $studyGroups = $stmt->fetchAll();
    
    // Get platform statistics
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(DISTINCT r.id) as total_resources,
            COUNT(DISTINCT u.id) as total_users,
            COUNT(DISTINCT reviews.id) as total_reviews,
            COUNT(DISTINCT sg.id) as total_study_groups
        FROM resources r
        LEFT JOIN users u ON 1=1
        LEFT JOIN reviews ON 1=1
        LEFT JOIN study_groups sg ON 1=1
        WHERE r.is_flagged = 0
    ");
    $stmt->execute();
    $platformStats = $stmt->fetch();
    
} catch (PDOException $e) {
    error_log("Dashboard error: " . $e->getMessage());
    $userStats = [];
    $recentActivity = [];
    $downloadTrends = [];
    $topResources = [];
    $studyGroups = [];
    $platformStats = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - PeerNotes</title>
    <meta name="description" content="Your personal dashboard with analytics and insights">
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
                        <a class="nav-link" href="recommendations.php">Recommendations</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="dashboard.php">Dashboard</a>
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

    <!-- Dashboard Section -->
    <section class="py-5" style="margin-top: 80px;">
        <div class="container">
            <div class="row mb-4">
                <div class="col-12">
                    <div class="text-center mb-4">
                        <h1 class="text-gradient">Dashboard</h1>
                        <p class="lead">Welcome back, <?php echo htmlspecialchars($_SESSION['name']); ?>! Here's your activity overview.</p>
                    </div>
                </div>
            </div>
            
            <!-- Stats Cards -->
            <div class="row mb-5">
                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="stats-card">
                        <div class="stats-icon">
                            <i class="bi bi-cloud-upload"></i>
                        </div>
                        <div class="stats-content">
                            <h3><?php echo number_format($userStats['total_uploads'] ?? 0); ?></h3>
                            <p>Resources Uploaded</p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="stats-card">
                        <div class="stats-icon">
                            <i class="bi bi-download"></i>
                        </div>
                        <div class="stats-content">
                            <h3><?php echo number_format($userStats['total_downloads'] ?? 0); ?></h3>
                            <p>Total Downloads</p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="stats-card">
                        <div class="stats-icon">
                            <i class="bi bi-eye"></i>
                        </div>
                        <div class="stats-content">
                            <h3><?php echo number_format($userStats['total_views'] ?? 0); ?></h3>
                            <p>Total Views</p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="stats-card">
                        <div class="stats-icon">
                            <i class="bi bi-heart"></i>
                        </div>
                        <div class="stats-content">
                            <h3><?php echo number_format($userStats['total_favorites'] ?? 0); ?></h3>
                            <p>Total Favorites</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <!-- Download Trends Chart -->
                <div class="col-lg-8 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="bi bi-graph-up me-2"></i>Download Trends (Last 30 Days)
                            </h5>
                        </div>
                        <div class="card-body">
                            <canvas id="downloadChart" height="100"></canvas>
                        </div>
                    </div>
                </div>
                
                <!-- Recent Activity -->
                <div class="col-lg-4 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="bi bi-activity me-2"></i>Recent Activity
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($recentActivity)): ?>
                                <div class="text-center py-3">
                                    <i class="bi bi-clock-history text-muted mb-2"></i>
                                    <p class="text-muted mb-0">No recent activity</p>
                                </div>
                            <?php else: ?>
                                <div class="activity-timeline">
                                    <?php foreach ($recentActivity as $activity): ?>
                                        <div class="activity-item">
                                            <div class="activity-icon">
                                                <?php
                                                switch($activity['action']) {
                                                    case 'download':
                                                        echo '<i class="bi bi-download text-primary"></i>';
                                                        break;
                                                    case 'upload':
                                                        echo '<i class="bi bi-cloud-upload text-success"></i>';
                                                        break;
                                                    case 'review':
                                                        echo '<i class="bi bi-star text-warning"></i>';
                                                        break;
                                                    default:
                                                        echo '<i class="bi bi-circle text-secondary"></i>';
                                                }
                                                ?>
                                            </div>
                                            <div class="activity-content">
                                                <p class="activity-text">
                                                    <?php
                                                    switch($activity['action']) {
                                                        case 'download':
                                                            echo 'Downloaded ' . ($activity['resource_title'] ? htmlspecialchars($activity['resource_title']) : 'a resource');
                                                            break;
                                                        case 'upload':
                                                            echo 'Uploaded ' . ($activity['resource_title'] ? htmlspecialchars($activity['resource_title']) : 'a resource');
                                                            break;
                                                        case 'review':
                                                            echo 'Reviewed ' . ($activity['resource_title'] ? htmlspecialchars($activity['resource_title']) : 'a resource');
                                                            break;
                                                        default:
                                                            echo ucfirst($activity['action']);
                                                    }
                                                    ?>
                                                </p>
                                                <small class="text-muted"><?php echo timeAgo($activity['created_at']); ?></small>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <!-- Top Resources -->
                <div class="col-lg-6 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="bi bi-trophy me-2"></i>Top Performing Resources
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($topResources)): ?>
                                <div class="text-center py-3">
                                    <i class="bi bi-file-earmark text-muted mb-2"></i>
                                    <p class="text-muted mb-0">No resources uploaded yet</p>
                                    <a href="upload.php" class="btn btn-primary btn-sm mt-2">Upload Your First Resource</a>
                                </div>
                            <?php else: ?>
                                <?php foreach ($topResources as $resource): ?>
                                    <div class="resource-item mb-3">
                                        <div class="d-flex align-items-center">
                                            <div class="resource-icon me-3">
                                                <i class="<?php echo getFileIcon($resource['file_type']); ?>"></i>
                                            </div>
                                            <div class="flex-grow-1">
                                                <h6 class="mb-1"><?php echo htmlspecialchars($resource['title']); ?></h6>
                                                <p class="text-muted mb-1"><?php echo htmlspecialchars($resource['subject']); ?></p>
                                                <div class="d-flex align-items-center">
                                                    <span class="badge bg-primary me-2"><?php echo $resource['download_count']; ?> downloads</span>
                                                    <div class="rating-stars">
                                                        <?php echo generateStars($resource['average_rating']); ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Study Groups -->
                <div class="col-lg-6 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="bi bi-people me-2"></i>My Study Groups
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($studyGroups)): ?>
                                <div class="text-center py-3">
                                    <i class="bi bi-people text-muted mb-2"></i>
                                    <p class="text-muted mb-0">Not in any study groups yet</p>
                                    <a href="study-groups.php" class="btn btn-primary btn-sm mt-2">Join Study Groups</a>
                                </div>
                            <?php else: ?>
                                <?php foreach ($studyGroups as $group): ?>
                                    <div class="study-group-item mb-3">
                                        <div class="d-flex align-items-center">
                                            <div class="group-icon me-3">
                                                <i class="bi bi-people-fill text-primary"></i>
                                            </div>
                                            <div class="flex-grow-1">
                                                <h6 class="mb-1"><?php echo htmlspecialchars($group['group_name']); ?></h6>
                                                <p class="text-muted mb-1"><?php echo htmlspecialchars($group['subject']); ?></p>
                                                <span class="badge bg-<?php echo $group['role'] === 'admin' ? 'danger' : 'primary'; ?>">
                                                    <?php echo ucfirst($group['role']); ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Platform Stats -->
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="bi bi-globe me-2"></i>Platform Statistics
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row text-center">
                                <div class="col-md-3">
                                    <h3 class="text-primary"><?php echo number_format($platformStats['total_resources'] ?? 0); ?></h3>
                                    <p class="text-muted">Total Resources</p>
                                </div>
                                <div class="col-md-3">
                                    <h3 class="text-success"><?php echo number_format($platformStats['total_users'] ?? 0); ?></h3>
                                    <p class="text-muted">Active Users</p>
                                </div>
                                <div class="col-md-3">
                                    <h3 class="text-warning"><?php echo number_format($platformStats['total_reviews'] ?? 0); ?></h3>
                                    <p class="text-muted">Reviews</p>
                                </div>
                                <div class="col-md-3">
                                    <h3 class="text-info"><?php echo number_format($platformStats['total_study_groups'] ?? 0); ?></h3>
                                    <p class="text-muted">Study Groups</p>
                                </div>
                            </div>
                        </div>
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
    <!-- Custom JS -->
    <script src="assets/js/main.js"></script>
    <script src="assets/js/theme.js"></script>
    <script>
        // Download trends chart
        const downloadData = <?php echo json_encode($downloadTrends); ?>;
        const labels = downloadData.map(item => new Date(item.date).toLocaleDateString()).reverse();
        const data = downloadData.map(item => item.downloads).reverse();
        
        const ctx = document.getElementById('downloadChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Downloads',
                    data: data,
                    borderColor: '#4f46e5',
                    backgroundColor: 'rgba(79, 70, 229, 0.1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0, 0, 0, 0.1)'
                        }
                    },
                    x: {
                        grid: {
                            color: 'rgba(0, 0, 0, 0.1)'
                        }
                    }
                }
            }
        });
    </script>
    
    <style>
        .stats-card {
            background: var(--bg-primary);
            border-radius: 15px;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border-color);
            padding: 1.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            transition: all 0.3s ease;
        }
        
        .stats-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }
        
        .stats-icon {
            font-size: 2.5rem;
            color: var(--primary-color);
        }
        
        .stats-content h3 {
            font-size: 2rem;
            font-weight: 700;
            color: var(--text-primary);
            margin: 0;
        }
        
        .stats-content p {
            color: var(--text-secondary);
            margin: 0;
        }
        
        .activity-timeline {
            position: relative;
            padding-left: 2rem;
        }
        
        .activity-timeline::before {
            content: '';
            position: absolute;
            left: 1rem;
            top: 0;
            bottom: 0;
            width: 2px;
            background: var(--border-color);
        }
        
        .activity-item {
            position: relative;
            margin-bottom: 1.5rem;
            padding-left: 2rem;
        }
        
        .activity-item::before {
            content: '';
            position: absolute;
            left: -0.5rem;
            top: 0.5rem;
            width: 1rem;
            height: 1rem;
            background: var(--bg-primary);
            border: 2px solid var(--border-color);
            border-radius: 50%;
        }
        
        .activity-icon {
            position: absolute;
            left: -0.75rem;
            top: 0.25rem;
            font-size: 1.5rem;
            background: var(--bg-primary);
            padding: 0.25rem;
            border-radius: 50%;
        }
        
        .activity-content {
            background: var(--bg-secondary);
            border-radius: 10px;
            padding: 1rem;
        }
        
        .activity-text {
            color: var(--text-primary);
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
        }
        
        .resource-item {
            padding: 1rem;
            background: var(--bg-secondary);
            border-radius: 10px;
            transition: all 0.3s ease;
        }
        
        .resource-item:hover {
            background: var(--bg-tertiary);
        }
        
        .resource-icon {
            font-size: 1.5rem;
            color: var(--primary-color);
        }
        
        .study-group-item {
            padding: 1rem;
            background: var(--bg-secondary);
            border-radius: 10px;
            transition: all 0.3s ease;
        }
        
        .study-group-item:hover {
            background: var(--bg-tertiary);
        }
        
        .group-icon {
            font-size: 1.5rem;
        }
    </style>
</body>
</html>

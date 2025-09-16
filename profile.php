<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Require login
requireLogin();

$userId = $_SESSION['user_id'];
$error = '';
$success = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_profile') {
        $name = sanitizeInput($_POST['name'] ?? '');
        $university = sanitizeInput($_POST['university'] ?? '');
        $bio = sanitizeInput($_POST['bio'] ?? '');
        
        if (empty($name)) {
            $error = 'Name is required.';
        } else {
            try {
                $stmt = $pdo->prepare("UPDATE users SET name = ?, university = ?, bio = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$name, $university, $bio, $userId]);
                
                $_SESSION['name'] = $name;
                $success = 'Profile updated successfully!';
                
            } catch (PDOException $e) {
                $error = 'Failed to update profile. Please try again.';
                error_log("Profile update error: " . $e->getMessage());
            }
        }
    }
    
    elseif ($action === 'change_password') {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        
        if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
            $error = 'All password fields are required.';
        } elseif ($newPassword !== $confirmPassword) {
            $error = 'New passwords do not match.';
        } elseif (!validatePassword($newPassword)) {
            $error = 'New password must be at least 8 characters long.';
        } else {
            try {
                // Verify current password
                $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
                $stmt->execute([$userId]);
                $user = $stmt->fetch();
                
                if (!password_verify($currentPassword, $user['password'])) {
                    $error = 'Current password is incorrect.';
                } else {
                    // Update password
                    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?");
                    $stmt->execute([$hashedPassword, $userId]);
                    
                    $success = 'Password changed successfully!';
                }
                
            } catch (PDOException $e) {
                $error = 'Failed to change password. Please try again.';
                error_log("Password change error: " . $e->getMessage());
            }
        }
    }
    
    elseif ($action === 'delete_resource') {
        $resourceId = (int)($_POST['resource_id'] ?? 0);
        
        if ($resourceId > 0) {
            try {
                // Verify ownership
                $stmt = $pdo->prepare("SELECT file_path FROM resources WHERE id = ? AND user_id = ?");
                $stmt->execute([$resourceId, $userId]);
                $resource = $stmt->fetch();
                
                if ($resource) {
                    // Delete file
                    if (file_exists($resource['file_path'])) {
                        unlink($resource['file_path']);
                    }
                    
                    // Delete from database
                    $stmt = $pdo->prepare("DELETE FROM resources WHERE id = ? AND user_id = ?");
                    $stmt->execute([$resourceId, $userId]);
                    
                    $success = 'Resource deleted successfully!';
                } else {
                    $error = 'Resource not found or you do not have permission to delete it.';
                }
                
            } catch (PDOException $e) {
                $error = 'Failed to delete resource. Please try again.';
                error_log("Resource deletion error: " . $e->getMessage());
            }
        }
    }
}

try {
    // Get user profile
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    
    // Get user's resources
    $stmt = $pdo->prepare("
        SELECT 
            r.*,
            COALESCE(AVG(reviews.rating), 0) as average_rating,
            COUNT(reviews.id) as review_count
        FROM resources r
        LEFT JOIN reviews ON r.id = reviews.resource_id
        WHERE r.user_id = ?
        GROUP BY r.id
        ORDER BY r.upload_date DESC
    ");
    $stmt->execute([$userId]);
    $userResources = $stmt->fetchAll();
    
    // Get user's favorites
    $favoriteResources = [];
    if ($user['favorites']) {
        $favoriteIds = json_decode($user['favorites'], true);
        if (is_array($favoriteIds) && !empty($favoriteIds)) {
            $placeholders = str_repeat('?,', count($favoriteIds) - 1) . '?';
            $stmt = $pdo->prepare("
                SELECT 
                    r.*,
                    u.name as uploader_name,
                    COALESCE(AVG(reviews.rating), 0) as average_rating,
                    COUNT(reviews.id) as review_count
                FROM resources r
                LEFT JOIN users u ON r.user_id = u.id
                LEFT JOIN reviews ON r.id = reviews.resource_id
                WHERE r.id IN ($placeholders) AND r.is_flagged = 0
                GROUP BY r.id
                ORDER BY r.upload_date DESC
            ");
            $stmt->execute($favoriteIds);
            $favoriteResources = $stmt->fetchAll();
        }
    }
    
    // Get user's activity stats
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
    $stats = $stmt->fetch();
    
    // Get user's recent activity
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
    
    // Get user achievements
    $achievements = [];
    if ($stats['total_uploads'] >= 1) $achievements[] = ['name' => 'First Upload', 'icon' => 'bi-cloud-upload', 'description' => 'Uploaded your first resource'];
    if ($stats['total_uploads'] >= 5) $achievements[] = ['name' => 'Contributor', 'icon' => 'bi-star', 'description' => 'Uploaded 5 resources'];
    if ($stats['total_uploads'] >= 10) $achievements[] = ['name' => 'Power User', 'icon' => 'bi-lightning', 'description' => 'Uploaded 10 resources'];
    if ($stats['total_downloads'] >= 100) $achievements[] = ['name' => 'Popular', 'icon' => 'bi-heart', 'description' => 'Resources downloaded 100+ times'];
    if ($stats['total_reviews'] >= 10) $achievements[] = ['name' => 'Reviewer', 'icon' => 'bi-chat-dots', 'description' => 'Left 10+ reviews'];
    
    // Get admin-specific data if user is admin
    $adminData = [];
    if (isAdmin($userId)) {
        // Get platform statistics
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(DISTINCT u.id) as total_users,
                COUNT(DISTINCT CASE WHEN u.is_suspended = 1 THEN u.id END) as suspended_users,
                COUNT(DISTINCT CASE WHEN u.is_banned = 1 THEN u.id END) as banned_users,
                COUNT(DISTINCT r.id) as total_resources,
                COUNT(DISTINCT CASE WHEN r.is_flagged = 1 THEN r.id END) as flagged_resources,
                COUNT(DISTINCT reports.id) as total_reports
            FROM users u
            LEFT JOIN resources r ON u.id = r.user_id
            LEFT JOIN reports ON r.id = reports.resource_id
        ");
        $stmt->execute();
        $adminData['platformStats'] = $stmt->fetch();
        
        // Get recent users
        $stmt = $pdo->prepare("
            SELECT 
                u.id,
                u.name,
                u.email,
                u.university,
                u.created_at,
                u.is_suspended,
                u.is_banned,
                COUNT(r.id) as resource_count
            FROM users u
            LEFT JOIN resources r ON u.id = r.user_id
            WHERE u.is_admin = 0
            GROUP BY u.id
            ORDER BY u.created_at DESC
            LIMIT 10
        ");
        $stmt->execute();
        $adminData['recentUsers'] = $stmt->fetchAll();
        
        // Get user growth data (last 30 days)
        $stmt = $pdo->prepare("
            SELECT 
                DATE(created_at) as date,
                COUNT(*) as new_users
            FROM users 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY DATE(created_at)
            ORDER BY date DESC
        ");
        $stmt->execute();
        $adminData['userGrowth'] = $stmt->fetchAll();
    }
    
} catch (PDOException $e) {
    error_log("Profile data error: " . $e->getMessage());
    $error = 'Failed to load profile data.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - PeerNotes</title>
    <meta name="description" content="Manage your PeerNotes profile and resources">
    
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
                        <a class="nav-link active" href="profile.php">Profile</a>
                    </li>
                    <?php if (isAdmin($_SESSION['user_id'])): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="admin.php">Admin</a>
                    </li>
                    <?php endif; ?>
                </ul>
                
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-person-circle me-1"></i><?php echo htmlspecialchars($_SESSION['name']); ?>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="profile.php"><i class="bi bi-person me-2"></i>Profile</a></li>
                            <?php if (isAdmin($_SESSION['user_id'])): ?>
                            <li><a class="dropdown-item" href="admin.php"><i class="bi bi-shield-check me-2"></i>Admin Panel</a></li>
                            <?php endif; ?>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Profile Section -->
    <section class="py-5" style="margin-top: 80px;">
        <div class="container">
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
            
            <div class="row">
                <!-- Profile Sidebar -->
                <div class="col-lg-4">
                    <div class="profile-sidebar">
                        <!-- Profile Card -->
                        <div class="profile-card">
                            <div class="profile-avatar">
                                <i class="bi bi-person-circle"></i>
                            </div>
                            <h3 class="profile-name"><?php echo htmlspecialchars($user['name']); ?></h3>
                            <?php if ($user['university']): ?>
                                <p class="profile-university">
                                    <i class="bi bi-building me-1"></i>
                                    <?php echo htmlspecialchars($user['university']); ?>
                                </p>
                            <?php endif; ?>
                            <p class="profile-joined">
                                <i class="bi bi-calendar me-1"></i>
                                Joined <?php echo timeAgo($user['created_at']); ?>
                            </p>
                            <?php if ($user['bio']): ?>
                                <p class="profile-bio"><?php echo nl2br(htmlspecialchars($user['bio'])); ?></p>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Stats Card -->
                        <div class="stats-card">
                            <h5>Your Stats</h5>
                            <div class="stats-grid">
                                <div class="stat-item">
                                    <i class="bi bi-cloud-upload"></i>
                                    <span class="stat-number"><?php echo number_format($stats['total_uploads'] ?? 0); ?></span>
                                    <span class="stat-label">Uploads</span>
                                </div>
                                <div class="stat-item">
                                    <i class="bi bi-download"></i>
                                    <span class="stat-number"><?php echo number_format($stats['total_downloads'] ?? 0); ?></span>
                                    <span class="stat-label">Downloads</span>
                                </div>
                                <div class="stat-item">
                                    <i class="bi bi-eye"></i>
                                    <span class="stat-number"><?php echo number_format($stats['total_views'] ?? 0); ?></span>
                                    <span class="stat-label">Views</span>
                                </div>
                                <div class="stat-item">
                                    <i class="bi bi-heart"></i>
                                    <span class="stat-number"><?php echo number_format($stats['total_favorites'] ?? 0); ?></span>
                                    <span class="stat-label">Favorites</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Admin Overview Section (only for admins) -->
                <?php if (isAdmin($userId) && !empty($adminData)): ?>
                <div class="col-12 mb-4">
                    <div class="admin-overview">
                        <div class="admin-header">
                            <h2><i class="bi bi-shield-check me-2"></i>Admin Overview</h2>
                            <p class="text-muted">Platform statistics and user management</p>
                        </div>
                        
                        <!-- Platform Stats -->
                        <div class="row mb-4">
                            <div class="col-md-3">
                                <div class="admin-stat-card">
                                    <div class="stat-icon">
                                        <i class="bi bi-people"></i>
                                    </div>
                                    <div class="stat-content">
                                        <h3><?php echo number_format($adminData['platformStats']['total_users']); ?></h3>
                                        <p>Total Users</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="admin-stat-card">
                                    <div class="stat-icon">
                                        <i class="bi bi-file-earmark"></i>
                                    </div>
                                    <div class="stat-content">
                                        <h3><?php echo number_format($adminData['platformStats']['total_resources']); ?></h3>
                                        <p>Total Resources</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="admin-stat-card flagged">
                                    <div class="stat-icon">
                                        <i class="bi bi-flag"></i>
                                    </div>
                                    <div class="stat-content">
                                        <h3><?php echo number_format($adminData['platformStats']['flagged_resources']); ?></h3>
                                        <p>Flagged Resources</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="admin-stat-card">
                                    <div class="stat-icon">
                                        <i class="bi bi-exclamation-triangle"></i>
                                    </div>
                                    <div class="stat-content">
                                        <h3><?php echo number_format($adminData['platformStats']['total_reports']); ?></h3>
                                        <p>Total Reports</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Recent Users -->
                        <div class="row">
                            <div class="col-md-8">
                                <div class="admin-section">
                                    <div class="section-header">
                                        <h4><i class="bi bi-people me-2"></i>Recent Users</h4>
                                        <a href="admin.php" class="btn btn-sm btn-outline-primary">View All</a>
                                    </div>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>User</th>
                                                    <th>University</th>
                                                    <th>Resources</th>
                                                    <th>Status</th>
                                                    <th>Joined</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($adminData['recentUsers'] as $recentUser): ?>
                                                <tr>
                                                    <td>
                                                        <div class="user-info">
                                                            <strong><?php echo htmlspecialchars($recentUser['name']); ?></strong>
                                                            <br>
                                                            <small class="text-muted"><?php echo htmlspecialchars($recentUser['email']); ?></small>
                                                        </div>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($recentUser['university'] ?? 'Not specified'); ?></td>
                                                    <td>
                                                        <span class="badge bg-primary"><?php echo $recentUser['resource_count']; ?></span>
                                                    </td>
                                                    <td>
                                                        <?php if ($recentUser['is_banned']): ?>
                                                            <span class="badge bg-danger">Banned</span>
                                                        <?php elseif ($recentUser['is_suspended']): ?>
                                                            <span class="badge bg-warning">Suspended</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-success">Active</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><?php echo timeAgo($recentUser['created_at']); ?></td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="admin-section">
                                    <div class="section-header">
                                        <h4><i class="bi bi-graph-up me-2"></i>User Growth</h4>
                                    </div>
                                    <div style="position: relative; height: 250px;">
                                        <canvas id="userGrowthChart"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Main Content -->
                <div class="col-lg-8">
                    <!-- Tab Navigation -->
                    <ul class="nav nav-tabs" id="profileTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="uploads-tab" data-bs-toggle="tab" data-bs-target="#uploads" type="button" role="tab">
                                <i class="bi bi-cloud-upload me-2"></i>My Uploads
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="favorites-tab" data-bs-toggle="tab" data-bs-target="#favorites" type="button" role="tab">
                                <i class="bi bi-heart me-2"></i>Favorites
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="achievements-tab" data-bs-toggle="tab" data-bs-target="#achievements" type="button" role="tab">
                                <i class="bi bi-trophy me-2"></i>Achievements
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="activity-tab" data-bs-toggle="tab" data-bs-target="#activity" type="button" role="tab">
                                <i class="bi bi-activity me-2"></i>Activity
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="settings-tab" data-bs-toggle="tab" data-bs-target="#settings" type="button" role="tab">
                                <i class="bi bi-gear me-2"></i>Settings
                            </button>
                        </li>
                    </ul>
                    
                    <!-- Tab Content -->
                    <div class="tab-content" id="profileTabContent">
                        <!-- My Uploads Tab -->
                        <div class="tab-pane fade show active" id="uploads" role="tabpanel">
                            <div class="uploads-section">
                                <div class="section-header">
                                    <h4>My Uploaded Resources</h4>
                                    <a href="upload.php" class="btn btn-primary">
                                        <i class="bi bi-plus me-2"></i>Upload New
                                    </a>
                                </div>
                                
                                <?php if (empty($userResources)): ?>
                                    <div class="empty-state">
                                        <i class="bi bi-cloud-upload display-1 text-muted mb-3"></i>
                                        <h5>No uploads yet</h5>
                                        <p class="text-muted">Start sharing your academic resources with fellow students!</p>
                                        <a href="upload.php" class="btn btn-primary">
                                            <i class="bi bi-cloud-upload me-2"></i>Upload Your First Resource
                                        </a>
                                    </div>
                                <?php else: ?>
                                    <div class="resources-grid">
                                        <?php foreach ($userResources as $resource): ?>
                                            <div class="resource-item">
                                                <div class="resource-icon">
                                                    <i class="<?php echo getFileIcon($resource['file_type']); ?>"></i>
                                                </div>
                                                <div class="resource-content">
                                                    <h6 class="resource-title"><?php echo htmlspecialchars($resource['title']); ?></h6>
                                                    <p class="resource-meta">
                                                        <?php echo htmlspecialchars($resource['subject']); ?> • 
                                                        <?php echo htmlspecialchars($resource['course']); ?>
                                                    </p>
                                                    <div class="resource-stats">
                                                        <span class="stat">
                                                            <i class="bi bi-download"></i> <?php echo number_format($resource['download_count'] ?? 0); ?>
                                                        </span>
                                                        <span class="stat">
                                                            <i class="bi bi-eye"></i> <?php echo number_format($resource['view_count'] ?? 0); ?>
                                                        </span>
                                                        <span class="stat">
                                                            <i class="bi bi-star"></i> <?php echo number_format($resource['average_rating'] ?? 0, 1); ?>
                                                        </span>
                                                    </div>
                                                </div>
                                                <div class="resource-actions">
                                                    <a href="resource.php?id=<?php echo $resource['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                        <i class="bi bi-eye"></i>
                                                    </a>
                                                    <button class="btn btn-sm btn-outline-danger" onclick="deleteResource(<?php echo $resource['id']; ?>)">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Favorites Tab -->
                        <div class="tab-pane fade" id="favorites" role="tabpanel">
                            <div class="favorites-section">
                                <div class="section-header">
                                    <h4>My Favorite Resources</h4>
                                </div>
                                
                                <?php if (empty($favoriteResources)): ?>
                                    <div class="empty-state">
                                        <i class="bi bi-heart display-1 text-muted mb-3"></i>
                                        <h5>No favorites yet</h5>
                                        <p class="text-muted">Browse resources and add them to your favorites!</p>
                                        <a href="search.php" class="btn btn-primary">
                                            <i class="bi bi-search me-2"></i>Browse Resources
                                        </a>
                                    </div>
                                <?php else: ?>
                                    <div class="resources-grid">
                                        <?php foreach ($favoriteResources as $resource): ?>
                                            <div class="resource-item">
                                                <div class="resource-icon">
                                                    <i class="<?php echo getFileIcon($resource['file_type']); ?>"></i>
                                                </div>
                                                <div class="resource-content">
                                                    <h6 class="resource-title"><?php echo htmlspecialchars($resource['title']); ?></h6>
                                                    <p class="resource-meta">
                                                        <?php echo htmlspecialchars($resource['subject']); ?> • 
                                                        <?php echo htmlspecialchars($resource['course']); ?>
                                                    </p>
                                                    <p class="resource-uploader">
                                                        <i class="bi bi-person me-1"></i>
                                                        <?php echo htmlspecialchars($resource['uploader_name']); ?>
                                                    </p>
                                                    <div class="resource-stats">
                                                        <span class="stat">
                                                            <i class="bi bi-download"></i> <?php echo number_format($resource['download_count'] ?? 0); ?>
                                                        </span>
                                                        <span class="stat">
                                                            <i class="bi bi-star"></i> <?php echo number_format($resource['average_rating'] ?? 0, 1); ?>
                                                        </span>
                                                    </div>
                                                </div>
                                                <div class="resource-actions">
                                                    <a href="resource.php?id=<?php echo $resource['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                        <i class="bi bi-eye"></i>
                                                    </a>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Achievements Tab -->
                        <div class="tab-pane fade" id="achievements" role="tabpanel">
                            <div class="achievements-section">
                                <div class="section-header">
                                    <h4>Your Achievements</h4>
                                    <span class="badge bg-warning"><?php echo count($achievements); ?> earned</span>
                                </div>
                                
                                <?php if (empty($achievements)): ?>
                                    <div class="empty-state">
                                        <i class="bi bi-trophy display-1 text-muted mb-3"></i>
                                        <h5>No achievements yet</h5>
                                        <p class="text-muted">Start uploading resources and engaging with the community to earn achievements!</p>
                                        <a href="upload.php" class="btn btn-primary">
                                            <i class="bi bi-cloud-upload me-2"></i>Upload Your First Resource
                                        </a>
                                    </div>
                                <?php else: ?>
                                    <div class="achievements-grid">
                                        <?php foreach ($achievements as $achievement): ?>
                                            <div class="achievement-item">
                                                <div class="achievement-icon">
                                                    <i class="<?php echo $achievement['icon']; ?>"></i>
                                                </div>
                                                <div class="achievement-content">
                                                    <h6 class="achievement-name"><?php echo $achievement['name']; ?></h6>
                                                    <p class="achievement-description"><?php echo $achievement['description']; ?></p>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Activity Tab -->
                        <div class="tab-pane fade" id="activity" role="tabpanel">
                            <div class="activity-section">
                                <div class="section-header">
                                    <h4>Recent Activity</h4>
                                </div>
                                
                                <?php if (empty($recentActivity)): ?>
                                    <div class="empty-state">
                                        <i class="bi bi-activity display-1 text-muted mb-3"></i>
                                        <h5>No recent activity</h5>
                                        <p class="text-muted">Your activity will appear here as you use the platform.</p>
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
                        
                        <!-- Settings Tab -->
                        <div class="tab-pane fade" id="settings" role="tabpanel">
                            <div class="settings-section">
                                <div class="section-header">
                                    <h4>Account Settings</h4>
                                </div>
                                
                                <!-- Profile Settings -->
                                <div class="settings-card">
                                    <h5>Profile Information</h5>
                                    <form method="POST">
                                        <input type="hidden" name="action" value="update_profile">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="name" class="form-label">Full Name</label>
                                                    <input type="text" class="form-control" id="name" name="name" 
                                                           value="<?php echo htmlspecialchars($user['name']); ?>" required>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="university" class="form-label">University</label>
                                                    <input type="text" class="form-control" id="university" name="university" 
                                                           value="<?php echo htmlspecialchars($user['university'] ?? ''); ?>">
                                                </div>
                                            </div>
                                        </div>
                                        <div class="mb-3">
                                            <label for="bio" class="form-label">Bio</label>
                                            <textarea class="form-control" id="bio" name="bio" rows="3" 
                                                      placeholder="Tell us about yourself..."><?php echo htmlspecialchars($user['bio'] ?? ''); ?></textarea>
                                        </div>
                                        <div class="mb-3">
                                            <label for="theme" class="form-label">Theme Preference</label>
                                            <select class="form-select" id="theme" name="theme">
                                                <option value="light" <?php echo (!isset($_COOKIE['theme']) || $_COOKIE['theme'] === 'light') ? 'selected' : ''; ?>>Light</option>
                                                <option value="dark" <?php echo (isset($_COOKIE['theme']) && $_COOKIE['theme'] === 'dark') ? 'selected' : ''; ?>>Dark</option>
                                                <option value="auto" <?php echo (isset($_COOKIE['theme']) && $_COOKIE['theme'] === 'auto') ? 'selected' : ''; ?>>Auto (System)</option>
                                            </select>
                                        </div>
                                        <button type="submit" class="btn btn-primary">
                                            <i class="bi bi-save me-2"></i>Update Profile
                                        </button>
                                    </form>
                                </div>
                                
                                <!-- Password Settings -->
                                <div class="settings-card">
                                    <h5>Change Password</h5>
                                    <form method="POST">
                                        <input type="hidden" name="action" value="change_password">
                                        <div class="mb-3">
                                            <label for="current_password" class="form-label">Current Password</label>
                                            <input type="password" class="form-control" id="current_password" name="current_password" required>
                                        </div>
                                        <div class="mb-3">
                                            <label for="new_password" class="form-label">New Password</label>
                                            <input type="password" class="form-control" id="new_password" name="new_password" required>
                                        </div>
                                        <div class="mb-3">
                                            <label for="confirm_password" class="form-label">Confirm New Password</label>
                                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                        </div>
                                        <button type="submit" class="btn btn-warning">
                                            <i class="bi bi-key me-2"></i>Change Password
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Delete Resource</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete this resource? This action cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="confirmDelete">Delete</button>
                </div>
            </div>
        </div>
    </div>

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
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- Custom JS -->
    <script src="assets/js/main.js"></script>
    <script>
        let resourceToDelete = null;
        
        function deleteResource(resourceId) {
            resourceToDelete = resourceId;
            const modal = new bootstrap.Modal(document.getElementById('deleteModal'));
            modal.show();
        }
        
        document.getElementById('confirmDelete').addEventListener('click', function() {
            if (resourceToDelete) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_resource">
                    <input type="hidden" name="resource_id" value="${resourceToDelete}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        });
        
        // Form validation
        document.addEventListener('DOMContentLoaded', function() {
            // Profile form validation
            const profileForm = document.querySelector('form[action="update_profile"]') || 
                               document.querySelector('input[name="action"][value="update_profile"]').closest('form');
            if (profileForm) {
                const validator = new FormValidator(profileForm);
                validator.addRule('name', {
                    validator: (value) => value.length >= 2,
                    message: 'Name must be at least 2 characters long'
                });
            }
            
            // Password form validation
            const passwordForm = document.querySelector('input[name="action"][value="change_password"]').closest('form');
            if (passwordForm) {
                const validator = new FormValidator(passwordForm);
                validator.addRule('new_password', {
                    validator: (value) => value.length >= 8,
                    message: 'Password must be at least 8 characters long'
                });
                validator.addRule('confirm_password', {
                    validator: (value) => {
                        const newPassword = document.getElementById('new_password').value;
                        return value === newPassword;
                    },
                    message: 'Passwords do not match'
                });
                
                // Real-time password confirmation validation
                document.getElementById('confirm_password').addEventListener('input', function() {
                    validator.validateField('confirm_password');
                });
            }
        });
        
        // Theme Toggle Functionality
        function setTheme(theme) {
            const body = document.body;
            const html = document.documentElement;
            
            // Remove existing theme classes
            body.classList.remove('theme-light', 'theme-dark');
            html.classList.remove('theme-light', 'theme-dark');
            
            if (theme === 'dark') {
                body.classList.add('theme-dark');
                html.classList.add('theme-dark');
            } else if (theme === 'light') {
                body.classList.add('theme-light');
                html.classList.add('theme-light');
            } else {
                // Auto theme - use system preference
                if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
                    body.classList.add('theme-dark');
                    html.classList.add('theme-dark');
                } else {
                    body.classList.add('theme-light');
                    html.classList.add('theme-light');
                }
            }
            
            // Save theme preference
            document.cookie = `theme=${theme}; path=/; max-age=31536000`; // 1 year
        }
        
        // Initialize theme on page load
        document.addEventListener('DOMContentLoaded', function() {
            const themeSelect = document.getElementById('theme');
            if (themeSelect) {
                // Set initial theme
                const savedTheme = getCookie('theme') || 'light';
                setTheme(savedTheme);
                
                // Handle theme change
                themeSelect.addEventListener('change', function() {
                    setTheme(this.value);
                });
            }
            
            // Initialize User Growth Chart (for admins)
            <?php if (isAdmin($userId) && !empty($adminData)): ?>
            const userGrowthCtx = document.getElementById('userGrowthChart');
            if (userGrowthCtx) {
                const growthData = <?php echo json_encode($adminData['userGrowth']); ?>;
                const labels = growthData.map(item => {
                    const date = new Date(item.date);
                    return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
                }).reverse();
                const data = growthData.map(item => item.new_users).reverse();
                
                new Chart(userGrowthCtx, {
                    type: 'line',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: 'New Users',
                            data: data,
                            borderColor: '#007bff',
                            backgroundColor: 'rgba(0, 123, 255, 0.1)',
                            tension: 0.4,
                            fill: true
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: {
                                beginAtZero: true
                            }
                        },
                        plugins: {
                            legend: {
                                display: false
                            }
                        }
                    }
                });
            }
            <?php endif; ?>
        });
        
        // Helper function to get cookie value
        function getCookie(name) {
            const value = `; ${document.cookie}`;
            const parts = value.split(`; ${name}=`);
            if (parts.length === 2) return parts.pop().split(';').shift();
        }
    </script>
    
    <style>
        .profile-sidebar {
            position: sticky;
            top: 100px;
        }
        
        .profile-card {
            background: var(--bg-primary);
            border-radius: 20px;
            box-shadow: var(--shadow-lg);
            border: 1px solid var(--border-color);
            padding: 2rem;
            text-align: center;
            margin-bottom: 1.5rem;
        }
        
        .profile-avatar {
            font-size: 4rem;
            color: var(--primary-color);
            margin-bottom: 1rem;
        }
        
        .profile-name {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }
        
        .profile-university,
        .profile-joined {
            color: var(--text-secondary);
            margin-bottom: 0.5rem;
        }
        
        .profile-bio {
            color: var(--text-secondary);
            font-style: italic;
            margin-top: 1rem;
        }
        
        .stats-card {
            background: var(--bg-primary);
            border-radius: 15px;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border-color);
            padding: 1.5rem;
        }
        
        .stats-card h5 {
            color: var(--text-primary);
            font-weight: 600;
            margin-bottom: 1rem;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }
        
        .stat-item {
            text-align: center;
            padding: 1rem;
            background: var(--bg-secondary);
            border-radius: 10px;
        }
        
        .stat-item i {
            font-size: 1.5rem;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
        }
        
        .stat-number {
            display: block;
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text-primary);
        }
        
        .stat-label {
            font-size: 0.9rem;
            color: var(--text-secondary);
        }
        
        .nav-tabs {
            border-bottom: 2px solid var(--border-color);
            margin-bottom: 2rem;
        }
        
        .nav-tabs .nav-link {
            border: none;
            color: var(--text-secondary);
            font-weight: 500;
            padding: 1rem 1.5rem;
            border-radius: 10px 10px 0 0;
        }
        
        .nav-tabs .nav-link.active {
            background: var(--primary-color);
            color: white;
        }
        
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--border-color);
        }
        
        .section-header h4 {
            color: var(--text-primary);
            font-weight: 600;
            margin: 0;
        }
        
        .empty-state {
            text-align: center;
            padding: 3rem;
            background: var(--bg-secondary);
            border-radius: 20px;
        }
        
        .resources-grid {
            display: grid;
            gap: 1.5rem;
        }
        
        .resource-item {
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
        
        .resource-item:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }
        
        .resource-icon {
            font-size: 2rem;
            color: var(--primary-color);
        }
        
        .resource-content {
            flex: 1;
        }
        
        .resource-title {
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }
        
        .resource-meta {
            color: var(--text-secondary);
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
        }
        
        .resource-uploader {
            color: var(--text-secondary);
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
        }
        
        .resource-stats {
            display: flex;
            gap: 1rem;
        }
        
        .stat {
            display: flex;
            align-items: center;
            gap: 0.25rem;
            font-size: 0.9rem;
            color: var(--text-secondary);
        }
        
        .resource-actions {
            display: flex;
            gap: 0.5rem;
        }
        
        .settings-section {
            background: var(--bg-primary);
            border-radius: 20px;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border-color);
            padding: 2rem;
        }
        
        .settings-card {
            background: var(--bg-secondary);
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .settings-card h5 {
            color: var(--text-primary);
            font-weight: 600;
            margin-bottom: 1.5rem;
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
        
        .achievements-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
        }
        
        .achievement-item {
            background: var(--bg-secondary);
            border-radius: 15px;
            padding: 1.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            border-left: 4px solid var(--warning-color);
            transition: all 0.3s ease;
        }
        
        .achievement-item:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }
        
        .achievement-icon {
            font-size: 2rem;
            color: var(--warning-color);
        }
        
        .achievement-name {
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }
        
        .achievement-description {
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
            margin-bottom: 2rem;
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
        }
        
        @media (max-width: 768px) {
            .profile-sidebar {
                position: static;
                margin-bottom: 2rem;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .section-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }
            
            .resource-item {
                flex-direction: column;
                text-align: center;
            }
            
            .resource-actions {
                justify-content: center;
            }
        }
        
        /* Admin Overview Styles */
        .admin-overview {
            background: var(--bg-primary);
            border-radius: 20px;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border-color);
            padding: 2rem;
            margin-bottom: 2rem;
        }
        
        .admin-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .admin-header h2 {
            color: var(--text-primary);
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        
        .admin-stat-card {
            background: var(--bg-secondary);
            border-radius: 15px;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border-color);
            padding: 1.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            transition: all 0.3s ease;
        }
        
        .admin-stat-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }
        
        .admin-stat-card.flagged {
            border-left: 4px solid var(--danger-color);
        }
        
        .admin-stat-card .stat-icon {
            font-size: 2.5rem;
            color: var(--primary-color);
        }
        
        .admin-stat-card.flagged .stat-icon {
            color: var(--danger-color);
        }
        
        .admin-stat-card .stat-content h3 {
            font-size: 2rem;
            font-weight: 700;
            color: var(--text-primary);
            margin: 0;
        }
        
        .admin-stat-card .stat-content p {
            color: var(--text-secondary);
            margin: 0;
        }
        
        .admin-section {
            background: var(--bg-secondary);
            border-radius: 15px;
            border: 1px solid var(--border-color);
            padding: 1.5rem;
        }
        
        .section-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--border-color);
        }
        
        .section-header h4 {
            color: var(--text-primary);
            font-weight: 600;
            margin: 0;
        }
        
        /* Theme Support */
        .theme-light {
            --bg-primary: #ffffff;
            --bg-secondary: #f8f9fa;
            --bg-tertiary: #e9ecef;
            --text-primary: #212529;
            --text-secondary: #6c757d;
            --border-color: #dee2e6;
            --primary-color: #0d6efd;
            --danger-color: #dc3545;
            --success-color: #198754;
            --warning-color: #ffc107;
            --shadow-sm: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            --shadow-md: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
            --shadow-lg: 0 1rem 3rem rgba(0, 0, 0, 0.175);
        }
        
        .theme-dark {
            --bg-primary: #1a1a1a;
            --bg-secondary: #2d2d2d;
            --bg-tertiary: #3d3d3d;
            --text-primary: #ffffff;
            --text-secondary: #adb5bd;
            --border-color: #495057;
            --primary-color: #0d6efd;
            --danger-color: #dc3545;
            --success-color: #198754;
            --warning-color: #ffc107;
            --shadow-sm: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.3);
            --shadow-md: 0 0.5rem 1rem rgba(0, 0, 0, 0.4);
            --shadow-lg: 0 1rem 3rem rgba(0, 0, 0, 0.5);
        }
        
        /* Apply theme variables */
        body {
            background-color: var(--bg-primary);
            color: var(--text-primary);
            transition: background-color 0.3s ease, color 0.3s ease;
        }
        
        .card, .profile-card, .stats-card, .settings-card, .admin-overview, .admin-section {
            background-color: var(--bg-primary);
            border-color: var(--border-color);
            color: var(--text-primary);
        }
        
        .form-control, .form-select {
            background-color: var(--bg-secondary);
            border-color: var(--border-color);
            color: var(--text-primary);
        }
        
        .form-control:focus, .form-select:focus {
            background-color: var(--bg-secondary);
            border-color: var(--primary-color);
            color: var(--text-primary);
            box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.25);
        }
        
        .table {
            color: var(--text-primary);
        }
        
        .table th {
            background-color: var(--bg-tertiary);
            border-color: var(--border-color);
        }
        
        .table td {
            border-color: var(--border-color);
        }
        
        .table-hover tbody tr:hover {
            background-color: var(--bg-tertiary);
        }
        
        @media (max-width: 768px) {
            .admin-stat-card {
                flex-direction: column;
                text-align: center;
            }
        }
    </style>
</body>
</html>

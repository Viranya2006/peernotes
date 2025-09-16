<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Require admin access
requireAdmin();

$error = '';
$success = '';

// Handle admin actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'delete_resource') {
        $resourceId = (int)($_POST['resource_id'] ?? 0);
        
        if ($resourceId > 0) {
            try {
                // Get resource details for file deletion
                $stmt = $pdo->prepare("SELECT file_path FROM resources WHERE id = ?");
                $stmt->execute([$resourceId]);
                $resource = $stmt->fetch();
                
                if ($resource) {
                    // Delete file
                    if (file_exists($resource['file_path'])) {
                        unlink($resource['file_path']);
                    }
                    
                    // Delete from database
                    $stmt = $pdo->prepare("DELETE FROM resources WHERE id = ?");
                    $stmt->execute([$resourceId]);
                    
                    $success = 'Resource deleted successfully!';
                } else {
                    $error = 'Resource not found.';
                }
                
            } catch (PDOException $e) {
                $error = 'Failed to delete resource. Please try again.';
                error_log("Admin resource deletion error: " . $e->getMessage());
            }
        }
    }
    
    elseif ($action === 'unflag_resource') {
        $resourceId = (int)($_POST['resource_id'] ?? 0);
        
        if ($resourceId > 0) {
            try {
                $stmt = $pdo->prepare("UPDATE resources SET is_flagged = 0 WHERE id = ?");
                $stmt->execute([$resourceId]);
                
                // Update report status
                $stmt = $pdo->prepare("UPDATE reports SET status = 'resolved' WHERE resource_id = ?");
                $stmt->execute([$resourceId]);
                
                $success = 'Resource unflagged successfully!';
                
            } catch (PDOException $e) {
                $error = 'Failed to unflag resource. Please try again.';
                error_log("Admin unflag error: " . $e->getMessage());
            }
        }
    }
    
    elseif ($action === 'suspend_user') {
        $userId = (int)($_POST['user_id'] ?? 0);
        $reason = sanitizeInput($_POST['reason'] ?? '');
        $duration = (int)($_POST['duration'] ?? 0); // days
        
        if ($userId > 0 && !empty($reason)) {
            try {
                $suspendedUntil = $duration > 0 ? date('Y-m-d H:i:s', strtotime("+$duration days")) : null;
                
                $stmt = $pdo->prepare("UPDATE users SET is_suspended = 1, suspended_until = ?, suspension_reason = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$suspendedUntil, $reason, $userId]);
                
                $success = 'User suspended successfully!';
                
            } catch (PDOException $e) {
                $error = 'Failed to suspend user. Please try again.';
                error_log("Admin suspend error: " . $e->getMessage());
            }
        }
    }
    
    elseif ($action === 'unsuspend_user') {
        $userId = (int)($_POST['user_id'] ?? 0);
        
        if ($userId > 0) {
            try {
                $stmt = $pdo->prepare("UPDATE users SET is_suspended = 0, suspended_until = NULL, suspension_reason = NULL, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$userId]);
                
                $success = 'User unsuspended successfully!';
                
            } catch (PDOException $e) {
                $error = 'Failed to unsuspend user. Please try again.';
                error_log("Admin unsuspend error: " . $e->getMessage());
            }
        }
    }
    
    elseif ($action === 'ban_user') {
        $userId = (int)($_POST['user_id'] ?? 0);
        $reason = sanitizeInput($_POST['reason'] ?? '');
        
        if ($userId > 0 && !empty($reason)) {
            try {
                $stmt = $pdo->prepare("UPDATE users SET is_banned = 1, ban_reason = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$reason, $userId]);
                
                // Flag all user's resources
                $stmt = $pdo->prepare("UPDATE resources SET is_flagged = 1 WHERE user_id = ?");
                $stmt->execute([$userId]);
                
                $success = 'User banned successfully!';
                
            } catch (PDOException $e) {
                $error = 'Failed to ban user. Please try again.';
                error_log("Admin ban error: " . $e->getMessage());
            }
        }
    }
    
    elseif ($action === 'unban_user') {
        $userId = (int)($_POST['user_id'] ?? 0);
        
        if ($userId > 0) {
            try {
                $stmt = $pdo->prepare("UPDATE users SET is_banned = 0, ban_reason = NULL, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$userId]);
                
                $success = 'User unbanned successfully!';
                
            } catch (PDOException $e) {
                $error = 'Failed to unban user. Please try again.';
                error_log("Admin unban error: " . $e->getMessage());
            }
        }
    }
}

// Initialize variables to prevent undefined variable errors
$flaggedResources = [];
$stats = [];
$recentReports = [];
$users = [];
$platformStats = [];

try {
    // Get flagged resources with details
    $stmt = $pdo->prepare("
        SELECT 
            r.*,
            u.name as uploader_name,
            u.email as uploader_email,
            u.university as uploader_university,
            COUNT(reports.id) as report_count,
            GROUP_CONCAT(reports.reason SEPARATOR ', ') as report_reasons,
            MAX(reports.created_at) as last_report_date
        FROM resources r
        LEFT JOIN users u ON r.user_id = u.id
        LEFT JOIN reports ON r.id = reports.resource_id
        WHERE r.is_flagged = 1
        GROUP BY r.id
        ORDER BY r.upload_date DESC
    ");
    
    $stmt->execute();
    $flaggedResources = $stmt->fetchAll();
    
    // Get admin stats
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_resources,
            COUNT(CASE WHEN is_flagged = 1 THEN 1 END) as flagged_resources,
            COUNT(CASE WHEN upload_date >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as recent_resources
        FROM resources
    ");
    $stmt->execute();
    $stats = $stmt->fetch();
    
    // Get recent reports
    $stmt = $pdo->prepare("
        SELECT 
            reports.*,
            r.title as resource_title,
            u.name as reporter_name
        FROM reports
        LEFT JOIN resources r ON reports.resource_id = r.id
        LEFT JOIN users u ON reports.user_id = u.id
        ORDER BY reports.created_at DESC
        LIMIT 10
    ");
    $stmt->execute();
    $recentReports = $stmt->fetchAll();
    
    // Get user management data
    $stmt = $pdo->prepare("
        SELECT 
            u.id,
            u.name,
            u.email,
            u.university,
            u.created_at,
            u.is_suspended,
            u.suspended_until,
            u.suspension_reason,
            u.is_banned,
            u.ban_reason,
            COUNT(r.id) as resource_count,
            COUNT(CASE WHEN r.is_flagged = 1 THEN 1 END) as flagged_resources
        FROM users u
        LEFT JOIN resources r ON u.id = r.user_id
        WHERE u.is_admin = 0
        GROUP BY u.id
        ORDER BY u.created_at DESC
        LIMIT 20
    ");
    $stmt->execute();
    $users = $stmt->fetchAll();
    
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
    $platformStats = $stmt->fetch();
    
} catch (PDOException $e) {
    error_log("Admin data error: " . $e->getMessage());
    $error = 'Failed to load admin data.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - PeerNotes</title>
    <meta name="description" content="Admin panel for managing flagged resources">
    
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
                    <?php if (isset($_SESSION['user_id'])): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="upload.php">Upload</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="profile.php">Profile</a>
                    </li>
                    <?php if (isAdmin($_SESSION['user_id'])): ?>
                    <li class="nav-item">
                        <a class="nav-link active" href="admin.php">Admin</a>
                    </li>
                    <?php endif; ?>
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

    <!-- Admin Panel -->
    <section class="py-5" style="margin-top: 80px;">
        <div class="container">
            <div class="admin-header mb-4">
                <h1 class="admin-title">
                    <i class="bi bi-shield-check me-2"></i>Admin Panel
                </h1>
                <p class="admin-subtitle">Manage flagged resources and platform moderation</p>
            </div>
            
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
            
            <!-- Stats Cards -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="stats-card">
                        <div class="stats-icon">
                            <i class="bi bi-people"></i>
                        </div>
                        <div class="stats-content">
                            <h3><?php echo number_format($platformStats['total_users'] ?? 0); ?></h3>
                            <p>Total Users</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stats-card">
                        <div class="stats-icon">
                            <i class="bi bi-file-earmark"></i>
                        </div>
                        <div class="stats-content">
                            <h3><?php echo number_format($platformStats['total_resources'] ?? 0); ?></h3>
                            <p>Total Resources</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stats-card flagged">
                        <div class="stats-icon">
                            <i class="bi bi-flag"></i>
                        </div>
                        <div class="stats-content">
                            <h3><?php echo number_format($platformStats['flagged_resources'] ?? 0); ?></h3>
                            <p>Flagged Resources</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stats-card recent">
                        <div class="stats-icon">
                            <i class="bi bi-exclamation-triangle"></i>
                        </div>
                        <div class="stats-content">
                            <h3><?php echo number_format($platformStats['total_reports'] ?? 0); ?></h3>
                            <p>Total Reports</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Charts Section -->
            <div class="row mb-5">
                <div class="col-md-6">
                    <div class="admin-section">
                        <div class="section-header">
                            <h4><i class="bi bi-graph-up me-2"></i>Platform Overview</h4>
                        </div>
                        <div style="position: relative; height: 300px;">
                            <canvas id="platformChart"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="admin-section">
                        <div class="section-header">
                            <h4><i class="bi bi-calendar-week me-2"></i>Recent Activity (Last 7 Days)</h4>
                        </div>
                        <div style="position: relative; height: 300px;">
                            <canvas id="activityChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="row mb-5">
                <div class="col-md-6">
                    <div class="admin-section">
                        <div class="section-header">
                            <h4><i class="bi bi-pie-chart me-2"></i>User Status Distribution</h4>
                        </div>
                        <div style="position: relative; height: 300px;">
                            <canvas id="userStatusChart"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="admin-section">
                        <div class="section-header">
                            <h4><i class="bi bi-bar-chart me-2"></i>Resource Categories</h4>
                        </div>
                        <div style="position: relative; height: 300px;">
                            <canvas id="categoryChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Flagged Resources -->
            <div class="admin-section">
                <div class="section-header">
                    <h4>
                        <i class="bi bi-flag me-2"></i>Flagged Resources
                        <span class="badge bg-danger ms-2"><?php echo count($flaggedResources); ?></span>
                    </h4>
                </div>
                
                <?php if (empty($flaggedResources)): ?>
                    <div class="empty-state">
                        <i class="bi bi-check-circle display-1 text-success mb-3"></i>
                        <h5>No flagged resources</h5>
                        <p class="text-muted">All resources are clean and approved!</p>
                    </div>
                <?php else: ?>
                    <div class="flagged-resources">
                        <?php foreach ($flaggedResources as $resource): ?>
                            <div class="flagged-resource-card">
                                <div class="resource-header">
                                    <div class="resource-info">
                                        <div class="resource-icon">
                                            <i class="<?php echo getFileIcon($resource['file_type']); ?>"></i>
                                        </div>
                                        <div class="resource-details">
                                            <h6 class="resource-title"><?php echo htmlspecialchars($resource['title']); ?></h6>
                                            <p class="resource-meta">
                                                <?php echo htmlspecialchars($resource['subject']); ?> • 
                                                <?php echo htmlspecialchars($resource['course']); ?> • 
                                                <?php echo htmlspecialchars($resource['academic_year']); ?>
                                            </p>
                                            <p class="resource-uploader">
                                                <i class="bi bi-person me-1"></i>
                                                <?php echo htmlspecialchars($resource['uploader_name'] ?? 'Unknown User'); ?>
                                                <?php if ($resource['uploader_university']): ?>
                                                    (<?php echo htmlspecialchars($resource['uploader_university']); ?>)
                                                <?php endif; ?>
                                            </p>
                                        </div>
                                    </div>
                                    <div class="resource-actions">
                                        <a href="resource.php?id=<?php echo $resource['id']; ?>" class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-eye me-1"></i>View
                                        </a>
                                        <button class="btn btn-sm btn-outline-success" onclick="unflagResource(<?php echo $resource['id']; ?>)">
                                            <i class="bi bi-check me-1"></i>Approve
                                        </button>
                                        <button class="btn btn-sm btn-outline-danger" onclick="deleteResource(<?php echo $resource['id']; ?>)">
                                            <i class="bi bi-trash me-1"></i>Delete
                                        </button>
                                    </div>
                                </div>
                                
                                <div class="resource-reports">
                                    <h6>Reports (<?php echo $resource['report_count']; ?>)</h6>
                                    <div class="report-reasons">
                                        <span class="badge bg-warning me-2"><?php echo htmlspecialchars($resource['report_reasons'] ?? 'No reason specified'); ?></span>
                                        <small class="text-muted">Last report: <?php echo timeAgo($resource['last_report_date']); ?></small>
                                    </div>
                                </div>
                                
                                <div class="resource-description">
                                    <p><?php echo htmlspecialchars(substr($resource['description'], 0, 200)) . (strlen($resource['description']) > 200 ? '...' : ''); ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- User Management -->
            <div class="admin-section mt-5">
                <div class="section-header">
                    <h4>
                        <i class="bi bi-people me-2"></i>User Management
                        <span class="badge bg-info ms-2"><?php echo count($users); ?> users</span>
                    </h4>
                </div>
                
                <?php if (empty($users)): ?>
                    <div class="empty-state">
                        <i class="bi bi-people display-1 text-muted mb-3"></i>
                        <h5>No users found</h5>
                        <p class="text-muted">No users to manage at the moment.</p>
                    </div>
                <?php else: ?>
                    <div class="users-table">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>User</th>
                                        <th>University</th>
                                        <th>Resources</th>
                                        <th>Status</th>
                                        <th>Joined</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($users as $user): ?>
                                        <tr>
                                            <td>
                                                <div class="user-info">
                                                    <strong><?php echo htmlspecialchars($user['name']); ?></strong>
                                                    <br>
                                                    <small class="text-muted"><?php echo htmlspecialchars($user['email']); ?></small>
                                                </div>
                                            </td>
                                            <td><?php echo htmlspecialchars($user['university'] ?? 'Not specified'); ?></td>
                                            <td>
                                                <span class="badge bg-primary"><?php echo $user['resource_count']; ?></span>
                                                <?php if ($user['flagged_resources'] > 0): ?>
                                                    <span class="badge bg-danger"><?php echo $user['flagged_resources']; ?> flagged</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($user['is_banned']): ?>
                                                    <span class="badge bg-danger">Banned</span>
                                                <?php elseif ($user['is_suspended']): ?>
                                                    <span class="badge bg-warning">Suspended</span>
                                                <?php else: ?>
                                                    <span class="badge bg-success">Active</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo timeAgo($user['created_at']); ?></td>
                                            <td>
                                                <div class="btn-group" role="group">
                                                    <?php if ($user['is_banned']): ?>
                                                        <button class="btn btn-sm btn-outline-success" onclick="unbanUser(<?php echo $user['id']; ?>)">
                                                            <i class="bi bi-check-circle"></i> Unban
                                                        </button>
                                                    <?php elseif ($user['is_suspended']): ?>
                                                        <button class="btn btn-sm btn-outline-success" onclick="unsuspendUser(<?php echo $user['id']; ?>)">
                                                            <i class="bi bi-check-circle"></i> Unsuspend
                                                        </button>
                                                    <?php else: ?>
                                                        <button class="btn btn-sm btn-outline-warning" onclick="suspendUser(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['name']); ?>')">
                                                            <i class="bi bi-pause-circle"></i> Suspend
                                                        </button>
                                                        <button class="btn btn-sm btn-outline-danger" onclick="banUser(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['name']); ?>')">
                                                            <i class="bi bi-x-circle"></i> Ban
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Recent Reports -->
            <div class="admin-section mt-5">
                <div class="section-header">
                    <h4>
                        <i class="bi bi-exclamation-triangle me-2"></i>Recent Reports
                    </h4>
                </div>
                
                <?php if (empty($recentReports)): ?>
                    <div class="empty-state">
                        <i class="bi bi-shield-check display-1 text-success mb-3"></i>
                        <h5>No recent reports</h5>
                        <p class="text-muted">The platform is running smoothly!</p>
                    </div>
                <?php else: ?>
                    <div class="reports-table">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Resource</th>
                                        <th>Reporter</th>
                                        <th>Reason</th>
                                        <th>Date</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentReports as $report): ?>
                                        <tr>
                                            <td>
                                                <a href="resource.php?id=<?php echo $report['resource_id']; ?>" class="text-decoration-none">
                                                    <?php echo htmlspecialchars($report['resource_title'] ?? 'Unknown Resource'); ?>
                                                </a>
                                            </td>
                                            <td><?php echo htmlspecialchars($report['reporter_name'] ?? 'Unknown User'); ?></td>
                                            <td>
                                                <span class="badge bg-warning"><?php echo htmlspecialchars($report['reason'] ?? 'Unknown Reason'); ?></span>
                                            </td>
                                            <td><?php echo timeAgo($report['created_at']); ?></td>
                                            <td>
                                                <span class="badge bg-<?php echo $report['status'] === 'pending' ? 'warning' : ($report['status'] === 'resolved' ? 'success' : 'info'); ?>">
                                                    <?php echo ucfirst($report['status']); ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>
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
                    <p>Are you sure you want to permanently delete this resource? This action cannot be undone.</p>
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        This will also delete the associated file from the server.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="confirmDelete">Delete Permanently</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Unflag Confirmation Modal -->
    <div class="modal fade" id="unflagModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Approve Resource</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to approve this resource and remove the flag?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-success" id="confirmUnflag">Approve Resource</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Suspend User Modal -->
    <div class="modal fade" id="suspendModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Suspend User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="suspend_user">
                        <input type="hidden" name="user_id" id="suspendUserId">
                        <p>You are about to suspend user: <strong id="suspendUserName"></strong></p>
                        <div class="mb-3">
                            <label for="suspendReason" class="form-label">Reason for suspension</label>
                            <textarea class="form-control" id="suspendReason" name="reason" rows="3" required></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="suspendDuration" class="form-label">Duration (days)</label>
                            <select class="form-select" id="suspendDuration" name="duration">
                                <option value="1">1 day</option>
                                <option value="3">3 days</option>
                                <option value="7">1 week</option>
                                <option value="30">1 month</option>
                                <option value="0">Indefinite</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-warning">Suspend User</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Ban User Modal -->
    <div class="modal fade" id="banModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Ban User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="ban_user">
                        <input type="hidden" name="user_id" id="banUserId">
                        <p>You are about to <strong class="text-danger">permanently ban</strong> user: <strong id="banUserName"></strong></p>
                        <div class="alert alert-danger">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            <strong>Warning:</strong> This action will ban the user permanently and flag all their resources.
                        </div>
                        <div class="mb-3">
                            <label for="banReason" class="form-label">Reason for ban</label>
                            <textarea class="form-control" id="banReason" name="reason" rows="3" required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Ban User</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Unsuspend Confirmation Modal -->
    <div class="modal fade" id="unsuspendModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Unsuspend User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to unsuspend this user?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-success" id="confirmUnsuspend">Unsuspend User</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Unban Confirmation Modal -->
    <div class="modal fade" id="unbanModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Unban User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to unban this user?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-success" id="confirmUnban">Unban User</button>
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
        let resourceToUnflag = null;
        let userToSuspend = null;
        let userToBan = null;
        let userToUnsuspend = null;
        let userToUnban = null;
        
        function deleteResource(resourceId) {
            resourceToDelete = resourceId;
            const modal = new bootstrap.Modal(document.getElementById('deleteModal'));
            modal.show();
        }
        
        function unflagResource(resourceId) {
            resourceToUnflag = resourceId;
            const modal = new bootstrap.Modal(document.getElementById('unflagModal'));
            modal.show();
        }
        
        function suspendUser(userId, userName) {
            userToSuspend = userId;
            document.getElementById('suspendUserId').value = userId;
            document.getElementById('suspendUserName').textContent = userName;
            const modal = new bootstrap.Modal(document.getElementById('suspendModal'));
            modal.show();
        }
        
        function banUser(userId, userName) {
            userToBan = userId;
            document.getElementById('banUserId').value = userId;
            document.getElementById('banUserName').textContent = userName;
            const modal = new bootstrap.Modal(document.getElementById('banModal'));
            modal.show();
        }
        
        function unsuspendUser(userId) {
            userToUnsuspend = userId;
            const modal = new bootstrap.Modal(document.getElementById('unsuspendModal'));
            modal.show();
        }
        
        function unbanUser(userId) {
            userToUnban = userId;
            const modal = new bootstrap.Modal(document.getElementById('unbanModal'));
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
        
        document.getElementById('confirmUnflag').addEventListener('click', function() {
            if (resourceToUnflag) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="unflag_resource">
                    <input type="hidden" name="resource_id" value="${resourceToUnflag}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        });
        
        document.getElementById('confirmUnsuspend').addEventListener('click', function() {
            if (userToUnsuspend) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="unsuspend_user">
                    <input type="hidden" name="user_id" value="${userToUnsuspend}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        });
        
        document.getElementById('confirmUnban').addEventListener('click', function() {
            if (userToUnban) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="unban_user">
                    <input type="hidden" name="user_id" value="${userToUnban}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        });
        
        // Initialize Charts
        document.addEventListener('DOMContentLoaded', function() {
            // Platform Overview Chart
            const platformCtx = document.getElementById('platformChart');
            if (platformCtx) {
                new Chart(platformCtx, {
                    type: 'doughnut',
                    data: {
                        labels: ['Users', 'Resources', 'Flagged', 'Reports'],
                        datasets: [{
                            data: [
                                <?php echo $platformStats['total_users'] ?? 0; ?>,
                                <?php echo $platformStats['total_resources'] ?? 0; ?>,
                                <?php echo $platformStats['flagged_resources'] ?? 0; ?>,
                                <?php echo $platformStats['total_reports'] ?? 0; ?>
                            ],
                            backgroundColor: [
                                '#007bff',
                                '#28a745',
                                '#dc3545',
                                '#ffc107'
                            ],
                            borderWidth: 2,
                            borderColor: '#fff'
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: {
                                    padding: 20,
                                    usePointStyle: true
                                }
                            }
                        },
                        layout: {
                            padding: {
                                top: 10,
                                bottom: 10
                            }
                        }
                    }
                });
            }
            
            // Activity Chart (Last 7 days)
            const activityCtx = document.getElementById('activityChart');
            if (activityCtx) {
                new Chart(activityCtx, {
                    type: 'line',
                    data: {
                        labels: ['6 days ago', '5 days ago', '4 days ago', '3 days ago', '2 days ago', 'Yesterday', 'Today'],
                        datasets: [{
                            label: 'New Users',
                            data: [2, 3, 1, 4, 2, 3, 1], // Sample data - replace with real data
                            borderColor: '#007bff',
                            backgroundColor: 'rgba(0, 123, 255, 0.1)',
                            tension: 0.4,
                            fill: true
                        }, {
                            label: 'New Resources',
                            data: [5, 7, 3, 8, 6, 9, 4], // Sample data - replace with real data
                            borderColor: '#28a745',
                            backgroundColor: 'rgba(40, 167, 69, 0.1)',
                            tension: 0.4,
                            fill: true
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: {
                                beginAtZero: true,
                                grid: {
                                    color: 'rgba(0,0,0,0.1)'
                                }
                            },
                            x: {
                                grid: {
                                    color: 'rgba(0,0,0,0.1)'
                                }
                            }
                        },
                        plugins: {
                            legend: {
                                position: 'top',
                                labels: {
                                    padding: 20,
                                    usePointStyle: true
                                }
                            }
                        },
                        layout: {
                            padding: {
                                top: 10,
                                bottom: 10
                            }
                        }
                    }
                });
            }
            
            // User Status Chart
            const userStatusCtx = document.getElementById('userStatusChart');
            if (userStatusCtx) {
                new Chart(userStatusCtx, {
                    type: 'pie',
                    data: {
                        labels: ['Active', 'Suspended', 'Banned'],
                        datasets: [{
                            data: [
                                <?php echo ($platformStats['total_users'] ?? 0) - ($platformStats['suspended_users'] ?? 0) - ($platformStats['banned_users'] ?? 0); ?>,
                                <?php echo $platformStats['suspended_users'] ?? 0; ?>,
                                <?php echo $platformStats['banned_users'] ?? 0; ?>
                            ],
                            backgroundColor: [
                                '#28a745',
                                '#ffc107',
                                '#dc3545'
                            ],
                            borderWidth: 2,
                            borderColor: '#fff'
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: {
                                    padding: 20,
                                    usePointStyle: true
                                }
                            }
                        },
                        layout: {
                            padding: {
                                top: 10,
                                bottom: 10
                            }
                        }
                    }
                });
            }
            
            // Category Chart
            const categoryCtx = document.getElementById('categoryChart');
            if (categoryCtx) {
                new Chart(categoryCtx, {
                    type: 'bar',
                    data: {
                        labels: ['Computer Science', 'Mathematics', 'Physics', 'Chemistry', 'Biology', 'Engineering'],
                        datasets: [{
                            label: 'Resources',
                            data: [12, 8, 6, 4, 7, 9], // Sample data - replace with real data
                            backgroundColor: [
                                '#007bff',
                                '#28a745',
                                '#dc3545',
                                '#ffc107',
                                '#17a2b8',
                                '#6f42c1'
                            ],
                            borderWidth: 1,
                            borderRadius: 4
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: {
                                beginAtZero: true,
                                grid: {
                                    color: 'rgba(0,0,0,0.1)'
                                }
                            },
                            x: {
                                grid: {
                                    color: 'rgba(0,0,0,0.1)'
                                }
                            }
                        },
                        plugins: {
                            legend: {
                                display: false
                            }
                        },
                        layout: {
                            padding: {
                                top: 10,
                                bottom: 10
                            }
                        }
                    }
                });
            }
        });
    </script>
    
    <style>
        .admin-header {
            text-align: center;
            margin-bottom: 3rem;
        }
        
        .admin-title {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }
        
        .admin-subtitle {
            color: var(--text-secondary);
            font-size: 1.1rem;
        }
        
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
        
        .stats-card.flagged {
            border-left: 4px solid var(--danger-color);
        }
        
        .stats-card.recent {
            border-left: 4px solid var(--success-color);
        }
        
        .stats-icon {
            font-size: 2.5rem;
            color: var(--primary-color);
        }
        
        .stats-card.flagged .stats-icon {
            color: var(--danger-color);
        }
        
        .stats-card.recent .stats-icon {
            color: var(--success-color);
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
        
        .admin-section {
            background: var(--bg-primary);
            border-radius: 20px;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border-color);
            padding: 2rem;
        }
        
        .section-header {
            display: flex;
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
        
        .flagged-resources {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }
        
        .flagged-resource-card {
            background: var(--bg-secondary);
            border-radius: 15px;
            border: 1px solid var(--border-color);
            padding: 1.5rem;
            border-left: 4px solid var(--danger-color);
        }
        
        .resource-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }
        
        .resource-info {
            display: flex;
            gap: 1rem;
            flex: 1;
        }
        
        .resource-icon {
            font-size: 2rem;
            color: var(--primary-color);
        }
        
        .resource-details {
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
            margin-bottom: 0;
        }
        
        .resource-actions {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        
        .resource-reports {
            background: rgba(239, 68, 68, 0.1);
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1rem;
        }
        
        .resource-reports h6 {
            color: var(--danger-color);
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
        
        .report-reasons {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .resource-description {
            color: var(--text-secondary);
            font-size: 0.9rem;
        }
        
        .reports-table {
            background: var(--bg-secondary);
            border-radius: 15px;
            overflow: hidden;
        }
        
        .table {
            margin: 0;
        }
        
        .table th {
            background: var(--bg-tertiary);
            color: var(--text-primary);
            font-weight: 600;
            border: none;
        }
        
        .table td {
            border: none;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-secondary);
        }
        
        .table tbody tr:hover {
            background: var(--bg-tertiary);
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
        
        .alert-warning {
            background: rgba(245, 158, 11, 0.1);
            color: var(--warning-color);
        }
        
        @media (max-width: 768px) {
            .admin-title {
                font-size: 2rem;
            }
            
            .stats-card {
                flex-direction: column;
                text-align: center;
            }
            
            .resource-header {
                flex-direction: column;
                gap: 1rem;
            }
            
            .resource-info {
                flex-direction: column;
                text-align: center;
            }
            
            .resource-actions {
                justify-content: center;
            }
            
            .report-reasons {
                flex-direction: column;
                align-items: flex-start;
            }
        }
    </style>
</body>
</html>

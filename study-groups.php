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
    
    if ($action === 'create_group') {
        $name = sanitizeInput($_POST['name'] ?? '');
        $description = sanitizeInput($_POST['description'] ?? '');
        $subject = sanitizeInput($_POST['subject'] ?? '');
        $maxMembers = (int)($_POST['max_members'] ?? 10);
        
        if (empty($name) || empty($description) || empty($subject)) {
            $error = 'All fields are required.';
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO study_groups (name, description, subject, max_members, created_by, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
                $stmt->execute([$name, $description, $subject, $maxMembers, $userId]);
                
                $groupId = $pdo->lastInsertId();
                
                // Add creator as member
                $stmt = $pdo->prepare("INSERT INTO study_group_members (group_id, user_id, role, joined_at) VALUES (?, ?, 'admin', NOW())");
                $stmt->execute([$groupId, $userId]);
                
                $success = 'Study group created successfully!';
                
            } catch (PDOException $e) {
                $error = 'Failed to create study group. Please try again.';
                error_log("Study group creation error: " . $e->getMessage());
            }
        }
    }
    
    elseif ($action === 'join_group') {
        $groupId = (int)($_POST['group_id'] ?? 0);
        
        if ($groupId > 0) {
            try {
                // Check if already a member
                $stmt = $pdo->prepare("SELECT id FROM study_group_members WHERE group_id = ? AND user_id = ?");
                $stmt->execute([$groupId, $userId]);
                
                if ($stmt->fetch()) {
                    $error = 'You are already a member of this group.';
                } else {
                    // Check if group has space
                    $stmt = $pdo->prepare("
                        SELECT sg.max_members, COUNT(sgm.id) as current_members 
                        FROM study_groups sg 
                        LEFT JOIN study_group_members sgm ON sg.id = sgm.group_id 
                        WHERE sg.id = ? 
                        GROUP BY sg.id
                    ");
                    $stmt->execute([$groupId]);
                    $group = $stmt->fetch();
                    
                    if ($group && $group['current_members'] < $group['max_members']) {
                        $stmt = $pdo->prepare("INSERT INTO study_group_members (group_id, user_id, role, joined_at) VALUES (?, ?, 'member', NOW())");
                        $stmt->execute([$groupId, $userId]);
                        
                        $success = 'Successfully joined the study group!';
                    } else {
                        $error = 'This group is full.';
                    }
                }
                
            } catch (PDOException $e) {
                $error = 'Failed to join study group. Please try again.';
                error_log("Study group join error: " . $e->getMessage());
            }
        }
    }
    
    elseif ($action === 'leave_group') {
        $groupId = (int)($_POST['group_id'] ?? 0);
        
        if ($groupId > 0) {
            try {
                $stmt = $pdo->prepare("DELETE FROM study_group_members WHERE group_id = ? AND user_id = ? AND role != 'admin'");
                $stmt->execute([$groupId, $userId]);
                
                if ($stmt->rowCount() > 0) {
                    $success = 'Left the study group successfully!';
                } else {
                    $error = 'Cannot leave group as admin. Transfer ownership first.';
                }
                
            } catch (PDOException $e) {
                $error = 'Failed to leave study group. Please try again.';
                error_log("Study group leave error: " . $e->getMessage());
            }
        }
    }
}

try {
    // Get user's study groups
    $stmt = $pdo->prepare("
        SELECT 
            sg.*,
            sgm.role,
            sgm.joined_at,
            u.name as creator_name,
            COUNT(sgm2.id) as member_count
        FROM study_groups sg
        LEFT JOIN study_group_members sgm ON sg.id = sgm.group_id AND sgm.user_id = ?
        LEFT JOIN users u ON sg.created_by = u.id
        LEFT JOIN study_group_members sgm2 ON sg.id = sgm2.group_id
        WHERE sgm.user_id IS NOT NULL OR sg.created_by = ?
        GROUP BY sg.id
        ORDER BY sg.created_at DESC
    ");
    $stmt->execute([$userId, $userId]);
    $userGroups = $stmt->fetchAll();
    
    // Get available study groups
    $stmt = $pdo->prepare("
        SELECT 
            sg.*,
            u.name as creator_name,
            COUNT(sgm.id) as member_count,
            CASE WHEN sgm2.user_id IS NOT NULL THEN 1 ELSE 0 END as is_member
        FROM study_groups sg
        LEFT JOIN users u ON sg.created_by = u.id
        LEFT JOIN study_group_members sgm ON sg.id = sgm.group_id
        LEFT JOIN study_group_members sgm2 ON sg.id = sgm2.group_id AND sgm2.user_id = ?
        GROUP BY sg.id
        HAVING is_member = 0 AND member_count < sg.max_members
        ORDER BY sg.created_at DESC
        LIMIT 20
    ");
    $stmt->execute([$userId]);
    $availableGroups = $stmt->fetchAll();
    
} catch (PDOException $e) {
    error_log("Study groups data error: " . $e->getMessage());
    $error = 'Failed to load study groups data.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Study Groups - PeerNotes</title>
    <meta name="description" content="Join study groups and collaborate with fellow students">
    
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
                        <a class="nav-link active" href="study-groups.php">Study Groups</a>
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

    <!-- Study Groups Section -->
    <section class="py-5" style="margin-top: 80px;">
        <div class="container">
            <div class="row mb-4">
                <div class="col-12">
                    <div class="text-center mb-4">
                        <h1 class="text-gradient">Study Groups</h1>
                        <p class="lead">Collaborate with fellow students and enhance your learning experience</p>
                    </div>
                </div>
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
            
            <!-- Create Group Button -->
            <div class="row mb-4">
                <div class="col-12">
                    <button class="btn btn-primary btn-lg" data-bs-toggle="modal" data-bs-target="#createGroupModal">
                        <i class="bi bi-plus-circle me-2"></i>Create Study Group
                    </button>
                </div>
            </div>
            
            <!-- My Study Groups -->
            <div class="row mb-5">
                <div class="col-12">
                    <h3 class="mb-4">My Study Groups</h3>
                    
                    <?php if (empty($userGroups)): ?>
                        <div class="empty-state text-center py-5">
                            <i class="bi bi-people display-1 text-muted mb-3"></i>
                            <h5>No study groups yet</h5>
                            <p class="text-muted">Create or join a study group to start collaborating!</p>
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createGroupModal">
                                <i class="bi bi-plus-circle me-2"></i>Create Your First Group
                            </button>
                        </div>
                    <?php else: ?>
                        <div class="row">
                            <?php foreach ($userGroups as $group): ?>
                                <div class="col-lg-4 col-md-6 mb-4">
                                    <div class="card study-group-card h-100">
                                        <div class="card-header">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <h5 class="mb-0"><?php echo htmlspecialchars($group['name']); ?></h5>
                                                <span class="badge bg-<?php echo $group['role'] === 'admin' ? 'danger' : 'primary'; ?>">
                                                    <?php echo ucfirst($group['role']); ?>
                                                </span>
                                            </div>
                                        </div>
                                        <div class="card-body">
                                            <p class="card-text"><?php echo htmlspecialchars($group['description']); ?></p>
                                            <div class="group-meta mb-3">
                                                <div class="meta-item">
                                                    <i class="bi bi-book me-2"></i>
                                                    <span><?php echo htmlspecialchars($group['subject']); ?></span>
                                                </div>
                                                <div class="meta-item">
                                                    <i class="bi bi-people me-2"></i>
                                                    <span><?php echo $group['member_count']; ?>/<?php echo $group['max_members']; ?> members</span>
                                                </div>
                                                <div class="meta-item">
                                                    <i class="bi bi-person me-2"></i>
                                                    <span>Created by <?php echo htmlspecialchars($group['creator_name']); ?></span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="card-footer">
                                            <div class="btn-group w-100" role="group">
                                                <button class="btn btn-outline-primary btn-sm" onclick="viewGroupDetails(<?php echo $group['id']; ?>)">
                                                    <i class="bi bi-eye me-1"></i>View
                                                </button>
                                                <?php if ($group['role'] !== 'admin'): ?>
                                                    <button class="btn btn-outline-danger btn-sm" onclick="leaveGroup(<?php echo $group['id']; ?>)">
                                                        <i class="bi bi-box-arrow-right me-1"></i>Leave
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Available Study Groups -->
            <div class="row">
                <div class="col-12">
                    <h3 class="mb-4">Available Study Groups</h3>
                    
                    <?php if (empty($availableGroups)): ?>
                        <div class="empty-state text-center py-5">
                            <i class="bi bi-search display-1 text-muted mb-3"></i>
                            <h5>No available groups</h5>
                            <p class="text-muted">All study groups are full or you're already a member of all available groups.</p>
                        </div>
                    <?php else: ?>
                        <div class="row">
                            <?php foreach ($availableGroups as $group): ?>
                                <div class="col-lg-4 col-md-6 mb-4">
                                    <div class="card study-group-card h-100">
                                        <div class="card-header">
                                            <h5 class="mb-0"><?php echo htmlspecialchars($group['name']); ?></h5>
                                        </div>
                                        <div class="card-body">
                                            <p class="card-text"><?php echo htmlspecialchars($group['description']); ?></p>
                                            <div class="group-meta mb-3">
                                                <div class="meta-item">
                                                    <i class="bi bi-book me-2"></i>
                                                    <span><?php echo htmlspecialchars($group['subject']); ?></span>
                                                </div>
                                                <div class="meta-item">
                                                    <i class="bi bi-people me-2"></i>
                                                    <span><?php echo $group['member_count']; ?>/<?php echo $group['max_members']; ?> members</span>
                                                </div>
                                                <div class="meta-item">
                                                    <i class="bi bi-person me-2"></i>
                                                    <span>Created by <?php echo htmlspecialchars($group['creator_name']); ?></span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="card-footer">
                                            <button class="btn btn-primary w-100" onclick="joinGroup(<?php echo $group['id']; ?>)">
                                                <i class="bi bi-plus-circle me-1"></i>Join Group
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <!-- Create Group Modal -->
    <div class="modal fade" id="createGroupModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Create Study Group</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="create_group">
                        <div class="mb-3">
                            <label for="name" class="form-label">Group Name</label>
                            <input type="text" class="form-control" id="name" name="name" required>
                        </div>
                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="3" required></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="subject" class="form-label">Subject</label>
                            <select class="form-select" id="subject" name="subject" required>
                                <option value="">Select a subject</option>
                                <?php foreach (getSubjects() as $subject): ?>
                                    <option value="<?php echo htmlspecialchars($subject); ?>"><?php echo htmlspecialchars($subject); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="max_members" class="form-label">Maximum Members</label>
                            <select class="form-select" id="max_members" name="max_members">
                                <option value="5">5 members</option>
                                <option value="10" selected>10 members</option>
                                <option value="15">15 members</option>
                                <option value="20">20 members</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Create Group</button>
                    </div>
                </form>
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
    <!-- Custom JS -->
    <script src="assets/js/main.js"></script>
    <script src="assets/js/theme.js"></script>
    <script>
        function joinGroup(groupId) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="action" value="join_group">
                <input type="hidden" name="group_id" value="${groupId}">
            `;
            document.body.appendChild(form);
            form.submit();
        }
        
        function leaveGroup(groupId) {
            if (confirm('Are you sure you want to leave this study group?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="leave_group">
                    <input type="hidden" name="group_id" value="${groupId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        function viewGroupDetails(groupId) {
            // This would open a detailed view of the group
            alert('Group details feature coming soon!');
        }
    </script>
    
    <style>
        .study-group-card {
            transition: all 0.3s ease;
            border: none;
            box-shadow: var(--shadow-md);
        }
        
        .study-group-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
        }
        
        .group-meta {
            font-size: 0.9rem;
            color: var(--text-secondary);
        }
        
        .meta-item {
            margin-bottom: 0.5rem;
        }
        
        .meta-item i {
            color: var(--primary-color);
        }
        
        .empty-state {
            background: var(--bg-secondary);
            border-radius: 20px;
            padding: 3rem;
        }
    </style>
</body>
</html>

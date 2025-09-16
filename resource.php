<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

$resourceId = (int)($_GET['id'] ?? 0);

if ($resourceId <= 0) {
    header('HTTP/1.1 404 Not Found');
    die('Resource not found');
}

try {
    // Get resource details
    $stmt = $pdo->prepare("
        SELECT 
            r.*,
            u.name as uploader_name,
            u.university as uploader_university,
            u.created_at as uploader_joined,
            COALESCE(AVG(reviews.rating), 0) as average_rating,
            COUNT(reviews.id) as review_count
        FROM resources r
        LEFT JOIN users u ON r.user_id = u.id
        LEFT JOIN reviews ON r.id = reviews.resource_id
        WHERE r.id = ? AND r.is_flagged = 0
        GROUP BY r.id
    ");
    
    $stmt->execute([$resourceId]);
    $resource = $stmt->fetch();
    
    if (!$resource) {
        header('HTTP/1.1 404 Not Found');
        die('Resource not found');
    }
    
    // Increment view count
    $stmt = $pdo->prepare("UPDATE resources SET view_count = view_count + 1 WHERE id = ?");
    $stmt->execute([$resourceId]);
    
    // Get reviews
    $stmt = $pdo->prepare("
        SELECT 
            reviews.*,
            u.name as reviewer_name,
            u.university as reviewer_university
        FROM reviews
        LEFT JOIN users u ON reviews.user_id = u.id
        WHERE reviews.resource_id = ?
        ORDER BY reviews.created_at DESC
    ");
    
    $stmt->execute([$resourceId]);
    $reviews = $stmt->fetchAll();
    
    // Check if user has already rated this resource
    $userRating = null;
    $userReview = null;
    $isFavorited = false;
    
    if (isset($_SESSION['user_id'])) {
        $stmt = $pdo->prepare("SELECT rating, comment FROM reviews WHERE resource_id = ? AND user_id = ?");
        $stmt->execute([$resourceId, $_SESSION['user_id']]);
        $userReview = $stmt->fetch();
        
        if ($userReview) {
            $userRating = $userReview['rating'];
        }
        
        // Check if favorited
        $stmt = $pdo->prepare("SELECT favorites FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
        
        if ($user && $user['favorites']) {
            $favorites = json_decode($user['favorites'], true);
            $isFavorited = is_array($favorites) && in_array($resourceId, $favorites);
        }
    }
    
} catch (PDOException $e) {
    error_log("Resource detail error: " . $e->getMessage());
    header('HTTP/1.1 500 Internal Server Error');
    die('Error loading resource');
}

$error = '';
$success = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['user_id'])) {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'rate') {
        $rating = (int)($_POST['rating'] ?? 0);
        
        if ($rating >= 1 && $rating <= 5) {
            try {
                if ($userRating) {
                    // Update existing rating
                    $stmt = $pdo->prepare("UPDATE reviews SET rating = ?, updated_at = NOW() WHERE resource_id = ? AND user_id = ?");
                    $stmt->execute([$rating, $resourceId, $_SESSION['user_id']]);
                } else {
                    // Insert new rating
                    $stmt = $pdo->prepare("INSERT INTO reviews (resource_id, user_id, rating, created_at) VALUES (?, ?, ?, NOW())");
                    $stmt->execute([$resourceId, $_SESSION['user_id'], $rating]);
                }
                
                $success = 'Rating submitted successfully!';
                
                // Refresh page to show updated rating
                header('Location: resource.php?id=' . $resourceId);
                exit();
                
            } catch (PDOException $e) {
                $error = 'Failed to submit rating. Please try again.';
                error_log("Rating error: " . $e->getMessage());
            }
        } else {
            $error = 'Please select a valid rating.';
        }
    }
    
    elseif ($action === 'review') {
        $rating = (int)($_POST['rating'] ?? 0);
        $comment = sanitizeInput($_POST['comment'] ?? '');
        
        if ($rating >= 1 && $rating <= 5 && !empty($comment)) {
            try {
                if ($userReview) {
                    // Update existing review
                    $stmt = $pdo->prepare("UPDATE reviews SET rating = ?, comment = ?, updated_at = NOW() WHERE resource_id = ? AND user_id = ?");
                    $stmt->execute([$rating, $comment, $resourceId, $_SESSION['user_id']]);
                } else {
                    // Insert new review
                    $stmt = $pdo->prepare("INSERT INTO reviews (resource_id, user_id, rating, comment, created_at) VALUES (?, ?, ?, ?, NOW())");
                    $stmt->execute([$resourceId, $_SESSION['user_id'], $rating, $comment]);
                }
                
                $success = 'Review submitted successfully!';
                
                // Refresh page to show updated review
                header('Location: resource.php?id=' . $resourceId);
                exit();
                
            } catch (PDOException $e) {
                $error = 'Failed to submit review. Please try again.';
                error_log("Review error: " . $e->getMessage());
            }
        } else {
            $error = 'Please provide both a rating and comment.';
        }
    }
    
    elseif ($action === 'favorite') {
        try {
            $stmt = $pdo->prepare("SELECT favorites FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch();
            
            $favorites = $user['favorites'] ? json_decode($user['favorites'], true) : [];
            if (!is_array($favorites)) {
                $favorites = [];
            }
            
            if ($isFavorited) {
                // Remove from favorites
                $favorites = array_diff($favorites, [$resourceId]);
                $stmt = $pdo->prepare("UPDATE resources SET favorites_count = favorites_count - 1 WHERE id = ?");
                $stmt->execute([$resourceId]);
            } else {
                // Add to favorites
                $favorites[] = $resourceId;
                $stmt = $pdo->prepare("UPDATE resources SET favorites_count = favorites_count + 1 WHERE id = ?");
                $stmt->execute([$resourceId]);
            }
            
            $stmt = $pdo->prepare("UPDATE users SET favorites = ? WHERE id = ?");
            $stmt->execute([json_encode($favorites), $_SESSION['user_id']]);
            
            $success = $isFavorited ? 'Removed from favorites!' : 'Added to favorites!';
            
            // Refresh page
            header('Location: resource.php?id=' . $resourceId);
            exit();
            
        } catch (PDOException $e) {
            $error = 'Failed to update favorites. Please try again.';
            error_log("Favorite error: " . $e->getMessage());
        }
    }
    
    elseif ($action === 'report') {
        $reason = sanitizeInput($_POST['reason'] ?? '');
        $description = sanitizeInput($_POST['description'] ?? '');
        
        if (!empty($reason)) {
            try {
                $stmt = $pdo->prepare("INSERT INTO reports (resource_id, user_id, reason, description, created_at) VALUES (?, ?, ?, ?, NOW())");
                $stmt->execute([$resourceId, $_SESSION['user_id'], $reason, $description]);
                
                // Mark resource as flagged
                $stmt = $pdo->prepare("UPDATE resources SET is_flagged = 1 WHERE id = ?");
                $stmt->execute([$resourceId]);
                
                $success = 'Report submitted successfully. Thank you for helping maintain quality!';
                
            } catch (PDOException $e) {
                $error = 'Failed to submit report. Please try again.';
                error_log("Report error: " . $e->getMessage());
            }
        } else {
            $error = 'Please select a reason for reporting.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($resource['title']); ?> - PeerNotes</title>
    <meta name="description" content="<?php echo htmlspecialchars(substr($resource['description'], 0, 160)); ?>">
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <!-- PDF.js for PDF preview -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
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

    <!-- Resource Detail -->
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
                <!-- Resource Info -->
                <div class="col-lg-8">
                    <div class="resource-detail-card">
                        <div class="card-header">
                            <div class="d-flex align-items-center mb-3">
                                <div class="resource-icon me-3">
                                    <i class="<?php echo getFileIcon($resource['file_type']); ?>"></i>
                                </div>
                                <div>
                                    <h1 class="resource-title"><?php echo htmlspecialchars($resource['title']); ?></h1>
                                    <div class="resource-meta">
                                        <span class="badge bg-primary me-2"><?php echo htmlspecialchars($resource['subject']); ?></span>
                                        <span class="badge bg-secondary me-2"><?php echo htmlspecialchars($resource['course']); ?></span>
                                        <span class="badge bg-info"><?php echo htmlspecialchars($resource['academic_year']); ?></span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="resource-stats">
                                <div class="stat-item">
                                    <i class="bi bi-download"></i>
                                    <span><?php echo number_format($resource['download_count']); ?> downloads</span>
                                </div>
                                <div class="stat-item">
                                    <i class="bi bi-eye"></i>
                                    <span><?php echo number_format($resource['view_count']); ?> views</span>
                                </div>
                                <div class="stat-item">
                                    <i class="bi bi-heart"></i>
                                    <span><?php echo number_format($resource['favorites_count']); ?> favorites</span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="card-body">
                            <div class="resource-description">
                                <h5>Description</h5>
                                <p><?php echo nl2br(htmlspecialchars($resource['description'])); ?></p>
                            </div>
                            
                            <div class="resource-actions mt-4">
                                <a href="download.php?id=<?php echo $resource['id']; ?>" class="btn btn-primary btn-lg">
                                    <i class="bi bi-download me-2"></i>Download
                                </a>
                                
                                <?php if (isset($_SESSION['user_id'])): ?>
                                    <button class="btn btn-outline-danger btn-lg ms-2" onclick="toggleFavorite()">
                                        <i class="bi bi-heart<?php echo $isFavorited ? '-fill' : ''; ?> me-2"></i>
                                        <?php echo $isFavorited ? 'Favorited' : 'Add to Favorites'; ?>
                                    </button>
                                    
                                    <button class="btn btn-outline-warning btn-lg ms-2" data-bs-toggle="modal" data-bs-target="#reportModal">
                                        <i class="bi bi-flag me-2"></i>Report
                                    </button>
                                <?php else: ?>
                                    <a href="login.php" class="btn btn-outline-primary btn-lg ms-2">
                                        <i class="bi bi-heart me-2"></i>Login to Favorite
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Reviews Section -->
                    <div class="reviews-section mt-4">
                        <div class="reviews-header">
                            <h4>Reviews & Ratings</h4>
                            <div class="rating-summary">
                                <div class="rating-stars-large">
                                    <?php echo generateStars($resource['average_rating']); ?>
                                </div>
                                <span class="rating-text">
                                    <?php echo number_format($resource['average_rating'], 1); ?> out of 5 
                                    (<?php echo $resource['review_count']; ?> review<?php echo $resource['review_count'] !== 1 ? 's' : ''; ?>)
                                </span>
                            </div>
                        </div>
                        
                        <?php if (isset($_SESSION['user_id'])): ?>
                            <div class="review-form mt-4">
                                <h5><?php echo $userRating ? 'Update Your Review' : 'Write a Review'; ?></h5>
                                <form method="POST">
                                    <input type="hidden" name="action" value="review">
                                    <div class="mb-3">
                                        <label class="form-label">Rating</label>
                                        <div class="rating-input">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <input type="radio" name="rating" value="<?php echo $i; ?>" id="rating<?php echo $i; ?>" 
                                                       <?php echo $userRating == $i ? 'checked' : ''; ?>>
                                                <label for="rating<?php echo $i; ?>">
                                                    <i class="bi bi-star"></i>
                                                </label>
                                            <?php endfor; ?>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label for="comment" class="form-label">Comment</label>
                                        <textarea class="form-control" id="comment" name="comment" rows="4" 
                                                  placeholder="Share your thoughts about this resource..."><?php echo htmlspecialchars($userReview['comment'] ?? ''); ?></textarea>
                                    </div>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-send me-2"></i><?php echo $userRating ? 'Update Review' : 'Submit Review'; ?>
                                    </button>
                                </form>
                            </div>
                        <?php endif; ?>
                        
                        <div class="reviews-list mt-4">
                            <?php if (empty($reviews)): ?>
                                <div class="text-center py-4">
                                    <i class="bi bi-chat-dots display-4 text-muted"></i>
                                    <p class="text-muted mt-2">No reviews yet. Be the first to review!</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($reviews as $review): ?>
                                    <div class="review-item">
                                        <div class="review-header">
                                            <div class="reviewer-info">
                                                <strong><?php echo htmlspecialchars($review['reviewer_name']); ?></strong>
                                                <?php if ($review['reviewer_university']): ?>
                                                    <span class="text-muted">• <?php echo htmlspecialchars($review['reviewer_university']); ?></span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="review-rating">
                                                <?php echo generateStars($review['rating']); ?>
                                                <span class="text-muted ms-2"><?php echo timeAgo($review['created_at']); ?></span>
                                            </div>
                                        </div>
                                        <?php if ($review['comment']): ?>
                                            <div class="review-comment">
                                                <p><?php echo nl2br(htmlspecialchars($review['comment'])); ?></p>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Sidebar -->
                <div class="col-lg-4">
                    <div class="sidebar">
                        <!-- Uploader Info -->
                        <div class="sidebar-card">
                            <h5>Uploaded by</h5>
                            <div class="uploader-info">
                                <div class="uploader-name">
                                    <strong><?php echo htmlspecialchars($resource['uploader_name']); ?></strong>
                                </div>
                                <?php if ($resource['uploader_university']): ?>
                                    <div class="uploader-university">
                                        <i class="bi bi-building me-1"></i>
                                        <?php echo htmlspecialchars($resource['uploader_university']); ?>
                                    </div>
                                <?php endif; ?>
                                <div class="uploader-joined">
                                    <i class="bi bi-calendar me-1"></i>
                                    Joined <?php echo timeAgo($resource['uploader_joined']); ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- File Info -->
                        <div class="sidebar-card">
                            <h5>File Information</h5>
                            <div class="file-info">
                                <div class="file-item">
                                    <i class="bi bi-file-earmark me-2"></i>
                                    <span>Type: <?php echo strtoupper($resource['file_type']); ?></span>
                                </div>
                                <div class="file-item">
                                    <i class="bi bi-hdd me-2"></i>
                                    <span>Size: <?php echo formatFileSize($resource['file_size']); ?></span>
                                </div>
                                <div class="file-item">
                                    <i class="bi bi-calendar me-2"></i>
                                    <span>Uploaded: <?php echo timeAgo($resource['upload_date']); ?></span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- PDF Preview -->
                        <?php if ($resource['file_type'] === 'pdf'): ?>
                            <div class="sidebar-card">
                                <h5>Preview</h5>
                                <div class="pdf-preview" id="pdfPreview">
                                    <div class="pdf-loading">
                                        <div class="spinner-border text-primary" role="status">
                                            <span class="visually-hidden">Loading...</span>
                                        </div>
                                        <p class="mt-2">Loading preview...</p>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Report Modal -->
    <?php if (isset($_SESSION['user_id'])): ?>
    <div class="modal fade" id="reportModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Report Resource</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="report">
                        <div class="mb-3">
                            <label for="reason" class="form-label">Reason for reporting</label>
                            <select class="form-select" id="reason" name="reason" required>
                                <option value="">Select a reason</option>
                                <option value="inappropriate">Inappropriate content</option>
                                <option value="spam">Spam or irrelevant</option>
                                <option value="copyright">Copyright violation</option>
                                <option value="offensive">Offensive or harmful</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="description" class="form-label">Additional details (optional)</label>
                            <textarea class="form-control" id="description" name="description" rows="3" 
                                      placeholder="Please provide more details about the issue..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-warning">Submit Report</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

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
    <script>
        // PDF Preview
        <?php if ($resource['file_type'] === 'pdf'): ?>
        document.addEventListener('DOMContentLoaded', function() {
            const pdfPreview = document.getElementById('pdfPreview');
            const pdfUrl = '<?php echo htmlspecialchars($resource['file_path']); ?>';
            
            // Initialize PDF.js
            pdfjsLib.getDocument(pdfUrl).promise.then(function(pdf) {
                pdf.getPage(1).then(function(page) {
                    const scale = 0.5;
                    const viewport = page.getViewport({scale: scale});
                    
                    const canvas = document.createElement('canvas');
                    const context = canvas.getContext('2d');
                    canvas.height = viewport.height;
                    canvas.width = viewport.width;
                    
                    const renderContext = {
                        canvasContext: context,
                        viewport: viewport
                    };
                    
                    page.render(renderContext).promise.then(function() {
                        pdfPreview.innerHTML = '';
                        pdfPreview.appendChild(canvas);
                    });
                });
            }).catch(function(error) {
                pdfPreview.innerHTML = '<p class="text-muted">Preview not available</p>';
            });
        });
        <?php endif; ?>
        
        // Toggle favorite
        function toggleFavorite() {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = '<input type="hidden" name="action" value="favorite">';
            document.body.appendChild(form);
            form.submit();
        }
        
        // Rating input interaction
        document.addEventListener('DOMContentLoaded', function() {
            const ratingInputs = document.querySelectorAll('.rating-input input[type="radio"]');
            const ratingLabels = document.querySelectorAll('.rating-input label');
            
            ratingInputs.forEach((input, index) => {
                input.addEventListener('change', function() {
                    ratingLabels.forEach((label, labelIndex) => {
                        if (labelIndex <= index) {
                            label.classList.add('active');
                        } else {
                            label.classList.remove('active');
                        }
                    });
                });
                
                // Hover effect
                input.addEventListener('mouseenter', function() {
                    ratingLabels.forEach((label, labelIndex) => {
                        if (labelIndex <= index) {
                            label.classList.add('hover');
                        } else {
                            label.classList.remove('hover');
                        }
                    });
                });
            });
            
            // Remove hover effect when mouse leaves
            document.querySelector('.rating-input').addEventListener('mouseleave', function() {
                ratingLabels.forEach(label => {
                    label.classList.remove('hover');
                });
            });
        });
    </script>
    
    <style>
        .resource-detail-card {
            background: var(--bg-primary);
            border-radius: 20px;
            box-shadow: var(--shadow-lg);
            border: 1px solid var(--border-color);
            overflow: hidden;
        }
        
        .card-header {
            background: var(--gradient-primary);
            color: white;
            padding: 2rem;
        }
        
        .resource-icon {
            font-size: 3rem;
            opacity: 0.9;
        }
        
        .resource-title {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 1rem;
        }
        
        .resource-meta .badge {
            font-size: 0.9rem;
            padding: 0.5rem 1rem;
        }
        
        .resource-stats {
            display: flex;
            gap: 2rem;
            margin-top: 1rem;
        }
        
        .stat-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            opacity: 0.9;
        }
        
        .card-body {
            padding: 2rem;
        }
        
        .resource-description h5 {
            color: var(--text-primary);
            font-weight: 600;
            margin-bottom: 1rem;
        }
        
        .resource-actions {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }
        
        .reviews-section {
            background: var(--bg-primary);
            border-radius: 20px;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border-color);
            padding: 2rem;
        }
        
        .reviews-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--border-color);
        }
        
        .rating-summary {
            text-align: right;
        }
        
        .rating-stars-large {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
        }
        
        .rating-text {
            color: var(--text-secondary);
            font-size: 0.9rem;
        }
        
        .review-form {
            background: var(--bg-secondary);
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .rating-input {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }
        
        .rating-input input[type="radio"] {
            display: none;
        }
        
        .rating-input label {
            font-size: 1.5rem;
            color: var(--text-muted);
            cursor: pointer;
            transition: color 0.2s ease;
        }
        
        .rating-input label:hover,
        .rating-input label.hover {
            color: var(--warning-color);
        }
        
        .rating-input label.active {
            color: var(--warning-color);
        }
        
        .review-item {
            padding: 1.5rem 0;
            border-bottom: 1px solid var(--border-color);
        }
        
        .review-item:last-child {
            border-bottom: none;
        }
        
        .review-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.5rem;
        }
        
        .reviewer-info {
            color: var(--text-primary);
        }
        
        .review-rating {
            display: flex;
            align-items: center;
        }
        
        .review-comment {
            color: var(--text-secondary);
        }
        
        .sidebar {
            position: sticky;
            top: 100px;
        }
        
        .sidebar-card {
            background: var(--bg-primary);
            border-radius: 15px;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border-color);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        .sidebar-card h5 {
            color: var(--text-primary);
            font-weight: 600;
            margin-bottom: 1rem;
        }
        
        .uploader-info,
        .file-info {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
        
        .file-item {
            display: flex;
            align-items: center;
            color: var(--text-secondary);
        }
        
        .pdf-preview {
            text-align: center;
            background: var(--bg-secondary);
            border-radius: 10px;
            padding: 1rem;
            min-height: 200px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .pdf-loading {
            display: flex;
            flex-direction: column;
            align-items: center;
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
            .resource-stats {
                flex-direction: column;
                gap: 1rem;
            }
            
            .resource-actions {
                flex-direction: column;
            }
            
            .reviews-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }
            
            .rating-summary {
                text-align: left;
            }
            
            .sidebar {
                position: static;
                margin-top: 2rem;
            }
        }
    </style>
</body>
</html>

<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

$query = sanitizeInput($_GET['query'] ?? '');
$subject = sanitizeInput($_GET['subject'] ?? '');
$academicYear = sanitizeInput($_GET['academic_year'] ?? '');
$fileType = sanitizeInput($_GET['file_type'] ?? '');
$sortBy = sanitizeInput($_GET['sort'] ?? 'relevance');
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 12;
$offset = ($page - 1) * $limit;

$resources = [];
$totalResults = 0;
$totalPages = 0;

try {
    // Build search query
    $whereConditions = ["r.is_flagged = 0"];
    $params = [];
    
    if (!empty($query)) {
        $whereConditions[] = "(r.title LIKE ? OR r.description LIKE ? OR r.subject LIKE ? OR r.course LIKE ?)";
        $searchTerm = "%$query%";
        $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
    }
    
    if (!empty($subject)) {
        $whereConditions[] = "r.subject = ?";
        $params[] = $subject;
    }
    
    if (!empty($academicYear)) {
        $whereConditions[] = "r.academic_year = ?";
        $params[] = $academicYear;
    }
    
    if (!empty($fileType)) {
        $whereConditions[] = "r.file_type = ?";
        $params[] = $fileType;
    }
        
    $whereClause = implode(' AND ', $whereConditions);
    
    // Determine sort order
    $orderBy = "r.upload_date DESC";
    switch ($sortBy) {
        case 'rating':
            $orderBy = "average_rating DESC, review_count DESC";
            break;
        case 'downloads':
            $orderBy = "r.download_count DESC";
            break;
        case 'recent':
            $orderBy = "r.upload_date DESC";
            break;
        case 'title':
            $orderBy = "r.title ASC";
            break;
    }
    
    // Get total count
    $countQuery = "
        SELECT COUNT(DISTINCT r.id) as total
        FROM resources r
        LEFT JOIN users u ON r.user_id = u.id
        LEFT JOIN reviews ON r.id = reviews.resource_id
        WHERE $whereClause
    ";
    
    $stmt = $pdo->prepare($countQuery);
    $stmt->execute($params);
    $totalResults = $stmt->fetch()['total'] ?? 0;
    $totalPages = ceil($totalResults / $limit);
    
    // Get resources
    $searchQuery = "
        SELECT 
            r.id,
            r.title,
            r.description,
            r.file_type,
            r.file_size,
            r.subject,
            r.course,
            r.academic_year,
            r.upload_date,
            r.download_count,
            r.view_count,
            r.favorites_count,
            u.name as uploader_name,
            u.university as uploader_university,
            COALESCE(AVG(reviews.rating), 0) as average_rating,
            COUNT(reviews.id) as review_count
        FROM resources r
        LEFT JOIN users u ON r.user_id = u.id
        LEFT JOIN reviews ON r.id = reviews.resource_id
        WHERE $whereClause
        GROUP BY r.id
        ORDER BY $orderBy
        LIMIT $limit OFFSET $offset
    ";
    
    $stmt = $pdo->prepare($searchQuery);
    $stmt->execute($params);
    $resources = $stmt->fetchAll();
    
} catch (PDOException $e) {
    error_log("Search error: " . $e->getMessage());
}

// Get filter options
$subjects = getSubjects();
$academicYears = getAcademicYears();
$fileTypes = ['pdf', 'doc', 'docx', 'ppt', 'pptx'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Search Resources - PeerNotes</title>
    <meta name="description" content="Search and discover academic resources shared by students">
    
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
                        <a class="nav-link active" href="search.php">Search</a>
                    </li>
                    <?php if (isset($_SESSION['user_id'])): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="upload.php">Upload</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="profile.php">Profile</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="study-groups.php">Study Groups</a>
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

    <!-- Search Section -->
    <section class="py-5" style="margin-top: 80px;">
        <div class="container">
            <!-- Search Bar -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="search-container">
                        <form method="GET" class="search-form">
                            <div class="input-group input-group-lg">
                                <input type="text" class="form-control search-input" name="query" 
                                       value="<?php echo htmlspecialchars($query); ?>" 
                                       placeholder="Search for notes, papers, presentations..." autocomplete="off">
                                <button class="btn btn-primary search-btn" type="submit">
                                    <i class="bi bi-search"></i>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Filters -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="filters-card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="bi bi-funnel me-2"></i>Filters
                            </h5>
                        </div>
                        <div class="card-body">
                            <form method="GET" id="filterForm">
                                <input type="hidden" name="query" value="<?php echo htmlspecialchars($query); ?>">
                                
                                <div class="row g-3">
                                    <div class="col-md-3">
                                        <label for="subject" class="form-label">Subject</label>
                                        <select class="form-select" id="subject" name="subject">
                                            <option value="">All Subjects</option>
                                            <?php foreach ($subjects as $subjectOption): ?>
                                                <option value="<?php echo htmlspecialchars($subjectOption); ?>" 
                                                        <?php echo $subject === $subjectOption ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($subjectOption); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="col-md-3">
                                        <label for="academic_year" class="form-label">Academic Year</label>
                                        <select class="form-select" id="academic_year" name="academic_year">
                                            <option value="">All Years</option>
                                            <?php foreach ($academicYears as $year): ?>
                                                <option value="<?php echo htmlspecialchars($year); ?>" 
                                                        <?php echo $academicYear === $year ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($year); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="col-md-3">
                                        <label for="file_type" class="form-label">File Type</label>
                                        <select class="form-select" id="file_type" name="file_type">
                                            <option value="">All Types</option>
                                            <?php foreach ($fileTypes as $type): ?>
                                                <option value="<?php echo htmlspecialchars($type); ?>" 
                                                        <?php echo $fileType === $type ? 'selected' : ''; ?>>
                                                    <?php echo strtoupper($type); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="col-md-3">
                                        <label for="sort" class="form-label">Sort By</label>
                                        <select class="form-select" id="sort" name="sort">
                                            <option value="relevance" <?php echo $sortBy === 'relevance' ? 'selected' : ''; ?>>Relevance</option>
                                            <option value="rating" <?php echo $sortBy === 'rating' ? 'selected' : ''; ?>>Rating</option>
                                            <option value="downloads" <?php echo $sortBy === 'downloads' ? 'selected' : ''; ?>>Downloads</option>
                                            <option value="recent" <?php echo $sortBy === 'recent' ? 'selected' : ''; ?>>Most Recent</option>
                                            <option value="title" <?php echo $sortBy === 'title' ? 'selected' : ''; ?>>Title A-Z</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="row mt-3">
                                    <div class="col-12">
                                        <button type="submit" class="btn btn-primary me-2">
                                            <i class="bi bi-funnel me-2"></i>Apply Filters
                                        </button>
                                        <a href="search.php" class="btn btn-outline-secondary">
                                            <i class="bi bi-x-circle me-2"></i>Clear Filters
                                        </a>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Results -->
            <div class="row">
                <div class="col-12">
                    <?php if ($totalResults > 0 || !empty($query) || !empty($subject) || !empty($academicYear) || !empty($fileType)): ?>
                        <div class="results-header mb-4">
                            <h4>
                                <?php if ($totalResults > 0): ?>
                                    Found <?php echo number_format($totalResults); ?> resource<?php echo $totalResults !== 1 ? 's' : ''; ?>
                                    <?php if (!empty($query)): ?>
                                        for "<?php echo htmlspecialchars($query); ?>"
                                    <?php endif; ?>
                                <?php else: ?>
                                    No resources found
                                    <?php if (!empty($query)): ?>
                                        for "<?php echo htmlspecialchars($query); ?>"
                                    <?php endif; ?>
                                <?php endif; ?>
                            </h4>
                        </div>
                        
                        <?php if ($totalResults > 0): ?>
                            <div class="row" id="search-results">
                                <?php foreach ($resources as $resource): ?>
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
                            
                            <!-- Pagination -->
                            <?php if ($totalPages > 1): ?>
                                <nav aria-label="Search results pagination" class="mt-4">
                                    <ul class="pagination justify-content-center">
                                        <?php if ($page > 1): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">
                                                    <i class="bi bi-chevron-left"></i>
                                                </a>
                                            </li>
                                        <?php endif; ?>
                                        
                                        <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                                            <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                                <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>">
                                                    <?php echo $i; ?>
                                                </a>
                                            </li>
                                        <?php endfor; ?>
                                        
                                        <?php if ($page < $totalPages): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">
                                                    <i class="bi bi-chevron-right"></i>
                                                </a>
                                            </li>
                                        <?php endif; ?>
                                    </ul>
                                </nav>
                            <?php endif; ?>
                            
                        <?php else: ?>
                            <div class="empty-state text-center py-5">
                                <i class="bi bi-search display-1 text-muted mb-3"></i>
                                <h4>No resources found</h4>
                                <p class="text-muted mb-4">Try adjusting your search terms or filters</p>
                                <?php if (isset($_SESSION['user_id'])): ?>
                                    <a href="upload.php" class="btn btn-primary">
                                        <i class="bi bi-cloud-upload me-2"></i>Upload a Resource
                                    </a>
                                <?php else: ?>
                                    <a href="register.php" class="btn btn-primary">
                                        <i class="bi bi-person-plus me-2"></i>Join PeerNotes
                                    </a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        
                    <?php else: ?>
                        <div class="empty-state text-center py-5">
                            <i class="bi bi-search display-1 text-muted mb-3"></i>
                            <h4>Search Academic Resources</h4>
                            <p class="text-muted mb-4">Use the search bar above to find notes, papers, and presentations</p>
                            <div class="search-suggestions">
                                <h6>Popular searches:</h6>
                                <div class="d-flex flex-wrap gap-2 justify-content-center">
                                    <a href="?query=computer science" class="btn btn-outline-primary btn-sm">Computer Science</a>
                                    <a href="?query=mathematics" class="btn btn-outline-primary btn-sm">Mathematics</a>
                                    <a href="?query=physics" class="btn btn-outline-primary btn-sm">Physics</a>
                                    <a href="?query=engineering" class="btn btn-outline-primary btn-sm">Engineering</a>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
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
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize search autocomplete
            const searchInput = document.querySelector('.search-input');
            if (searchInput) {
                new SearchAutocomplete(searchInput);
            }
            
            // Auto-submit filters on change
            const filterForm = document.getElementById('filterForm');
            const filterSelects = filterForm.querySelectorAll('select');
            
            filterSelects.forEach(select => {
                select.addEventListener('change', function() {
                    filterForm.submit();
                });
            });
        });
        
    </script>
    
    <style>
        .search-container {
            max-width: 800px;
            margin: 0 auto;
        }
        
        .search-form .input-group {
            box-shadow: var(--shadow-lg);
            border-radius: 50px;
            overflow: hidden;
            backdrop-filter: blur(10px);
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .search-input {
            border: none;
            background: transparent;
            color: var(--text-primary);
            font-size: 1.1rem;
            padding: 1rem 1.5rem;
        }
        
        .search-input:focus {
            box-shadow: none;
            background: rgba(255, 255, 255, 0.1);
        }
        
        .search-btn {
            border: none;
            background: var(--gradient-primary);
            padding: 1rem 1.5rem;
            transition: all 0.3s ease;
        }
        
        .search-btn:hover {
            transform: scale(1.05);
            box-shadow: 0 0 20px rgba(79, 70, 229, 0.4);
        }
        
        .filters-card {
            background: var(--bg-primary);
            border-radius: 15px;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border-color);
        }
        
        .filters-card .card-header {
            background: var(--bg-secondary);
            border-bottom: 1px solid var(--border-color);
            padding: 1rem 1.5rem;
        }
        
        .filters-card .card-body {
            padding: 1.5rem;
        }
        
        .results-header h4 {
            color: var(--text-primary);
            font-weight: 600;
        }
        
        .empty-state {
            background: var(--bg-secondary);
            border-radius: 20px;
            padding: 3rem;
        }
        
        .search-suggestions {
            margin-top: 2rem;
        }
        
        .pagination .page-link {
            border-radius: 8px;
            margin: 0 2px;
            border: 1px solid var(--border-color);
            color: var(--text-primary);
        }
        
        .pagination .page-item.active .page-link {
            background: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .pagination .page-link:hover {
            background: var(--bg-secondary);
            border-color: var(--primary-color);
        }
        
        @media (max-width: 768px) {
            .search-container {
                margin: 0 1rem;
            }
            
            .filters-card .card-body {
                padding: 1rem;
            }
            
            .empty-state {
                padding: 2rem 1rem;
            }
        }
    </style>
</body>
</html>

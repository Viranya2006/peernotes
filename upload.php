<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Require login
requireLogin();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = sanitizeInput($_POST['title'] ?? '');
    $description = sanitizeInput($_POST['description'] ?? '');
    $subject = sanitizeInput($_POST['subject'] ?? '');
    $course = sanitizeInput($_POST['course'] ?? '');
    $academicYear = sanitizeInput($_POST['academic_year'] ?? '');
    
    // Validation
    if (empty($title) || empty($description) || empty($subject) || empty($course) || empty($academicYear)) {
        $error = 'All fields are required.';
    } elseif (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        $error = 'Please select a file to upload.';
    } else {
        // Handle file upload
        $uploadResult = uploadFile($_FILES['file']);
        
        if ($uploadResult['success']) {
            try {
                // Insert resource into database
                $stmt = $pdo->prepare("
                    INSERT INTO resources 
                    (user_id, title, description, file_path, file_name, file_type, file_size, subject, course, academic_year, upload_date) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                
                $stmt->execute([
                    $_SESSION['user_id'],
                    $title,
                    $description,
                    $uploadResult['file_path'],
                    $uploadResult['file_name'],
                    $uploadResult['file_type'],
                    $_FILES['file']['size'],
                    $subject,
                    $course,
                    $academicYear
                ]);
                
                $resourceId = $pdo->lastInsertId();
                
                // Log activity
                $stmt = $pdo->prepare("INSERT INTO activity_log (user_id, action, resource_id, ip_address, user_agent) VALUES (?, 'upload', ?, ?, ?)");
                $stmt->execute([$_SESSION['user_id'], $resourceId, $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '']);
                
                $success = 'Resource uploaded successfully!';
                
                // Redirect to resource detail page after 2 seconds
                header('refresh:2;url=resource.php?id=' . $resourceId);
                
            } catch (PDOException $e) {
                $error = 'Failed to save resource information. Please try again.';
                error_log("Upload database error: " . $e->getMessage());
                
                // Clean up uploaded file
                if (file_exists($uploadResult['file_path'])) {
                    unlink($uploadResult['file_path']);
                }
            }
        } else {
            $error = $uploadResult['error'];
        }
    }
}

// Get subjects and academic years for dropdowns
$subjects = getSubjects();
$academicYears = getAcademicYears();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Resource - PeerNotes</title>
    <meta name="description" content="Upload academic resources to share with fellow students">
    
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
                        <a class="nav-link active" href="upload.php">Upload</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="profile.php">Profile</a>
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

    <!-- Upload Form -->
    <section class="py-5" style="margin-top: 80px;">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="upload-card">
                        <div class="card-header text-center">
                            <div class="upload-icon mb-3">
                                <i class="bi bi-cloud-upload-fill"></i>
                            </div>
                            <h2 class="card-title">Upload Resource</h2>
                            <p class="card-subtitle">Share your academic materials with fellow students</p>
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
                            
                            <form method="POST" enctype="multipart/form-data" id="uploadForm" novalidate>
                                <!-- File Upload -->
                                <div class="mb-4">
                                    <label class="form-label">
                                        <i class="bi bi-file-earmark me-2"></i>Select File
                                    </label>
                                    <div class="upload-container">
                                        <input type="file" class="form-control" id="file" name="file" 
                                               accept=".pdf,.doc,.docx,.ppt,.pptx" required>
                                        <div class="file-info mt-2" id="fileInfo" style="display: none;">
                                            <div class="file-preview">
                                                <i class="bi bi-file-earmark"></i>
                                                <span class="file-name"></span>
                                                <span class="file-size"></span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="form-text">Supported formats: PDF, DOC, DOCX, PPT, PPTX (Max 10MB)</div>
                                    <div class="invalid-feedback"></div>
                                </div>
                                
                                <!-- Title -->
                                <div class="mb-3">
                                    <label for="title" class="form-label">
                                        <i class="bi bi-tag me-2"></i>Resource Title
                                    </label>
                                    <input type="text" class="form-control" id="title" name="title" 
                                           value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>" 
                                           required placeholder="Enter a descriptive title">
                                    <div class="invalid-feedback"></div>
                                </div>
                                
                                <!-- Description -->
                                <div class="mb-3">
                                    <label for="description" class="form-label">
                                        <i class="bi bi-text-paragraph me-2"></i>Description
                                    </label>
                                    <textarea class="form-control" id="description" name="description" rows="4" 
                                              required placeholder="Describe the content and topics covered"><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                                    <div class="form-text">Provide a detailed description to help others find your resource</div>
                                    <div class="invalid-feedback"></div>
                                </div>
                                
                                <!-- Subject -->
                                <div class="mb-3">
                                    <label for="subject" class="form-label">
                                        <i class="bi bi-book me-2"></i>Subject
                                    </label>
                                    <select class="form-select" id="subject" name="subject" required>
                                        <option value="">Select a subject</option>
                                        <?php foreach ($subjects as $subject): ?>
                                            <option value="<?php echo htmlspecialchars($subject); ?>" 
                                                    <?php echo ($_POST['subject'] ?? '') === $subject ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($subject); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="invalid-feedback"></div>
                                </div>
                                
                                <!-- Course -->
                                <div class="mb-3">
                                    <label for="course" class="form-label">
                                        <i class="bi bi-mortarboard me-2"></i>Course Code/Name
                                    </label>
                                    <input type="text" class="form-control" id="course" name="course" 
                                           value="<?php echo htmlspecialchars($_POST['course'] ?? ''); ?>" 
                                           required placeholder="e.g., CS101, Mathematics I">
                                    <div class="invalid-feedback"></div>
                                </div>
                                
                                <!-- Academic Year -->
                                <div class="mb-4">
                                    <label for="academic_year" class="form-label">
                                        <i class="bi bi-calendar me-2"></i>Academic Year
                                    </label>
                                    <select class="form-select" id="academic_year" name="academic_year" required>
                                        <option value="">Select academic year</option>
                                        <?php foreach ($academicYears as $year): ?>
                                            <option value="<?php echo htmlspecialchars($year); ?>" 
                                                    <?php echo ($_POST['academic_year'] ?? '') === $year ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($year); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="invalid-feedback"></div>
                                </div>
                                
                                <div class="d-grid gap-2">
                                    <button type="submit" class="btn btn-primary btn-lg">
                                        <i class="bi bi-cloud-upload me-2"></i>Upload Resource
                                    </button>
                                    <a href="profile.php" class="btn btn-outline-secondary">
                                        <i class="bi bi-arrow-left me-2"></i>Cancel
                                    </a>
                                </div>
                            </form>
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
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('uploadForm');
            const fileInput = document.getElementById('file');
            const fileInfo = document.getElementById('fileInfo');
            
            // Form validation
            const validator = new FormValidator(form);
            
            validator.addRule('title', {
                validator: (value) => value.length >= 3,
                message: 'Title must be at least 3 characters long'
            });
            
            validator.addRule('description', {
                validator: (value) => value.length >= 10,
                message: 'Description must be at least 10 characters long'
            });
            
            validator.addRule('course', {
                validator: (value) => value.length >= 2,
                message: 'Course code/name must be at least 2 characters long'
            });
            
            // File input handler
            fileInput.addEventListener('change', function() {
                const file = this.files[0];
                if (file) {
                    const fileName = file.name;
                    const fileSize = Utils.formatFileSize(file.size);
                    const fileType = file.type;
                    
                    fileInfo.innerHTML = `
                        <div class="file-preview">
                            <i class="bi bi-file-earmark-${getFileIcon(fileType)}"></i>
                            <div class="file-details">
                                <span class="file-name">${fileName}</span>
                                <span class="file-size">${fileSize}</span>
                            </div>
                        </div>
                    `;
                    fileInfo.style.display = 'block';
                } else {
                    fileInfo.style.display = 'none';
                }
            });
            
            // Form submission with loading state
            form.addEventListener('submit', function() {
                const submitBtn = form.querySelector('button[type="submit"]');
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Uploading...';
            });
        });
        
        function getFileIcon(fileType) {
            if (fileType.includes('pdf')) return 'pdf text-danger';
            if (fileType.includes('word')) return 'word text-primary';
            if (fileType.includes('presentation') || fileType.includes('powerpoint')) return 'ppt text-warning';
            return 'text-secondary';
        }
    </script>
    
    <style>
        .upload-card {
            background: var(--bg-primary);
            border-radius: 20px;
            box-shadow: var(--shadow-xl);
            border: 1px solid var(--border-color);
            overflow: hidden;
        }
        
        .card-header {
            background: var(--gradient-primary);
            color: white;
            padding: 2rem;
        }
        
        .upload-icon {
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
        
        .upload-container {
            position: relative;
        }
        
        .file-info {
            background: var(--bg-secondary);
            border: 2px dashed var(--border-color);
            border-radius: 12px;
            padding: 1rem;
        }
        
        .file-preview {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .file-preview i {
            font-size: 2rem;
        }
        
        .file-details {
            display: flex;
            flex-direction: column;
        }
        
        .file-name {
            font-weight: 600;
            color: var(--text-primary);
        }
        
        .file-size {
            font-size: 0.9rem;
            color: var(--text-secondary);
        }
        
        .form-select:focus,
        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(79, 70, 229, 0.25);
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
            .upload-card {
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

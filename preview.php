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
    $stmt = $pdo->prepare("SELECT * FROM resources WHERE id = ? AND is_flagged = 0");
    $stmt->execute([$resourceId]);
    $resource = $stmt->fetch();
    
    if (!$resource) {
        header('HTTP/1.1 404 Not Found');
        die('Resource not found');
    }
    
    // Check if file exists
    if (!file_exists($resource['file_path'])) {
        header('HTTP/1.1 404 Not Found');
        die('File not found');
    }
    
    $fileExtension = strtolower(pathinfo($resource['file_name'], PATHINFO_EXTENSION));
    $filePath = $resource['file_path'];
    
} catch (PDOException $e) {
    error_log("Preview error: " . $e->getMessage());
    header('HTTP/1.1 500 Internal Server Error');
    die('Error loading preview');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Preview - <?php echo htmlspecialchars($resource['title']); ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    
    <?php if ($fileExtension === 'pdf'): ?>
    <!-- PDF.js for PDF preview -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
    <?php endif; ?>
    
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: #f8f9fa;
            margin: 0;
            padding: 0;
        }
        
        .preview-header {
            background: white;
            border-bottom: 1px solid #dee2e6;
            padding: 1rem 0;
            position: sticky;
            top: 0;
            z-index: 1000;
        }
        
        .preview-content {
            padding: 2rem 0;
            min-height: calc(100vh - 200px);
        }
        
        .preview-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .preview-header-content {
            padding: 1rem 2rem;
            border-bottom: 1px solid #dee2e6;
        }
        
        .preview-body {
            padding: 0;
            position: relative;
        }
        
        .pdf-viewer {
            width: 100%;
            height: 70vh;
            border: none;
        }
        
        .image-preview {
            width: 100%;
            max-height: 70vh;
            object-fit: contain;
        }
        
        .text-preview {
            padding: 2rem;
            max-height: 70vh;
            overflow-y: auto;
            font-family: 'Courier New', monospace;
            font-size: 14px;
            line-height: 1.6;
        }
        
        .unsupported-preview {
            padding: 3rem;
            text-align: center;
            color: #6c757d;
        }
        
        .preview-actions {
            position: fixed;
            bottom: 20px;
            right: 20px;
            display: flex;
            gap: 10px;
            z-index: 1000;
        }
        
        .btn-floating {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            border: none;
            transition: all 0.3s ease;
        }
        
        .btn-floating:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.2);
        }
        
        .loading-spinner {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 70vh;
            color: #6c757d;
        }
        
        .file-info {
            background: #f8f9fa;
            padding: 1rem 2rem;
            border-top: 1px solid #dee2e6;
        }
        
        .file-info-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.5rem;
        }
        
        .file-info-item:last-child {
            margin-bottom: 0;
        }
    </style>
</head>
<body>
    <!-- Preview Header -->
    <div class="preview-header">
        <div class="container">
            <div class="d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center">
                    <i class="<?php echo getFileIcon($fileExtension); ?> me-3" style="font-size: 2rem;"></i>
                    <div>
                        <h4 class="mb-0"><?php echo htmlspecialchars($resource['title']); ?></h4>
                        <small class="text-muted">
                            <?php echo htmlspecialchars($resource['subject']); ?> • 
                            <?php echo htmlspecialchars($resource['course']); ?> • 
                            <?php echo strtoupper($fileExtension); ?>
                        </small>
                    </div>
                </div>
                <div>
                    <a href="resource.php?id=<?php echo $resource['id']; ?>" class="btn btn-outline-primary me-2">
                        <i class="bi bi-arrow-left me-1"></i>Back to Resource
                    </a>
                    <a href="download.php?id=<?php echo $resource['id']; ?>" class="btn btn-primary">
                        <i class="bi bi-download me-1"></i>Download
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Preview Content -->
    <div class="preview-content">
        <div class="container">
            <div class="preview-container">
                <div class="preview-body">
                    <?php if ($fileExtension === 'pdf'): ?>
                        <!-- PDF Preview -->
                        <div id="pdfPreview" class="loading-spinner">
                            <div class="spinner-border text-primary mb-3" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <p>Loading PDF preview...</p>
                        </div>
                        
                    <?php elseif (in_array($fileExtension, ['jpg', 'jpeg', 'png', 'gif', 'webp'])): ?>
                        <!-- Image Preview -->
                        <img src="<?php echo htmlspecialchars($filePath); ?>" alt="<?php echo htmlspecialchars($resource['title']); ?>" class="image-preview">
                        
                    <?php elseif (in_array($fileExtension, ['txt', 'md', 'csv'])): ?>
                        <!-- Text Preview -->
                        <div class="text-preview">
                            <?php 
                            $content = file_get_contents($filePath);
                            echo nl2br(htmlspecialchars($content));
                            ?>
                        </div>
                        
                    <?php else: ?>
                        <!-- Unsupported File Type -->
                        <div class="unsupported-preview">
                            <i class="bi bi-file-earmark display-1 mb-3"></i>
                            <h5>Preview not available</h5>
                            <p>This file type cannot be previewed in the browser.</p>
                            <p class="text-muted">File type: <?php echo strtoupper($fileExtension); ?></p>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- File Information -->
                <div class="file-info">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="file-info-item">
                                <i class="bi bi-file-earmark"></i>
                                <span><strong>File:</strong> <?php echo htmlspecialchars($resource['file_name']); ?></span>
                            </div>
                            <div class="file-info-item">
                                <i class="bi bi-hdd"></i>
                                <span><strong>Size:</strong> <?php echo formatFileSize($resource['file_size']); ?></span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="file-info-item">
                                <i class="bi bi-download"></i>
                                <span><strong>Downloads:</strong> <?php echo number_format($resource['download_count']); ?></span>
                            </div>
                            <div class="file-info-item">
                                <i class="bi bi-calendar"></i>
                                <span><strong>Uploaded:</strong> <?php echo timeAgo($resource['upload_date']); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Floating Action Buttons -->
    <div class="preview-actions">
        <a href="download.php?id=<?php echo $resource['id']; ?>" class="btn btn-primary btn-floating" title="Download">
            <i class="bi bi-download"></i>
        </a>
        <a href="resource.php?id=<?php echo $resource['id']; ?>" class="btn btn-outline-primary btn-floating" title="View Details">
            <i class="bi bi-info-circle"></i>
        </a>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <?php if ($fileExtension === 'pdf'): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const pdfPreview = document.getElementById('pdfPreview');
            const pdfUrl = '<?php echo htmlspecialchars($filePath); ?>';
            
            // Initialize PDF.js
            pdfjsLib.getDocument(pdfUrl).promise.then(function(pdf) {
                // Create iframe for PDF display
                const iframe = document.createElement('iframe');
                iframe.src = pdfUrl + '#toolbar=0&navpanes=0&scrollbar=1';
                iframe.className = 'pdf-viewer';
                iframe.title = '<?php echo htmlspecialchars($resource['title']); ?>';
                
                pdfPreview.innerHTML = '';
                pdfPreview.appendChild(iframe);
                
            }).catch(function(error) {
                pdfPreview.innerHTML = `
                    <div class="unsupported-preview">
                        <i class="bi bi-exclamation-triangle display-1 mb-3"></i>
                        <h5>Preview Error</h5>
                        <p>Unable to load PDF preview.</p>
                        <a href="download.php?id=<?php echo $resource['id']; ?>" class="btn btn-primary">
                            <i class="bi bi-download me-2"></i>Download Instead
                        </a>
                    </div>
                `;
            });
        });
    </script>
    <?php endif; ?>
</body>
</html>

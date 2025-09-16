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
    
    // Increment download count
    $stmt = $pdo->prepare("UPDATE resources SET download_count = download_count + 1 WHERE id = ?");
    $stmt->execute([$resourceId]);
    
    // Log download activity
    if (isset($_SESSION['user_id'])) {
        $stmt = $pdo->prepare("INSERT INTO activity_log (user_id, action, resource_id, ip_address, user_agent) VALUES (?, 'download', ?, ?, ?)");
        $stmt->execute([$_SESSION['user_id'], $resourceId, $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '']);
    }
    
    // Set headers for file download
    $fileName = $resource['file_name'];
    $filePath = $resource['file_path'];
    $fileSize = filesize($filePath);
    
    // Determine MIME type
    $mimeTypes = [
        'pdf' => 'application/pdf',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'ppt' => 'application/vnd.ms-powerpoint',
        'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation'
    ];
    
    $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    $mimeType = $mimeTypes[$fileExtension] ?? 'application/octet-stream';
    
    // Set headers
    header('Content-Type: ' . $mimeType);
    header('Content-Disposition: attachment; filename="' . $fileName . '"');
    header('Content-Length: ' . $fileSize);
    header('Cache-Control: no-cache, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // Output file
    readfile($filePath);
    exit();
    
} catch (PDOException $e) {
    error_log("Download error: " . $e->getMessage());
    header('HTTP/1.1 500 Internal Server Error');
    die('Error processing download');
}
?>

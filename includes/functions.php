<?php
// Helper functions for PeerNotes

function sanitizeInput($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit();
    }
}

function isAdmin($userId) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT is_admin FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        return $user && $user['is_admin'] == 1;
    } catch(PDOException $e) {
        return false;
    }
}

function requireAdmin() {
    requireLogin();
    if (!isAdmin($_SESSION['user_id'])) {
        header('HTTP/1.1 403 Forbidden');
        die('Access denied. Admin privileges required.');
    }
}

function getFileIcon($fileType) {
    switch(strtolower($fileType)) {
        case 'pdf':
            return 'bi-file-earmark-pdf text-danger';
        case 'doc':
        case 'docx':
            return 'bi-file-earmark-word text-primary';
        case 'ppt':
        case 'pptx':
            return 'bi-file-earmark-ppt text-warning';
        default:
            return 'bi-file-earmark text-secondary';
    }
}

function formatFileSize($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' bytes';
    }
}

function timeAgo($datetime) {
    $time = time() - strtotime($datetime);
    
    if ($time < 60) return 'just now';
    if ($time < 3600) return floor($time/60) . ' minutes ago';
    if ($time < 86400) return floor($time/3600) . ' hours ago';
    if ($time < 2592000) return floor($time/86400) . ' days ago';
    if ($time < 31536000) return floor($time/2592000) . ' months ago';
    return floor($time/31536000) . ' years ago';
}

function generateStars($rating, $maxRating = 5) {
    $stars = '';
    for ($i = 1; $i <= $maxRating; $i++) {
        if ($i <= $rating) {
            $stars .= '<i class="bi bi-star-fill text-warning"></i>';
        } elseif ($i - 0.5 <= $rating) {
            $stars .= '<i class="bi bi-star-half text-warning"></i>';
        } else {
            $stars .= '<i class="bi bi-star text-warning"></i>';
        }
    }
    return $stars;
}

function calculateAverageRating($ratingsJson) {
    if (empty($ratingsJson)) return 0;
    
    $ratings = json_decode($ratingsJson, true);
    if (!is_array($ratings) || empty($ratings)) return 0;
    
    $sum = array_sum(array_column($ratings, 'rating'));
    return round($sum / count($ratings), 1);
}

function getSubjectIcon($subject) {
    $icons = [
        'Computer Science' => 'bi-laptop',
        'Mathematics' => 'bi-calculator',
        'Physics' => 'bi-atom',
        'Chemistry' => 'bi-flask',
        'Biology' => 'bi-heart-pulse',
        'Engineering' => 'bi-gear',
        'Business' => 'bi-briefcase',
        'Medicine' => 'bi-hospital',
        'Law' => 'bi-scale',
        'Arts' => 'bi-palette',
        'Literature' => 'bi-book',
        'History' => 'bi-clock-history',
        'Geography' => 'bi-globe',
        'Economics' => 'bi-graph-up',
        'Psychology' => 'bi-person-heart',
        'Sociology' => 'bi-people',
        'Political Science' => 'bi-building',
        'Education' => 'bi-mortarboard',
        'Architecture' => 'bi-building',
        'Agriculture' => 'bi-tree'
    ];
    
    return $icons[$subject] ?? 'bi-book';
}

function uploadFile($file, $uploadDir = 'uploads/') {
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    
    // Ensure directory is writable
    if (!is_writable($uploadDir)) {
        chmod($uploadDir, 0777);
    }
    
    $allowedTypes = ['pdf', 'doc', 'docx', 'ppt', 'pptx'];
    $maxSize = 10 * 1024 * 1024; // 10MB
    
    $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $fileName = uniqid() . '_' . time() . '.' . $fileExtension;
    $filePath = $uploadDir . $fileName;
    
    if (!in_array($fileExtension, $allowedTypes)) {
        return ['success' => false, 'error' => 'Invalid file type. Only PDF, DOC, DOCX, PPT, PPTX files are allowed.'];
    }
    
    if ($file['size'] > $maxSize) {
        return ['success' => false, 'error' => 'File size too large. Maximum size is 10MB.'];
    }
    
    if (move_uploaded_file($file['tmp_name'], $filePath)) {
        return [
            'success' => true, 
            'file_path' => $filePath, 
            'file_name' => $fileName,
            'file_type' => $fileExtension
        ];
    } else {
        return ['success' => false, 'error' => 'Failed to upload file.'];
    }
}

function deleteFile($filePath) {
    if (file_exists($filePath)) {
        return unlink($filePath);
    }
    return true;
}

function sendJsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit();
}

function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

function validatePassword($password) {
    return strlen($password) >= 8;
}

function getSubjects() {
    return [
        'Computer Science',
        'Mathematics',
        'Physics',
        'Chemistry',
        'Biology',
        'Engineering',
        'Business',
        'Medicine',
        'Law',
        'Arts',
        'Literature',
        'History',
        'Geography',
        'Economics',
        'Psychology',
        'Sociology',
        'Political Science',
        'Education',
        'Architecture',
        'Agriculture'
    ];
}

function getAcademicYears() {
    $currentYear = date('Y');
    $years = [];
    for ($i = 0; $i < 5; $i++) {
        $years[] = ($currentYear - $i) . '/' . ($currentYear - $i + 1);
    }
    return $years;
}
?>

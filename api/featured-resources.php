<?php
header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../includes/functions.php';

try {
    // Get featured resources (top rated with minimum reviews)
    $stmt = $pdo->prepare("
        SELECT 
            r.id,
            r.title,
            r.description,
            r.file_type,
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
        WHERE r.is_flagged = 0
        GROUP BY r.id
        HAVING average_rating >= 3.5 AND review_count >= 2
        ORDER BY average_rating DESC, review_count DESC, r.upload_date DESC
        LIMIT 6
    ");
    
    $stmt->execute();
    $resources = $stmt->fetchAll();
    
    // Format the response
    $formattedResources = array_map(function($resource) {
        return [
            'id' => (int)$resource['id'],
            'title' => $resource['title'],
            'description' => $resource['description'],
            'file_type' => $resource['file_type'],
            'subject' => $resource['subject'],
            'course' => $resource['course'],
            'academic_year' => $resource['academic_year'],
            'upload_date' => $resource['upload_date'],
            'download_count' => (int)$resource['download_count'],
            'view_count' => (int)$resource['view_count'],
            'favorites_count' => (int)$resource['favorites_count'],
            'uploader_name' => $resource['uploader_name'],
            'uploader_university' => $resource['uploader_university'],
            'average_rating' => round((float)$resource['average_rating'], 1),
            'review_count' => (int)$resource['review_count']
        ];
    }, $resources);
    
    sendJsonResponse([
        'success' => true,
        'resources' => $formattedResources,
        'count' => count($formattedResources)
    ]);
    
} catch (PDOException $e) {
    error_log("Featured resources API error: " . $e->getMessage());
    sendJsonResponse([
        'success' => false,
        'message' => 'Failed to load featured resources'
    ], 500);
} catch (Exception $e) {
    error_log("Featured resources API error: " . $e->getMessage());
    sendJsonResponse([
        'success' => false,
        'message' => 'An error occurred'
    ], 500);
}
?>

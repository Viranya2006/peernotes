<?php
header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../includes/functions.php';

$query = $_GET['q'] ?? '';
$suggestions = [];

if (strlen($query) >= 2) {
    try {
        // Get subject suggestions
        $stmt = $pdo->prepare("
            SELECT DISTINCT subject as value, subject as text, 'bi-book' as icon
            FROM resources 
            WHERE subject LIKE ? AND is_flagged = 0
            ORDER BY subject
            LIMIT 5
        ");
        $stmt->execute(["%$query%"]);
        $subjectSuggestions = $stmt->fetchAll();
        
        // Get course suggestions
        $stmt = $pdo->prepare("
            SELECT DISTINCT course as value, course as text, 'bi-mortarboard' as icon
            FROM resources 
            WHERE course LIKE ? AND is_flagged = 0
            ORDER BY course
            LIMIT 5
        ");
        $stmt->execute(["%$query%"]);
        $courseSuggestions = $stmt->fetchAll();
        
        // Get title suggestions
        $stmt = $pdo->prepare("
            SELECT DISTINCT title as value, title as text, 'bi-file-earmark' as icon
            FROM resources 
            WHERE title LIKE ? AND is_flagged = 0
            ORDER BY title
            LIMIT 5
        ");
        $stmt->execute(["%$query%"]);
        $titleSuggestions = $stmt->fetchAll();
        
        // Combine and deduplicate suggestions
        $allSuggestions = array_merge($subjectSuggestions, $courseSuggestions, $titleSuggestions);
        $uniqueSuggestions = [];
        $seen = [];
        
        foreach ($allSuggestions as $suggestion) {
            $key = strtolower($suggestion['value']);
            if (!isset($seen[$key])) {
                $uniqueSuggestions[] = $suggestion;
                $seen[$key] = true;
            }
        }
        
        // Limit to 10 suggestions
        $suggestions = array_slice($uniqueSuggestions, 0, 10);
        
    } catch (PDOException $e) {
        error_log("Search suggestions API error: " . $e->getMessage());
    }
}

sendJsonResponse([
    'success' => true,
    'suggestions' => $suggestions,
    'query' => $query
]);
?>

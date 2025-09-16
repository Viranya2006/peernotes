<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'peernotes');
define('DB_USER', 'root');
define('DB_PASS', '');

try {
    // First try to connect to the database
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    // If database doesn't exist, show helpful error message
    if ($e->getCode() == 1049) {
        die("
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 50px auto; padding: 20px; border: 1px solid #ddd; border-radius: 10px; background: #f9f9f9;'>
            <h2 style='color: #d32f2f;'>Database Setup Required</h2>
            <p><strong>Error:</strong> Database 'peernotes' does not exist.</p>
            <h3>To fix this:</h3>
            <ol>
                <li>Open phpMyAdmin: <a href='http://localhost/phpmyadmin' target='_blank'>http://localhost/phpmyadmin</a></li>
                <li>Create a new database named 'peernotes'</li>
                <li>Import the schema file: <code>database/schema.sql</code></li>
                <li>Refresh this page</li>
            </ol>
            <p><strong>Quick Setup:</strong></p>
            <pre style='background: #fff; padding: 10px; border-radius: 5px; overflow-x: auto;'>
CREATE DATABASE peernotes CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE peernotes;
-- Then import database/schema.sql file</pre>
        </div>
        ");
    } else {
        die("
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 50px auto; padding: 20px; border: 1px solid #ddd; border-radius: 10px; background: #f9f9f9;'>
            <h2 style='color: #d32f2f;'>Database Connection Error</h2>
            <p><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>
            <h3>Possible solutions:</h3>
            <ul>
                <li>Make sure MySQL service is running</li>
                <li>Check database credentials in config/database.php</li>
                <li>Verify database 'peernotes' exists</li>
            </ul>
        </div>
        ");
    }
}
?>

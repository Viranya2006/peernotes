<?php
// Database setup script for PeerNotes
// Run this file once to create the database and tables

// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');

echo "<h2>PeerNotes Database Setup</h2>";

try {
    // Connect to MySQL server (without specifying database)
    $pdo = new PDO("mysql:host=" . DB_HOST . ";charset=utf8mb4", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<p>✓ Connected to MySQL server</p>";
    
    // Create database if it doesn't exist
    $pdo->exec("CREATE DATABASE IF NOT EXISTS peernotes CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "<p>✓ Database 'peernotes' created/verified</p>";
    
    // Select the database
    $pdo->exec("USE peernotes");
    echo "<p>✓ Selected database 'peernotes'</p>";
    
    // Read and execute the schema file
    $schema = file_get_contents('database/schema.sql');
    
    // Split the schema into individual statements
    $statements = explode(';', $schema);
    
    $successCount = 0;
    $errorCount = 0;
    
    foreach ($statements as $statement) {
        $statement = trim($statement);
        
        // Skip empty statements and comments
        if (empty($statement) || strpos($statement, '--') === 0 || strpos($statement, 'CREATE DATABASE') === 0 || strpos($statement, 'USE ') === 0) {
            continue;
        }
        
        try {
            $pdo->exec($statement);
            $successCount++;
        } catch (PDOException $e) {
            $errorCount++;
            echo "<p style='color: orange;'>⚠ Statement failed: " . htmlspecialchars(substr($statement, 0, 50)) . "... - " . htmlspecialchars($e->getMessage()) . "</p>";
        }
    }
    
    echo "<h3>Setup Complete!</h3>";
    echo "<p>✓ Successfully executed $successCount statements</p>";
    if ($errorCount > 0) {
        echo "<p style='color: orange;'>⚠ $errorCount statements had issues (this is usually normal for some MySQL versions)</p>";
    }
    
    // Test the connection with the main config
    echo "<h3>Testing Application Connection...</h3>";
    
    // Test if we can connect using the main config
    $testPdo = new PDO("mysql:host=" . DB_HOST . ";dbname=peernotes;charset=utf8mb4", DB_USER, DB_PASS);
    $testPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Test a simple query
    $stmt = $testPdo->query("SELECT COUNT(*) as count FROM users");
    $result = $stmt->fetch();
    
    echo "<p>✓ Application can connect to database</p>";
    echo "<p>✓ Found " . $result['count'] . " users in database</p>";
    
    echo "<div style='background: #d4edda; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
    echo "<h3 style='color: #155724; margin-top: 0;'>🎉 Setup Successful!</h3>";
    echo "<p><strong>Default Admin Account:</strong></p>";
    echo "<ul>";
    echo "<li>Email: admin@peernotes.lk</li>";
    echo "<li>Password: admin123</li>";
    echo "</ul>";
    echo "<p><a href='index.php' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Go to PeerNotes</a></p>";
    echo "</div>";
    
} catch (PDOException $e) {
    echo "<div style='background: #f8d7da; padding: 15px; border-radius: 5px; color: #721c24;'>";
    echo "<h3>❌ Setup Failed</h3>";
    echo "<p><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<h4>Possible solutions:</h4>";
    echo "<ul>";
    echo "<li>Make sure WAMP/XAMPP MySQL service is running</li>";
    echo "<li>Check if MySQL is accessible on localhost</li>";
    echo "<li>Verify MySQL credentials in this file</li>";
    echo "</ul>";
    echo "</div>";
}
?>
